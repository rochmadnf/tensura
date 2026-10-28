<?php

namespace Tests\Feature;

use App\Models\DocumentFont;
use App\Services\Letter\ConvertLetterPdf;
use App\Services\Letter\DocumentFontPackage;
use App\Services\Letter\DocumentFontRegistry;
use App\Services\Letter\InstallDocumentFont;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

class DocumentFontPackageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_shipped_package_contains_verified_static_fonts_and_reports_missing_variants(): void
    {
        $this->isolateFonts();
        config(['document-fonts.package_directory' => resource_path('fonts/documents')]);
        $manifest = app(DocumentFontPackage::class)->manifest();
        $registry = app(DocumentFontRegistry::class);

        $this->assertNotEmpty($manifest['fonts']);
        $keys = [];
        foreach ($manifest['missing'] as $missing) {
            $this->assertNotContains($missing, array_map(fn(array $entry): string => $entry['family'] . ' · ' . $entry['style'], $manifest['fonts']));
        }
        foreach ($manifest['fonts'] as $entry) {
            $key = $registry->faceKey($entry['family'], $entry['style']);
            $this->assertNotContains($key, $keys);
            $keys[] = $key;
            $path = resource_path('fonts/documents/' . $entry['file']);
            $this->assertSame($entry['sha256'], hash_file('sha256', $path));
            $face = $registry->scan($path);
            $this->assertSame($entry['family'], $face['family']);
            $this->assertSame($entry['style'], $face['style']);
        }
    }

    public function test_office_bundle_only_reports_missing_variants_in_its_documented_scope(): void
    {
        $manifest = json_decode(file_get_contents(resource_path('fonts/documents/manifest.json')), true, 512, JSON_THROW_ON_ERROR);
        $core = ['Regular', 'Bold', 'Italic', 'Bold Italic'];
        $families = array_fill_keys(['Arial', 'Calibri', 'Cambria', 'Candara', 'Consolas', 'Constantia', 'Corbel', 'Courier New', 'Georgia', 'Times New Roman', 'Trebuchet MS', 'Verdana'], $core);
        $families['Tahoma'] = ['Regular', 'Bold'];
        $expectedMissing = [];
        foreach ($families as $family => $styles) {
            $entries = array_values(array_filter($manifest['fonts'], fn(array $entry): bool => $entry['family'] === $family));
            $this->assertNotEmpty($entries, $family);
            foreach ($entries as $entry) {
                $this->assertContains($entry['style'], $styles, $family);
            }
            foreach ($styles as $style) {
                if (!in_array($style, array_column($entries, 'style'), true)) {
                    $expectedMissing[] = $family . ' · ' . $style;
                }
            }
        }
        $reportedMissing = array_values(array_filter($manifest['missing'], fn(string $missing): bool => isset($families[explode(' · ', $missing, 2)[0]])));
        sort($expectedMissing);
        sort($reportedMissing);

        $this->assertSame($expectedMissing, $reportedMissing);
    }

    public function test_sync_does_not_warn_for_tahoma_variants_that_do_not_exist(): void
    {
        $this->isolateFonts();
        $manifest = json_decode(file_get_contents(resource_path('fonts/documents/manifest.json')), true, 512, JSON_THROW_ON_ERROR);
        $manifest['fonts'] = array_values(array_filter($manifest['fonts'], fn(array $entry): bool => $entry['family'] === 'Tahoma'));
        foreach ($manifest['fonts'] as $entry) {
            Storage::disk('local')->put('package/' . $entry['file'], file_get_contents(resource_path('fonts/documents/' . $entry['file'])));
        }
        Storage::disk('local')->put('package/manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));

        $this->artisan('document-fonts:sync')
            ->doesntExpectOutputToContain('Tahoma · Italic')
            ->doesntExpectOutputToContain('Tahoma · Bold Italic')
            ->assertSuccessful();

        $this->assertSame(['Bold', 'Regular'], DocumentFont::query()->orderBy('style')->pluck('style')->all());
        $report = app(DocumentFontRegistry::class)->report([['family' => 'Tahoma', 'bold' => false, 'italic' => true]]);
        $this->assertFalse($report['entries'][0]['available']);
    }

    public function test_sync_is_repeatable_repairs_missing_storage_and_serves_private_fonts(): void
    {
        $entry = $this->isolateFonts();

        $this->artisan('document-fonts:sync')->expectsOutput('1 font paket tersinkron.')->assertSuccessful();
        $font = DocumentFont::sole();
        $this->assertNull($font->uploaded_by);
        $this->assertSame($entry['sha256'], $font->sha256);
        $this->assertSame(file_get_contents(resource_path('fonts/documents/arial.ttf')), Storage::disk('local')->get($font->path));
        $this->artisan('document-fonts:sync')->assertSuccessful();
        Storage::disk('local')->delete($font->path);
        $this->artisan('document-fonts:sync')->assertSuccessful();
        $this->assertDatabaseCount('document_fonts', 1);
        Storage::disk('local')->assertExists($font->path);
        $this->get(route('document-fonts.file', $font->uuid))->assertRedirectToRoute('login.index');
        $this->actingAs(UserFactory::new()->create());
        $this->get(route('document-fonts.file', $font->uuid))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $report = app(DocumentFontRegistry::class)->report([['family' => 'Arial', 'bold' => false, 'italic' => false]]);
        $this->assertSame(route('document-fonts.file', $font->uuid, false), $report['entries'][0]['browser_url']);
    }

    public function test_invalid_checksum_missing_file_metadata_and_license_fail_before_installing(): void
    {
        $entry = $this->isolateFonts();
        foreach ([['sha256' => str_repeat('0', 64)], ['file' => 'missing.ttf'], ['family' => 'Wrong Family'], ['server' => false]] as $change) {
            $this->manifest([...$entry, ...$change]);

            $this->artisan('document-fonts:sync')->assertFailed();
            $this->assertDatabaseCount('document_fonts', 0);
            $this->assertSame([], Storage::disk('local')->allFiles(config('document-fonts.bundled_directory')));
        }
    }

    public function test_corrupt_font_and_unsafe_manifest_path_are_rejected(): void
    {
        $entry = $this->isolateFonts();
        Storage::disk('local')->put('package/arial.ttf', "\x00\x01\x00\x00broken");
        $this->manifest([...$entry, 'sha256' => hash('sha256', "\x00\x01\x00\x00broken")]);

        $this->artisan('document-fonts:sync')->assertFailed();
        $this->manifest([...$entry, 'file' => '../arial.ttf']);
        $this->artisan('document-fonts:sync')->assertFailed();
        $this->assertDatabaseCount('document_fonts', 0);
    }

    public function test_conflicting_managed_font_is_preserved_when_sync_fails(): void
    {
        $entry = $this->isolateFonts();
        $disk = Storage::disk('local');
        $disk->put('upload.ttf', $disk->get('package/arial.ttf') . "\0");
        $font = app(InstallDocumentFont::class)->installFile($disk->path('upload.ttf'), 'upload.ttf', UserFactory::new()->create());

        $this->artisan('document-fonts:sync')->assertFailed();
        $this->assertDatabaseCount('document_fonts', 1);
        $this->assertSame($entry['sha256'], hash_file('sha256', $disk->path('package/arial.ttf')));
        $this->assertSame($font->sha256, hash('sha256', $disk->get($font->path)));
    }

    public function test_webfont_permission_gates_both_report_and_direct_endpoint(): void
    {
        $entry = $this->isolateFonts();
        $this->manifest([...$entry, 'webfont' => false]);
        $this->artisan('document-fonts:sync')->assertSuccessful();
        $font = DocumentFont::sole();
        $this->actingAs(UserFactory::new()->create());

        $report = app(DocumentFontRegistry::class)->report([['family' => 'Arial', 'bold' => false, 'italic' => false]]);

        $this->assertTrue($report['entries'][0]['available']);
        $this->assertNull($report['entries'][0]['browser_url']);
        $this->get(route('document-fonts.file', $font->uuid))->assertNotFound();
    }

    public function test_registry_and_real_fontconfig_choose_bundle_then_upload_then_system(): void
    {
        $this->isolateFonts();
        $disk = Storage::disk('local');
        $bytes = $disk->get('package/arial.ttf');
        $paths = ['bundled_directory', 'directory'];
        foreach ($paths as $key) {
            $disk->put(config('document-fonts.' . $key) . '/arial.ttf', $bytes . $key);
        }
        $disk->put('system/arial.ttf', $bytes . 'system');
        $registry = app(DocumentFontRegistry::class);
        $requirement = [['family' => 'Arial', 'bold' => false, 'italic' => false]];
        $fingerprints = [];
        foreach ([...$paths, null] as $key) {
            $registry->inventory(true);
            $fingerprints[] = $registry->fingerprint($requirement);
            $path = $key === null ? 'system/arial.ttf' : config('document-fonts.' . $key) . '/arial.ttf';
            $profile = $registry->conversionProfile($requirement);
            $match = Process::env($profile['environment'])->run(['/usr/bin/fc-match', '--format', '%{file}', 'Arial:style=Regular']);

            $this->assertTrue($match->successful());
            $this->assertSame($disk->path($path), $match->output());
            $this->assertSame(hash('sha256', $disk->get($path)), $registry->report($requirement)['entries'][0]['hash']);
            $disk->delete($path);
        }
        $this->assertCount(3, array_unique($fingerprints));
    }

    public function test_fallback_uses_bundle_and_missing_variant_can_use_system(): void
    {
        $this->isolateFonts();
        $disk = Storage::disk('local');
        $disk->put(config('document-fonts.bundled_directory') . '/arial.ttf', $disk->get('package/arial.ttf'));
        $disk->put('system/arialbd.ttf', file_get_contents(resource_path('fonts/documents/arialbd.ttf')));
        $registry = app(DocumentFontRegistry::class);
        $requirements = [['family' => 'Arial', 'bold' => true, 'italic' => false], ['family' => 'Missing Font', 'bold' => false, 'italic' => false]];

        $report = $registry->report($requirements);
        $profile = $registry->conversionProfile($requirements);
        $match = Process::env($profile['environment'])->run(['/usr/bin/fc-match', '--format', '%{file}', 'Missing Font']);

        $this->assertSame(hash_file('sha256', $disk->path('system/arialbd.ttf')), $report['entries'][0]['hash']);
        $this->assertFalse($report['entries'][1]['available']);
        $this->assertSame('Arial', $report['entries'][1]['fallback']);
        $this->assertSame($disk->path(config('document-fonts.bundled_directory') . '/arial.ttf'), $match->output());
    }

    public function test_missing_regular_fallback_does_not_replace_available_bold_variant(): void
    {
        $this->isolateFonts();
        $disk = Storage::disk('local');
        foreach (['arial.ttf', 'calibrib.ttf'] as $file) {
            $disk->put(config('document-fonts.bundled_directory') . '/' . $file, file_get_contents(resource_path('fonts/documents/' . $file)));
        }
        $requirements = [['family' => 'Calibri', 'bold' => false, 'italic' => false], ['family' => 'Calibri', 'bold' => true, 'italic' => false]];
        $registry = app(DocumentFontRegistry::class);
        $profile = $registry->conversionProfile($requirements);

        $regular = Process::env($profile['environment'])->run(['/usr/bin/fc-match', '--format', '%{file}', 'Calibri:weight=80:slant=0']);
        $bold = Process::env($profile['environment'])->run(['/usr/bin/fc-match', '--format', '%{file}', 'Calibri:weight=200:slant=0']);

        $this->assertSame($disk->path(config('document-fonts.bundled_directory') . '/arial.ttf'), $regular->output());
        $this->assertSame($disk->path(config('document-fonts.bundled_directory') . '/calibrib.ttf'), $bold->output());
        $before = $profile;
        foreach (['system', config('document-fonts.bundled_directory')] as $directory) {
            $disk->put($directory . '/times.ttf', file_get_contents(resource_path('fonts/documents/times.ttf')));
        }
        $registry->inventory(true);
        $after = $registry->conversionProfile($requirements);
        $this->assertSame($before['fingerprint'], $after['fingerprint']);
        $this->assertSame($before['environment'], $after['environment']);
    }

    public function test_real_pdf_embeds_all_four_bundled_arial_variants_without_system_fonts(): void
    {
        $this->isolateFonts();
        $disk = Storage::disk('local');
        $document = new PhpWord();
        $section = $document->addSection();
        $requirements = [];
        foreach (['arial.ttf' => [false, false], 'arialbd.ttf' => [true, false], 'ariali.ttf' => [false, true], 'arialbi.ttf' => [true, true]] as $file => [$bold, $italic]) {
            $disk->put(config('document-fonts.bundled_directory') . '/' . $file, file_get_contents(resource_path('fonts/documents/' . $file)));
            $section->addText('Contoh dokumen dengan font paket.', ['name' => 'Arial', 'bold' => $bold, 'italic' => $italic]);
            $requirements[] = ['family' => 'Arial', 'bold' => $bold, 'italic' => $italic];
        }
        IOFactory::createWriter($document, 'Word2007')->save($disk->path('sample.docx'));
        $contents = $disk->get('sample.docx');
        $profile = app(DocumentFontRegistry::class)->conversionProfile($requirements);

        $result = app(ConvertLetterPdf::class)->convertSource('pdf-test', $contents, hash('sha256', $contents), false, $profile);
        $fonts = Process::run(['/usr/bin/pdffonts', $disk->path($result['path'])]);

        $this->assertTrue($fonts->successful());
        foreach (['ArialMT', 'Arial-BoldMT', 'Arial-ItalicMT', 'Arial-BoldItalicMT'] as $name) {
            $this->assertMatchesRegularExpression('/' . preg_quote($name, '/') . '\s+TrueType\s+WinAnsi\s+yes/', $fonts->output());
        }
        $this->assertSame($contents, $disk->get('sample.docx'));
    }

    public function test_fallback_fingerprint_tracks_source_even_when_font_bytes_are_identical(): void
    {
        $this->isolateFonts();
        $disk = Storage::disk('local');
        $disk->put('system/arial.ttf', $disk->get('package/arial.ttf'));
        $registry = app(DocumentFontRegistry::class);
        $requirements = [['family' => 'Missing Font', 'bold' => false, 'italic' => false]];
        $before = $registry->conversionProfile($requirements)['fingerprint'];

        $disk->put(config('document-fonts.bundled_directory') . '/arial.ttf', $disk->get('package/arial.ttf'));
        $registry->inventory(true);

        $this->assertNotSame($before, $registry->conversionProfile($requirements)['fingerprint']);
    }

    private function isolateFonts(): array
    {
        Storage::fake('local');
        $disk = Storage::disk('local');
        $disk->makeDirectory('system');
        $disk->put('system.conf', '<fontconfig><dir>' . htmlspecialchars($disk->path('system'), ENT_XML1) . '</dir></fontconfig>');
        config(['document-fonts.system_config' => $disk->path('system.conf'), 'document-fonts.package_directory' => $disk->path('package')]);
        $entry = collect(json_decode(file_get_contents(resource_path('fonts/documents/manifest.json')), true)['fonts'])->firstWhere('file', 'arial.ttf');
        $disk->put('package/arial.ttf', file_get_contents(resource_path('fonts/documents/arial.ttf')));
        $this->manifest($entry);

        return $entry;
    }

    private function manifest(array $entry): void
    {
        Storage::disk('local')->put('package/manifest.json', json_encode(['schema_version' => 1, 'missing' => [], 'fonts' => [$entry]], JSON_THROW_ON_ERROR));
    }
}
