<?php

namespace Tests\Feature;

use App\Models\DocumentFont;
use App\Services\Letter\AddDocumentFontToPackage;
use App\Services\Letter\DocumentFontPackage;
use App\Services\Letter\DocumentFontRegistry;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class AddDocumentFontTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_add_reads_metadata_checksum_and_license_and_removes_completed_missing_variant(): void
    {
        $source = $this->package();

        $this->artisan('document-fonts:add', ['font' => $source, '--webfont' => true, '--no-sync' => true, '--no-interaction' => true])->assertSuccessful();

        $manifest = $this->manifest();
        $this->assertSame('Catatan lisensi tetap tersimpan.', $manifest['license_confirmation']);
        $this->assertSame(['Cambria · Regular'], $manifest['missing']);
        $this->assertSame([['file' => 'arial-regular.ttf', 'family' => 'Arial', 'style' => 'Regular', 'sha256' => hash_file('sha256', $source), 'server' => true, 'webfont' => true]], $manifest['fonts']);
        $this->assertSame(file_get_contents($source), Storage::disk('local')->get('package/arial-regular.ttf'));
        $this->assertDatabaseCount('document_fonts', 0);
    }

    public function test_interactive_choice_can_save_package_for_later_sync(): void
    {
        $source = $this->package();

        $this->artisan('document-fonts:add', ['font' => $source])
            ->expectsConfirmation('Kapan paket font disinkronkan?', 'no')
            ->expectsOutput('Paket tersimpan. Sinkron nanti dengan: php artisan document-fonts:sync --no-interaction')
            ->assertSuccessful();

        $this->assertFalse($this->manifest()['fonts'][0]['webfont']);
        $this->assertDatabaseCount('document_fonts', 0);
    }

    public function test_interactive_choice_can_sync_immediately(): void
    {
        $source = $this->package();

        $this->artisan('document-fonts:add', ['font' => $source])
            ->expectsConfirmation('Kapan paket font disinkronkan?', 'yes')
            ->assertSuccessful();

        $font = DocumentFont::sole();
        $this->assertSame('Arial', $font->family);
        $this->assertNull($font->uploaded_by);
        Storage::disk('local')->assertExists($font->path);
    }

    public function test_sync_flag_installs_the_font_without_prompting(): void
    {
        $source = $this->package();

        $this->artisan('document-fonts:add', ['font' => $source, '--sync' => true, '--no-interaction' => true])->assertSuccessful();

        $font = DocumentFont::sole();
        $this->assertSame(hash_file('sha256', $source), $font->sha256);
        Storage::disk('local')->assertExists($font->path);
    }

    public function test_conflicting_flags_or_missing_noninteractive_choice_do_not_modify_package(): void
    {
        $source = $this->package();
        $before = Storage::disk('local')->get('package/manifest.json');
        foreach ([['--sync' => true, '--no-sync' => true], ['--no-interaction' => true]] as $options) {
            $this->artisan('document-fonts:add', ['font' => $source, ...$options])->assertFailed();
            $this->assertSame($before, Storage::disk('local')->get('package/manifest.json'));
            Storage::disk('local')->assertMissing('package/arial-regular.ttf');
        }
    }

    public function test_adding_same_bytes_preserves_existing_permissions_and_filename(): void
    {
        $source = $this->package();
        $this->artisan('document-fonts:add', ['font' => $source, '--no-sync' => true])->assertSuccessful();
        $before = Storage::disk('local')->get('package/manifest.json');

        $this->artisan('document-fonts:add', ['font' => $source, '--webfont' => true, '--no-sync' => true])->assertSuccessful();

        $this->assertSame($before, Storage::disk('local')->get('package/manifest.json'));
        $this->assertCount(1, $this->manifest()['fonts']);
        $this->assertCount(2, Storage::disk('local')->files('package'));
    }

    public function test_conflicting_family_or_destination_file_is_never_overwritten(): void
    {
        $source = $this->package();
        $this->artisan('document-fonts:add', ['font' => $source, '--no-sync' => true])->assertSuccessful();
        $before = Storage::disk('local')->get('package/manifest.json');
        Storage::disk('local')->put('input/different.ttf', file_get_contents($source) . "\0");

        $this->artisan('document-fonts:add', ['font' => Storage::disk('local')->path('input/different.ttf'), '--no-sync' => true])->assertFailed();

        $this->assertSame($before, Storage::disk('local')->get('package/manifest.json'));
        $this->assertSame(file_get_contents($source), Storage::disk('local')->get('package/arial-regular.ttf'));
        Storage::disk('local')->put('package/arial-bold.ttf', 'Berkas lama');
        $this->artisan('document-fonts:add', ['font' => resource_path('fonts/documents/arialbd.ttf'), '--no-sync' => true])->assertFailed();
        $this->assertSame('Berkas lama', Storage::disk('local')->get('package/arial-bold.ttf'));
        $this->assertSame($before, Storage::disk('local')->get('package/manifest.json'));
    }

    public function test_missing_corrupt_collection_mismatched_extension_and_oversized_fonts_leave_manifest_unchanged(): void
    {
        $source = $this->package();
        $disk = Storage::disk('local');
        $disk->put('input/corrupt.ttf', "\x00\x01\x00\x00broken");
        $disk->put('input/collection.ttf', 'ttcfbroken');
        $disk->put('input/wrong.otf', file_get_contents($source));
        $disk->put('input/script.php', file_get_contents($source));
        $before = $disk->get('package/manifest.json');
        foreach (['missing.ttf', 'corrupt.ttf', 'collection.ttf', 'wrong.otf', 'script.php'] as $file) {
            $this->artisan('document-fonts:add', ['font' => $disk->path('input/' . $file), '--no-sync' => true])->assertFailed();
            $this->assertSame($before, $disk->get('package/manifest.json'));
        }
        config(['document-fonts.max_upload_kb' => 1]);
        $this->artisan('document-fonts:add', ['font' => $source, '--no-sync' => true])->assertFailed();
        $this->assertSame($before, $disk->get('package/manifest.json'));
        $this->assertCount(1, $disk->files('package'));
    }

    public function test_unsupported_variant_is_rejected_without_copying_font(): void
    {
        $source = $this->package();
        $registry = $this->partialMock(DocumentFontRegistry::class);
        $registry->shouldReceive('scan')->once()->andReturn(['family' => 'Example', 'style' => 'Light']);

        $this->artisan('document-fonts:add', ['font' => $source, '--no-sync' => true])
            ->expectsOutput('Varian font belum didukung. Gunakan Regular, Bold, Italic, atau Bold Italic.')
            ->assertFailed();

        $this->assertSame([], $this->manifest()['fonts']);
    }

    public function test_manifest_write_failure_removes_new_font_and_temporary_files(): void
    {
        $source = $this->package();
        $before = Storage::disk('local')->get('package/manifest.json');
        $files = Mockery::mock(Filesystem::class)->makePartial();
        $files->shouldReceive('move')->withArgs(fn(string $from, string $to): bool => basename($to) === 'manifest.json')->once()->andReturn(false);
        $this->app->instance(AddDocumentFontToPackage::class, new AddDocumentFontToPackage(app(DocumentFontPackage::class), app(DocumentFontRegistry::class), $files));

        $this->artisan('document-fonts:add', ['font' => $source, '--no-sync' => true])->assertFailed();

        $this->assertSame($before, Storage::disk('local')->get('package/manifest.json'));
        $this->assertCount(1, Storage::disk('local')->files('package', true));
        $this->assertSame([], glob(Storage::disk('local')->path('package/.font-*')));
    }

    public function test_font_copy_failure_preserves_manifest(): void
    {
        $source = $this->package();
        $before = Storage::disk('local')->get('package/manifest.json');
        $files = Mockery::mock(Filesystem::class)->makePartial();
        $files->shouldReceive('put')->withArgs(fn(string $path, string $contents): bool => str_starts_with(basename($path), '.font-'))->once()->andReturn(false);
        $this->app->instance(AddDocumentFontToPackage::class, new AddDocumentFontToPackage(app(DocumentFontPackage::class), app(DocumentFontRegistry::class), $files));

        $this->artisan('document-fonts:add', ['font' => $source, '--no-sync' => true])->assertFailed();

        $this->assertSame($before, Storage::disk('local')->get('package/manifest.json'));
        Storage::disk('local')->assertMissing('package/arial-regular.ttf');
    }

    public function test_sync_failure_preserves_added_package_and_returns_retry_instruction(): void
    {
        $source = $this->package();
        Process::fake(['*fc-cache*' => Process::result(exitCode: 1)]);

        $this->artisan('document-fonts:add', ['font' => $source, '--sync' => true])
            ->expectsOutput('Paket tetap tersimpan, tetapi sinkronisasi gagal. Coba lagi: php artisan document-fonts:sync --no-interaction')
            ->assertFailed();

        Storage::disk('local')->assertExists('package/arial-regular.ttf');
        $this->assertCount(1, $this->manifest()['fonts']);
        Process::assertRan(fn($process): bool => str_ends_with($process->command[0], 'fc-cache'));
        $this->assertDatabaseCount('document_fonts', 0);
    }

    public function test_competing_writer_is_rejected_and_lock_is_released_after_failure(): void
    {
        $source = $this->package();
        $disk = Storage::disk('local');
        $runtime = config('document-fonts.runtime_directory');
        $disk->makeDirectory($runtime);
        $lock = fopen($disk->path($runtime . '/package-' . hash('sha256', realpath(config('document-fonts.package_directory'))) . '.lock'), 'c');
        flock($lock, LOCK_EX);
        try {
            $this->artisan('document-fonts:add', ['font' => $source, '--no-sync' => true])->expectsOutput('Paket font sedang diperbarui. Coba lagi.')->assertFailed();
            $this->assertSame([], $this->manifest()['fonts']);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        $this->artisan('document-fonts:add', ['font' => $source, '--no-sync' => true])->assertSuccessful();
        $this->assertCount(1, $this->manifest()['fonts']);
    }

    public function test_variable_font_is_rejected_even_if_scanner_returns_only_one_face(): void
    {
        $source = $this->package();
        Process::fake(['*fc-scan*' => function ($process) {
            $path = $process->command[array_key_last($process->command)];

            return Process::result(output: "Arial\tRegular\t80\t0\t" . $path . "\t0\nTrue\n");
        }]);

        $this->artisan('document-fonts:add', ['font' => $source, '--no-sync' => true])->assertFailed();

        $this->assertSame([], $this->manifest()['fonts']);
        Storage::disk('local')->assertMissing('package/arial-regular.ttf');
        Process::assertRan(fn($process): bool => str_ends_with($process->command[0], 'fc-scan'));
    }

    public function test_filename_collision_in_manifest_preserves_unrelated_entry(): void
    {
        $source = $this->package();
        $manifest = $this->manifest();
        $manifest['fonts'][] = ['file' => 'arial-regular.ttf', 'family' => 'Arial!', 'style' => 'Regular', 'sha256' => str_repeat('a', 64), 'server' => true, 'webfont' => false];
        Storage::disk('local')->put('package/manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));

        $this->artisan('document-fonts:add', ['font' => $source, '--no-sync' => true])->expectsOutput('Nama berkas sudah digunakan oleh font lain dalam manifest.')->assertFailed();

        $this->assertSame($manifest, $this->manifest());
        Storage::disk('local')->assertMissing('package/arial-regular.ttf');
    }

    private function package(): string
    {
        Storage::fake('local');
        $disk = Storage::disk('local');
        $disk->makeDirectory('system');
        $disk->put('system.conf', '<fontconfig><dir>' . htmlspecialchars($disk->path('system'), ENT_XML1) . '</dir></fontconfig>');
        $disk->put('package/manifest.json', json_encode(['schema_version' => 1, 'license_confirmation' => 'Catatan lisensi tetap tersimpan.', 'missing' => ['Arial · Regular', 'Cambria · Regular'], 'fonts' => []], JSON_THROW_ON_ERROR));
        $disk->put('input/Font dengan spasi.TTF', file_get_contents(resource_path('fonts/documents/arial.ttf')));
        config(['document-fonts.system_config' => $disk->path('system.conf'), 'document-fonts.package_directory' => $disk->path('package')]);

        return $disk->path('input/Font dengan spasi.TTF');
    }

    private function manifest(): array
    {
        return json_decode(Storage::disk('local')->get('package/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
    }
}
