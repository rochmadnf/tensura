<?php

namespace Tests\Feature;

use App\Models\DocumentFont;
use App\Services\Letter\DocumentFontRegistry;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentFontTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::disk('local')->makeDirectory('font-test-baseline');
        $directory = htmlspecialchars(Storage::disk('local')->path('font-test-baseline'), ENT_XML1);
        Storage::disk('local')->put('font-test-system.conf', '<fontconfig><dir>' . $directory . '</dir></fontconfig>');
        config(['document-fonts.system_config' => Storage::disk('local')->path('font-test-system.conf')]);
    }

    public function test_upload_verifies_real_fontconfig_inventory_and_serves_private_font_without_exposing_paths(): void
    {
        $actor = UserFactory::new()->create();
        $this->actingAs($actor);
        [$bytes, $extension] = $this->font();
        $this->from('/letter-templates')->post(route('document-fonts.store'), ['font' => UploadedFile::fake()->createWithContent('My Font.' . $extension, $bytes)])->assertSessionHasNoErrors()->assertRedirect('/letter-templates');
        $font = DocumentFont::sole();
        $this->assertSame('ready', $font->status);
        $this->assertSame($actor->id, $font->uploaded_by);
        $this->assertSame(hash('sha256', $bytes), $font->sha256);
        $response = $this->getJson(route('document-fonts.index', ['q' => $font->family]))->assertOk()->assertJsonPath('available', true)->assertJsonPath('data.0.family', $font->family);
        $this->assertStringNotContainsString(Storage::disk('local')->path(''), $response->getContent());
        $this->assertStringNotContainsString('sha256', $response->getContent());
        $this->get(route('document-fonts.file', $font->uuid))->assertOk()->assertHeader('Content-Type', 'font/' . $extension)->assertHeader('Cache-Control', 'no-store, private');
        $report = app(DocumentFontRegistry::class)->report([['family' => $font->family, 'bold' => $font->is_bold, 'italic' => $font->is_italic]]);
        $this->assertTrue($report['entries'][0]['available']);
        $this->assertSame(route('document-fonts.file', $font->uuid, false), $report['entries'][0]['browser_url']);
        $this->post(route('document-fonts.store'), ['font' => UploadedFile::fake()->createWithContent('again.' . $extension, $bytes)])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('document_fonts', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles(config('document-fonts.directory')));
    }

    public function test_different_file_for_same_family_and_variant_does_not_overwrite_installed_font(): void
    {
        $this->actingAs(UserFactory::new()->create());
        [$bytes, $extension] = $this->font();
        $this->post(route('document-fonts.store'), ['font' => UploadedFile::fake()->createWithContent('first.' . $extension, $bytes)])->assertSessionHasNoErrors();
        $font = DocumentFont::sole();
        $this->post(route('document-fonts.store'), ['font' => UploadedFile::fake()->createWithContent('different.' . $extension, $bytes . "\0")])->assertSessionHasErrors('font');
        $this->assertSame($bytes, Storage::disk('local')->get($font->path));
        $this->assertDatabaseCount('document_fonts', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles(config('document-fonts.directory')));
    }

    public function test_invalid_font_extension_corrupt_content_and_size_are_rejected_without_installing(): void
    {
        $this->actingAs(UserFactory::new()->create());
        Exceptions::fake();
        foreach ([UploadedFile::fake()->createWithContent('fake.ttf', 'not a font'), UploadedFile::fake()->createWithContent('broken.ttf', "\x00\x01\x00\x00broken"), UploadedFile::fake()->createWithContent('script.php', "\x00\x01\x00\x00broken"), UploadedFile::fake()->create('huge.ttf', 10241)] as $file) {
            $this->post(route('document-fonts.store'), ['font' => $file])->assertSessionHasErrors('font');
        }
        $this->assertDatabaseCount('document_fonts', 0);
        $this->assertSame([], Storage::disk('local')->allFiles(config('document-fonts.directory')));
    }

    public function test_cache_failure_does_not_mark_font_ready_and_removes_staged_file(): void
    {
        $this->actingAs(UserFactory::new()->create());
        [$bytes, $extension] = $this->font();
        Exceptions::fake();
        Process::fake(['*fc-cache*' => Process::result(errorOutput: 'private internal path', exitCode: 1)]);
        $this->post(route('document-fonts.store'), ['font' => UploadedFile::fake()->createWithContent('font.' . $extension, $bytes)])->assertSessionHasErrors('font');
        $this->assertDatabaseCount('document_fonts', 0);
        $this->assertSame([], Storage::disk('local')->allFiles(config('document-fonts.directory')));
        $this->assertStringNotContainsString('private internal path', session('errors')->first('font'));
    }

    public function test_inventory_failure_is_warning_and_authentication_and_font_file_scope_are_enforced(): void
    {
        $font = DocumentFont::factory()->create();
        $this->getJson(route('document-fonts.index'))->assertUnauthorized();
        $this->postJson(route('document-fonts.store'))->assertUnauthorized();
        $this->get(route('document-fonts.file', $font->uuid))->assertRedirectToRoute('login.index');
        $this->actingAs(UserFactory::new()->create());
        Exceptions::fake();
        Process::fake(['*' => Process::result(exitCode: 1)]);
        $this->getJson(route('document-fonts.index'))->assertOk()->assertJsonPath('available', false)->assertJsonCount(0, 'data');
        $this->get(route('document-fonts.file', $font->uuid))->assertNotFound();
        $this->get('/document-fonts/' . $font->id . '/file')->assertNotFound();
        $this->getJson(route('document-fonts.index', ['q' => str_repeat('x', 101)]))->assertUnprocessable();
    }

    public function test_fingerprint_ignores_json_key_order_and_unrelated_fonts_but_changes_for_required_face(): void
    {
        $this->actingAs(UserFactory::new()->create());
        [$bytes, $extension] = $this->font();
        $registry = app(DocumentFontRegistry::class);
        $face = $this->scanBytes($bytes, $extension);
        $needed = [['family' => $face['family'], 'bold' => $face['bold'], 'italic' => $face['italic']]];
        $other = [['family' => 'Unrelated Missing Family', 'bold' => false, 'italic' => false]];
        $original = $registry->fingerprint($needed);
        $unrelated = $registry->fingerprint($other);
        $this->assertSame($original, $registry->fingerprint([['italic' => $face['italic'], 'family' => $face['family'], 'bold' => $face['bold']]]));
        $this->post(route('document-fonts.store'), ['font' => UploadedFile::fake()->createWithContent('font.' . $extension, $bytes)])->assertSessionHasNoErrors();
        $registry->inventory(true);
        $this->assertNotSame($original, $registry->fingerprint($needed));
        $this->assertSame($unrelated, $registry->fingerprint($other));
    }

    public function test_inventory_matches_regular_and_bold_independently_and_profiles_only_relevant_fonts(): void
    {
        Storage::disk('local')->put('regular.ttf', 'regular font bytes');
        Storage::disk('local')->put('light.ttf', 'light font bytes');
        Storage::disk('local')->put('fallback.ttf', 'fallback font bytes');
        $face = ['family' => 'Example Family', 'aliases' => ['Example Family'], 'style' => 'Regular', 'weight' => 80, 'bold' => false, 'italic' => false, 'path' => Storage::disk('local')->path('regular.ttf'), 'index' => 0];
        $faces = [[...$face, 'style' => 'Light', 'weight' => 50, 'path' => Storage::disk('local')->path('light.ttf')], $face];
        $registry = $this->partialMock(DocumentFontRegistry::class);
        $registry->shouldReceive('inventory')->andReturnUsing(function () use (&$faces): array { return ['available' => true, 'faces' => $faces, 'warning' => null]; });
        $regular = [['family' => 'Example Family', 'bold' => false, 'italic' => false]];
        $report = $registry->report([...$regular, ['family' => 'Example Family', 'bold' => true, 'italic' => false]]);
        $this->assertSame(hash('sha256', 'regular font bytes'), $report['entries'][0]['hash']);
        $this->assertTrue($report['entries'][0]['available']);
        $this->assertFalse($report['entries'][1]['available']);
        $before = $registry->fingerprint($regular);
        $faces[] = [...$face, 'family' => 'Arial', 'aliases' => ['Arial'], 'path' => Storage::disk('local')->path('fallback.ttf')];
        $this->assertSame($before, $registry->fingerprint($regular), 'An unused fallback must not stale a fully available font profile.');
        Process::fake(['*libreoffice*' => Process::result(output: 'LibreOffice test-version')]);
        $profile = $registry->conversionProfile([['family' => 'Missing Family', 'bold' => false, 'italic' => false]]);
        $this->assertSame(['Missing Family' => 'Arial'], $profile['substitutions']);
        $this->assertSame('LibreOffice test-version', $profile['converter']);
        $this->assertStringContainsString('<family>Missing Family</family>', file_get_contents($profile['environment']['FONTCONFIG_FILE']));
        $this->assertStringContainsString('<family>Arial</family>', file_get_contents($profile['environment']['FONTCONFIG_FILE']));
    }

    private function scanBytes(string $bytes, string $extension): array
    {
        Storage::disk('local')->put('sample.' . $extension, $bytes);

        return app(DocumentFontRegistry::class)->scan(Storage::disk('local')->path('sample.' . $extension));
    }

    private function font(): array
    {
        $path = trim(Process::run(['/usr/bin/fc-match', '--format', '%{file}', 'sans-serif'])->output());
        $this->assertFileExists($path, 'The Linux test environment needs a readable static TTF/OTF font.');
        $bytes = file_get_contents($path);

        return [$bytes, substr($bytes, 0, 4) === 'OTTO' ? 'otf' : 'ttf'];
    }
}
