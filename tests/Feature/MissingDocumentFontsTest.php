<?php

namespace Tests\Feature;

use App\Enums\LetterTemplateVersionStatusEnum;
use App\Models\MasterData\LetterTemplate;
use App\Models\MasterData\LetterTemplateVersion;
use App\Services\Letter\InspectMissingDocumentFonts;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use ZipArchive;

class MissingDocumentFontsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_empty_inventory_and_versions_return_zero_without_changing_manifest(): void
    {
        $this->environment();
        $before = Storage::disk('local')->get('package/manifest.json');
        LetterTemplateVersion::factory()->create();

        $status = Artisan::call('document-fonts:missing', ['--no-interaction' => true]);

        $output = Artisan::output();
        $this->assertSame(0, $status);
        $this->assertStringContainsString('Tidak ada kekurangan paket font.', $output);
        $this->assertStringContainsString('1 dilewati tanpa DOCX', $output);
        $this->assertSame($before, Storage::disk('local')->get('package/manifest.json'));
    }

    public function test_command_renders_prompts_tables_for_package_and_template_findings(): void
    {
        $this->environment(missing: ['Cambria · Regular']);
        $this->source('Surat Tugas', 'Unknown Font');

        $status = Artisan::call('document-fonts:missing', ['--no-interaction' => true]);

        $output = Artisan::output();
        $this->assertSame(1, $status);
        foreach (['Kekurangan Paket', 'Cambria', 'Belum ada dalam paket', 'Font Template Belum Tersedia', 'Surat Tugas', 'Unknown Font', 'Fallback', '│'] as $text) {
            $this->assertStringContainsString($text, $output);
        }
    }

    public function test_system_font_satisfies_template_while_package_still_reports_the_missing_font(): void
    {
        $this->environment(missing: ['Arial · Regular']);
        Storage::disk('local')->put('system/arial.ttf', file_get_contents(resource_path('fonts/documents/arial.ttf')));
        $this->source('Sumber Sistem', 'Arial');

        $result = app(InspectMissingDocumentFonts::class)->inspect();

        $this->assertSame([['Arial', 'Regular', '—', 'Belum ada dalam paket']], $result['package']);
        $this->assertSame([], $result['templates']);
        $this->assertSame([], $result['incomplete']);
        $this->assertSame(1, $result['exit_code']);
    }

    public function test_tahoma_package_is_complete_without_italic_variants(): void
    {
        $this->environment(fontFiles: ['tahoma.ttf', 'tahomabd.ttf']);
        $this->source('Surat Tahoma', 'Tahoma');
        foreach (['tahoma.ttf', 'tahomabd.ttf'] as $file) {
            Storage::disk('local')->put('system/' . $file, file_get_contents(resource_path('fonts/documents/' . $file)));
        }

        $result = app(InspectMissingDocumentFonts::class)->inspect();

        $this->assertSame([], $result['package']);
        $this->assertSame([], $result['templates']);
        $this->assertSame(0, $result['exit_code']);
    }

    #[DataProvider('packageProblems')]
    public function test_package_integrity_problems_are_reported(string $problem, string $expected): void
    {
        $this->environment(fontFiles: ['arial.ttf']);
        $disk = Storage::disk('local');
        $manifest = json_decode($disk->get('package/manifest.json'), true);
        if ($problem === 'missing') {
            $disk->delete('package/arial.ttf');
        } elseif ($problem === 'checksum') {
            $disk->put('package/arial.ttf', 'changed');
        } else {
            $manifest['fonts'][0]['family'] = 'Wrong Family';
            $disk->put('package/manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
        }

        $result = app(InspectMissingDocumentFonts::class)->inspect();

        $this->assertSame($expected, $result['package'][0][3]);
        $this->assertSame(1, $result['summary']['package_checked']);
        $this->assertSame(1, $result['exit_code']);
    }

    public static function packageProblems(): array
    {
        return ['missing' => ['missing', 'Berkas font hilang'], 'checksum' => ['checksum', 'Checksum font tidak sesuai'], 'metadata' => ['metadata', 'Metadata font tidak sesuai']];
    }

    public function test_all_version_states_are_scanned_and_regular_does_not_satisfy_bold(): void
    {
        $this->environment();
        Storage::disk('local')->put('system/arial.ttf', file_get_contents(resource_path('fonts/documents/arial.ttf')));
        $template = LetterTemplate::factory()->create(['name' => 'Semua Versi', 'is_active' => false]);
        foreach (LetterTemplateVersionStatusEnum::cases() as $index => $status) {
            $version = $this->source('Semua Versi', 'Arial', bold: true, template: $template, number: $index + 1, status: $status);
            if ($status === LetterTemplateVersionStatusEnum::PUBLISHED) {
                $template->update(['active_version_id' => $version->id]);
            }
        }

        $result = app(InspectMissingDocumentFonts::class)->inspect();

        $this->assertSame([
            ['Semua Versi', '1', 'Arial', 'Bold', 'Arial'],
            ['Semua Versi', '2', 'Arial', 'Bold', 'Arial'],
            ['Semua Versi', '3', 'Arial', 'Bold', 'Arial'],
        ], $result['templates']);
        $this->assertSame(3, $result['summary']['versions_checked']);
        $this->assertSame(3, $result['summary']['versions_with_findings']);
        $this->assertSame(1, $result['exit_code']);
    }

    public function test_repeated_runs_are_deduplicated_and_unused_styles_are_ignored(): void
    {
        $this->environment();
        $version = $this->source('Pengulangan', 'Missing Used Font');
        $disk = Storage::disk('local');
        $zip = new ZipArchive();
        $zip->open($disk->path($version->source_path));
        $styles = $zip->getFromName('word/styles.xml');
        $zip->addFromString('word/styles.xml', str_replace('</w:styles>', '<w:style w:type="paragraph" w:styleId="Unused"><w:rPr><w:rFonts w:ascii="Missing Unused Font" w:hAnsi="Missing Unused Font"/></w:rPr></w:style></w:styles>', $styles));
        $zip->close();
        $version->update(['source_sha256' => hash_file('sha256', $disk->path($version->source_path))]);

        $result = app(InspectMissingDocumentFonts::class)->inspect();

        $this->assertSame([['Pengulangan', '1', 'Missing Used Font', 'Regular', '—']], $result['templates']);
        $this->assertSame([], $result['incomplete']);
    }

    public function test_bad_sources_are_reported_without_stopping_other_templates(): void
    {
        $this->environment();
        $missing = $this->source('DOCX Hilang', 'Ignored');
        $changed = $this->source('DOCX Berubah', 'Ignored');
        $corrupt = $this->source('DOCX Rusak', 'Ignored');
        $this->source('DOCX Baik', 'Missing Valid Font');
        $disk = Storage::disk('local');
        $disk->delete($missing->source_path);
        $disk->put($changed->source_path, 'changed');
        $disk->put($corrupt->source_path, 'not a docx');
        $corrupt->update(['source_sha256' => hash('sha256', 'not a docx')]);

        $result = app(InspectMissingDocumentFonts::class)->inspect();

        $this->assertCount(3, $result['incomplete']);
        $this->assertSame([['DOCX Baik', '1', 'Missing Valid Font', 'Regular', '—']], $result['templates']);
        $this->assertSame(4, $result['summary']['versions_with_findings']);
        $this->assertSame(2, $result['exit_code']);
    }

    public function test_oversized_source_and_inspection_limitations_have_exit_code_two(): void
    {
        $this->environment();
        $version = $this->source('Dokumen Bernomor', 'Arial');
        $disk = Storage::disk('local');
        $zip = new ZipArchive();
        $zip->open($disk->path($version->source_path));
        $document = $zip->getFromName('word/document.xml');
        $zip->addFromString('word/document.xml', str_replace('<w:p>', '<w:p><w:pPr><w:numPr><w:numId w:val="1"/></w:numPr></w:pPr>', $document));
        $zip->close();
        $version->update(['source_sha256' => hash_file('sha256', $disk->path($version->source_path))]);

        $result = app(InspectMissingDocumentFonts::class)->inspect();

        $this->assertNotEmpty($result['incomplete']);
        $this->assertStringContainsString('bullet', $result['incomplete'][0][1]);
        $this->assertSame(2, $result['exit_code']);
        config(['letter-templates.max_upload_kb' => 1]);
        $oversized = app(InspectMissingDocumentFonts::class)->inspect();
        $this->assertStringContainsString('Ukuran DOCX', $oversized['incomplete'][0][1]);
        $this->assertSame(2, $oversized['exit_code']);
    }

    public function test_unavailable_fontconfig_does_not_label_all_template_fonts_missing(): void
    {
        $this->environment(missing: ['Cambria · Regular']);
        $this->source('Runtime Belum Diperiksa', 'Unknown Font');
        Exceptions::fake();
        Process::fake(['*fc-list*' => Process::result(exitCode: 1)]);

        $result = app(InspectMissingDocumentFonts::class)->inspect();

        $this->assertSame([], $result['templates']);
        $this->assertSame('Runtime font', $result['incomplete'][0][0]);
        $this->assertSame(1, $result['summary']['versions_checked']);
        $this->assertSame(2, $result['exit_code']);
        Process::assertRan(fn($process): bool => str_ends_with($process->command[0], 'fc-list'));
        Exceptions::assertReported(\RuntimeException::class);
    }

    public function test_invalid_manifest_does_not_prevent_inspecting_templates(): void
    {
        $this->environment();
        Storage::disk('local')->put('package/manifest.json', '{broken');
        $this->source('Paket Bermasalah', 'Missing Font');

        $result = app(InspectMissingDocumentFonts::class)->inspect();

        $this->assertSame('Manifest paket', $result['incomplete'][0][0]);
        $this->assertCount(1, $result['templates']);
        $this->assertSame(2, $result['exit_code']);
    }

    public function test_inspection_preserves_evidence_audits_manifest_and_source_bytes(): void
    {
        $this->environment();
        $version = $this->source('Bukti Tetap', 'Missing Font');
        $version->update(['validated_at' => now(), 'validated_source_sha256' => $version->source_sha256, 'validation_result' => ['valid' => true], 'visually_confirmed_at' => now(), 'tested_at' => now(), 'test_font_requirements' => [['family' => 'Previous Font', 'bold' => false, 'italic' => false]]]);
        $before = $version->fresh()->getRawOriginal();
        $disk = Storage::disk('local');
        $manifest = $disk->get('package/manifest.json');
        $bytes = $disk->get($version->source_path);

        app(InspectMissingDocumentFonts::class)->inspect();

        $this->assertSame($before, $version->fresh()->getRawOriginal());
        $this->assertSame($manifest, $disk->get('package/manifest.json'));
        $this->assertSame($bytes, $disk->get($version->source_path));
        $this->assertDatabaseCount('letter_template_audits', 0);
    }

    public function test_unreadable_metadata_is_incomplete_and_other_package_files_are_still_checked(): void
    {
        $this->environment(fontFiles: ['arial.ttf', 'arialbd.ttf']);
        $disk = Storage::disk('local');
        $disk->put('package/arial.ttf', "\x00\x01\x00\x00broken");
        $manifest = json_decode($disk->get('package/manifest.json'), true);
        $manifest['fonts'][0]['sha256'] = hash('sha256', "\x00\x01\x00\x00broken");
        $disk->put('package/manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));

        $result = app(InspectMissingDocumentFonts::class)->inspect();

        $this->assertSame([], $result['package']);
        $this->assertSame('Paket: arial.ttf', $result['incomplete'][0][0]);
        $this->assertSame(2, $result['summary']['package_checked']);
        $this->assertSame(2, $result['exit_code']);
    }

    private function environment(array $fontFiles = [], array $missing = []): void
    {
        Storage::fake('local');
        $disk = Storage::disk('local');
        $disk->makeDirectory('system');
        $disk->put('system.conf', '<fontconfig><dir>' . htmlspecialchars($disk->path('system'), ENT_XML1) . '</dir></fontconfig>');
        config(['document-fonts.system_config' => $disk->path('system.conf'), 'document-fonts.package_directory' => $disk->path('package')]);
        $entries = [];
        $shipped = json_decode(file_get_contents(resource_path('fonts/documents/manifest.json')), true)['fonts'];
        foreach ($fontFiles as $file) {
            $entry = collect($shipped)->firstWhere('file', $file);
            $entries[] = $entry;
            $disk->put('package/' . $file, file_get_contents(resource_path('fonts/documents/' . $file)));
        }
        $disk->put('package/manifest.json', json_encode(['schema_version' => 1, 'missing' => $missing, 'fonts' => $entries], JSON_THROW_ON_ERROR));
    }

    private function source(string $name, string $family, bool $bold = false, ?LetterTemplate $template = null, int $number = 1, LetterTemplateVersionStatusEnum $status = LetterTemplateVersionStatusEnum::DRAFT): LetterTemplateVersion
    {
        $template ??= LetterTemplate::factory()->create(['name' => $name]);
        $version = LetterTemplateVersion::factory()->for($template, 'template')->create(['version_number' => $number, 'status' => $status]);
        $document = new PhpWord();
        $document->setDefaultFontName($family);
        $section = $document->addSection();
        $section->addText('Contoh teks', ['name' => $family, 'bold' => $bold]);
        $section->addText('Teks berulang', ['name' => $family, 'bold' => $bold]);
        $disk = Storage::disk('local');
        $path = 'sources/' . $version->uuid . '.docx';
        $disk->makeDirectory('sources');
        IOFactory::createWriter($document, 'Word2007')->save($disk->path($path));
        $version->update(['source_disk' => 'local', 'source_path' => $path, 'source_original_name' => 'source.docx', 'source_size' => $disk->size($path), 'source_sha256' => hash_file('sha256', $disk->path($path))]);

        return $version;
    }
}
