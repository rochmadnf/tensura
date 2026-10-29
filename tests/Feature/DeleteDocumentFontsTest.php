<?php

namespace Tests\Feature;

use App\Enums\LetterTemplateVersionStatusEnum;
use App\Models\DocumentFont;
use App\Models\MasterData\LetterTemplateVersion;
use App\Services\Letter\ChangeBundledDocumentFonts;
use App\Services\Letter\DocumentFontPackage;
use App\Services\Letter\DocumentFontRegistry;
use App\Services\Letter\InspectDocumentFontReferences;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use RuntimeException;
use Tests\TestCase;

class DeleteDocumentFontsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_multisearch_accepts_case_insensitive_queries_and_multiple_variants(): void
    {
        $this->package();
        $this->activate('arial.ttf');
        $this->activate('arialbd.ttf');

        $this->artisan('document-fonts:delete')
            ->expectsSearch('Pilih font paket yang akan dihapus', ['arial.ttf', 'arialbd.ttf'], 'aRiAl', ['arial.ttf' => 'Arial · Regular — arial.ttf', 'arialbd.ttf' => 'Arial · Bold — arialbd.ttf'])
            ->expectsConfirmation('Hapus seluruh font yang dipilih?', 'yes')
            ->expectsOutput('2 varian font paket dihapus.')
            ->assertSuccessful();

        $this->assertSame([], $this->manifest()['fonts']);
        $this->assertSame(['Cambria · Regular'], $this->manifest()['missing']);
        $this->assertDatabaseCount('document_fonts', 0);
        foreach (['arial.ttf', 'arialbd.ttf'] as $file) {
            Storage::disk('local')->assertMissing('package/' . $file);
        }
        $this->assertSame([], Storage::disk('local')->files(config('document-fonts.bundled_directory')));
    }

    public function test_empty_selection_and_cancel_leave_everything_unchanged(): void
    {
        $this->package();
        $before = Storage::disk('local')->get('package/manifest.json');
        $this->artisan('document-fonts:delete')
            ->expectsSearch('Pilih font paket yang akan dihapus', [], 'Arial', ['arial.ttf' => 'Arial · Regular — arial.ttf', 'arialbd.ttf' => 'Arial · Bold — arialbd.ttf'])
            ->assertSuccessful();
        $this->artisan('document-fonts:delete', ['fonts' => ['arial.ttf']])
            ->expectsConfirmation('Hapus seluruh font yang dipilih?', 'no')
            ->assertSuccessful();

        $this->assertSame($before, Storage::disk('local')->get('package/manifest.json'));
        Storage::disk('local')->assertExists('package/arial.ttf');
    }

    public function test_no_interaction_requires_filenames_and_yes_and_unknown_name_blocks_the_entire_batch(): void
    {
        $this->package();
        $before = Storage::disk('local')->get('package/manifest.json');
        foreach ([['--yes' => true], ['fonts' => ['arial.ttf']], ['fonts' => ['arial.ttf', '../other.ttf'], '--yes' => true]] as $options) {
            $this->artisan('document-fonts:delete', [...$options, '--no-interaction' => true])->assertFailed();
        }

        $this->assertSame($before, Storage::disk('local')->get('package/manifest.json'));
        Storage::disk('local')->assertExists('package/arial.ttf');
    }

    public function test_any_used_font_blocks_the_entire_batch_but_force_removes_it_without_altering_template(): void
    {
        $this->package();
        $version = $this->source();
        $before = $version->fresh()->getRawOriginal();
        $bytes = Storage::disk('local')->get($version->source_path);
        $this->artisan('document-fonts:delete', ['fonts' => ['arial.ttf', 'arialbd.ttf'], '--yes' => true])->assertFailed();
        Storage::disk('local')->assertExists('package/arialbd.ttf');

        $this->artisan('document-fonts:delete', ['fonts' => ['arial.ttf', 'arialbd.ttf'], '--yes' => true, '--force' => true])->assertSuccessful();

        $this->assertSame([], $this->manifest()['fonts']);
        $this->assertSame($before, $version->fresh()->getRawOriginal());
        $this->assertSame($bytes, Storage::disk('local')->get($version->source_path));
        $this->assertDatabaseCount('letter_template_audits', 0);
    }

    public function test_force_does_not_skip_confirmation_and_an_unused_variant_can_be_deleted(): void
    {
        $this->package();
        $this->source();
        $this->artisan('document-fonts:delete', ['fonts' => ['arial.ttf'], '--force' => true])
            ->expectsConfirmation('Hapus seluruh font yang dipilih?', 'no')->assertSuccessful();

        $this->artisan('document-fonts:delete', ['fonts' => ['arialbd.ttf', 'arialbd.ttf'], '--yes' => true, '--no-interaction' => true])
            ->expectsOutput('1 varian font paket dihapus.')->assertSuccessful();

        $this->assertSame(['arial.ttf'], array_column($this->manifest()['fonts'], 'file'));
    }

    public function test_reference_check_includes_published_and_archived_versions_and_inspection_failures(): void
    {
        $this->package();
        foreach (LetterTemplateVersionStatusEnum::cases() as $status) {
            $this->source($status);
        }
        $entries = app(ChangeBundledDocumentFonts::class)->select(['arial.ttf']);
        $usage = app(InspectDocumentFontReferences::class)->inspect($entries);
        $this->assertCount(3, $usage['used']);
        $version = $this->source();
        Storage::disk('local')->delete($version->source_path);

        $this->artisan('document-fonts:delete', ['fonts' => ['arialbd.ttf'], '--yes' => true])->assertFailed();
        $this->artisan('document-fonts:delete', ['fonts' => ['arialbd.ttf'], '--yes' => true, '--force' => true])->assertSuccessful();

        $this->assertSame(['arial.ttf'], array_column($this->manifest()['fonts'], 'file'));
    }

    public function test_missing_files_can_be_cleaned_and_the_removed_target_does_not_become_missing(): void
    {
        $this->package();
        $font = $this->activate('arial.ttf');
        $manifest = $this->manifest();
        $manifest['missing'][] = 'Arial · Regular';
        Storage::disk('local')->put('package/manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
        Storage::disk('local')->delete(['package/arial.ttf', $font->path]);

        $this->artisan('document-fonts:delete', ['fonts' => ['arial.ttf'], '--yes' => true])->assertSuccessful();

        $this->assertDatabaseCount('document_fonts', 0);
        $this->assertSame(['Cambria · Regular'], $this->manifest()['missing']);
        $this->assertSame(['arialbd.ttf'], array_column($this->manifest()['fonts'], 'file'));
    }

    public function test_upload_and_system_font_with_the_same_hash_are_preserved(): void
    {
        $this->package();
        $upload = $this->activate('arial.ttf', uploaded: true);
        $disk = Storage::disk('local');
        $entry = $this->entry('arial.ttf');
        $bundled = config('document-fonts.bundled_directory') . '/' . $entry['sha256'] . '.ttf';
        $disk->put($bundled, $disk->get('package/arial.ttf'));
        $disk->put('system/arial.ttf', $disk->get('package/arial.ttf'));

        $this->artisan('document-fonts:delete', ['fonts' => ['arial.ttf'], '--yes' => true])->assertSuccessful();

        $this->assertModelExists($upload);
        $disk->assertExists($upload->path);
        $disk->assertExists('system/arial.ttf');
        $disk->assertMissing($bundled);
        $disk->assertExists('package/arialbd.ttf');
    }

    public function test_database_failure_restores_all_files_rows_and_manifest(): void
    {
        $this->package();
        $regular = $this->activate('arial.ttf');
        $bold = $this->activate('arialbd.ttf');
        $before = Storage::disk('local')->get('package/manifest.json');
        $dispatcher = DocumentFont::getEventDispatcher();
        DocumentFont::setEventDispatcher(clone $dispatcher);
        DocumentFont::deleting(function (DocumentFont $font): void {
            if ($font->is_bold) {
                throw new RuntimeException('Simulasi kegagalan database.');
            }
        });
        try {
            $this->artisan('document-fonts:delete', ['fonts' => ['arial.ttf', 'arialbd.ttf'], '--yes' => true])->assertFailed();
        } finally {
            DocumentFont::setEventDispatcher($dispatcher);
        }

        $this->assertDatabaseCount('document_fonts', 2);
        $this->assertSame($before, Storage::disk('local')->get('package/manifest.json'));
        foreach ([$regular, $bold] as $font) {
            $this->assertSame($font->sha256, hash_file('sha256', Storage::disk('local')->path($font->path)));
        }
        Storage::disk('local')->assertExists('package/arial.ttf');
        Storage::disk('local')->assertExists('package/arialbd.ttf');
    }

    public function test_manifest_failure_rolls_back_the_entire_batch(): void
    {
        $this->package();
        $font = $this->activate('arial.ttf');
        $before = Storage::disk('local')->get('package/manifest.json');
        $files = Mockery::mock(Filesystem::class)->makePartial();
        $failed = false;
        $files->shouldReceive('move')->withArgs(fn(string $from, string $to): bool => basename($to) === 'manifest.json')->andReturnUsing(function (string $from, string $to) use (&$failed): bool {
            if (!$failed) {
                $failed = true;

                return false;
            }

            return (new Filesystem())->move($from, $to);
        });
        $this->app->instance(Filesystem::class, $files);

        $this->artisan('document-fonts:delete', ['fonts' => ['arial.ttf', 'arialbd.ttf'], '--yes' => true])->assertFailed();

        $this->assertTrue($failed);
        $this->assertModelExists($font);
        $this->assertSame($before, Storage::disk('local')->get('package/manifest.json'));
        Storage::disk('local')->assertExists($font->path);
        Storage::disk('local')->assertExists('package/arial.ttf');
        Storage::disk('local')->assertExists('package/arialbd.ttf');
    }

    public function test_cache_failure_restores_files_database_and_manifest(): void
    {
        $this->package();
        $font = $this->activate('arial.ttf');
        $before = Storage::disk('local')->get('package/manifest.json');
        $attempts = 0;
        Process::fake(['*fc-cache*' => function () use (&$attempts) {
            return Process::result(exitCode: ++$attempts === 1 ? 1 : 0);
        }]);

        $this->artisan('document-fonts:delete', ['fonts' => ['arial.ttf'], '--yes' => true])->assertFailed();

        $this->assertSame(2, $attempts);
        Process::assertRan(fn($process): bool => str_ends_with($process->command[0], 'fc-cache'));
        $this->assertModelExists($font);
        $this->assertSame($before, Storage::disk('local')->get('package/manifest.json'));
        Storage::disk('local')->assertExists($font->path);
        Storage::disk('local')->assertExists('package/arial.ttf');
    }

    public function test_sync_prunes_remote_bundle_rows_and_orphan_files_but_keeps_uploads(): void
    {
        $this->package();
        $regular = $this->activate('arial.ttf');
        $upload = $this->activate('arialbd.ttf', uploaded: true);
        $disk = Storage::disk('local');
        $orphan = config('document-fonts.bundled_directory') . '/' . str_repeat('f', 64) . '.ttf';
        $disk->put($orphan, 'orphan');
        $manifest = $this->manifest();
        $manifest['fonts'] = [$this->entry('arialbd.ttf')];
        $disk->put('package/manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
        $disk->delete('package/arial.ttf');

        $this->artisan('document-fonts:sync')->assertSuccessful();

        $this->assertModelMissing($regular);
        $this->assertModelExists($upload);
        $this->assertSame($upload->path, $upload->fresh()->path);
        $disk->assertExists($upload->path);
        $disk->assertMissing($regular->path);
        $disk->assertMissing($orphan);
    }

    public function test_sync_replaces_stale_bundled_variant_with_new_checksum(): void
    {
        $this->package();
        $entry = $this->entry('arial.ttf');
        $old = $this->activate('arial.ttf', contents: file_get_contents(resource_path('fonts/documents/arial.ttf')) . "\0");

        $this->artisan('document-fonts:sync')->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertDatabaseHas('document_fonts', ['sha256' => $entry['sha256'], 'family' => 'Arial', 'style' => 'Regular']);
        Storage::disk('local')->assertMissing($old->path);
    }

    public function test_invalid_package_does_not_prune_stale_bundled_fonts(): void
    {
        $this->package();
        $old = $this->activate('arial.ttf', contents: file_get_contents(resource_path('fonts/documents/arial.ttf')) . "\0");
        Storage::disk('local')->put('package/arialbd.ttf', 'changed');

        $this->artisan('document-fonts:sync')->assertFailed();

        $this->assertModelExists($old);
        Storage::disk('local')->assertExists($old->path);
    }

    public function test_sync_failure_after_pruning_restores_old_bundle_and_removes_new_files(): void
    {
        $this->package();
        $old = $this->activate('arial.ttf', contents: file_get_contents(resource_path('fonts/documents/arial.ttf')) . "\0");
        $entry = $this->entry('arial.ttf');
        $attempts = 0;
        Process::fake(['*fc-cache*' => function () use (&$attempts) {
            return Process::result(exitCode: ++$attempts === 1 ? 1 : 0);
        }]);

        $this->artisan('document-fonts:sync')->assertFailed();

        $this->assertModelExists($old);
        $this->assertDatabaseCount('document_fonts', 1);
        Storage::disk('local')->assertExists($old->path);
        Storage::disk('local')->assertMissing(config('document-fonts.bundled_directory') . '/' . $entry['sha256'] . '.ttf');
        Process::assertRan(fn($process): bool => str_ends_with($process->command[0], 'fc-cache'));
    }

    public function test_shared_package_lock_blocks_add_sync_and_delete_without_changes(): void
    {
        $this->package();
        $before = Storage::disk('local')->get('package/manifest.json');

        app(DocumentFontPackage::class)->synchronized(function (): void {
            $this->assertSame(1, Artisan::call('document-fonts:add', ['font' => resource_path('fonts/documents/arial.ttf'), '--no-sync' => true]));
            $this->assertSame(1, Artisan::call('document-fonts:sync'));
            $this->assertSame(1, Artisan::call('document-fonts:delete', ['fonts' => ['arial.ttf'], '--yes' => true]));
        });

        $this->assertSame($before, Storage::disk('local')->get('package/manifest.json'));
        Storage::disk('local')->assertExists('package/arial.ttf');
    }

    public function test_changed_selection_is_rejected_after_confirmation_snapshot(): void
    {
        $this->package();
        $manager = app(ChangeBundledDocumentFonts::class);
        $selected = $manager->select(['arial.ttf']);
        $manifest = $this->manifest();
        $manifest['fonts'][0]['webfont'] = !$manifest['fonts'][0]['webfont'];
        Storage::disk('local')->put('package/manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));

        $this->expectExceptionMessage('Paket font berubah sejak dipilih.');
        try {
            $manager->delete($selected, false);
        } finally {
            Storage::disk('local')->assertExists('package/arial.ttf');
        }
    }

    public function test_failure_removing_second_file_restores_the_first_selected_font(): void
    {
        $this->package();
        $regular = $this->activate('arial.ttf');
        $before = Storage::disk('local')->get('package/manifest.json');
        $blocked = Storage::disk('local')->path('package/arialbd.ttf');
        $files = Mockery::mock(Filesystem::class)->makePartial();
        $files->shouldReceive('delete')->with($blocked)->once()->andReturn(false);
        $this->app->instance(Filesystem::class, $files);

        $this->artisan('document-fonts:delete', ['fonts' => ['arial.ttf', 'arialbd.ttf'], '--yes' => true])->assertFailed();

        $this->assertModelExists($regular);
        $this->assertSame($before, Storage::disk('local')->get('package/manifest.json'));
        foreach (['package/arial.ttf', 'package/arialbd.ttf', $regular->path] as $path) {
            Storage::disk('local')->assertExists($path);
        }
    }

    public function test_persistent_cache_failure_reports_failed_recovery_and_preserves_data(): void
    {
        $this->package();
        $font = $this->activate('arial.ttf');
        Process::fake(['*fc-cache*' => Process::result(exitCode: 1)]);

        $status = Artisan::call('document-fonts:delete', ['fonts' => ['arial.ttf'], '--yes' => true]);

        $this->assertSame(1, $status);
        $this->assertStringContainsString('Pemulihan cache/manifest gagal', Artisan::output());
        $this->assertModelExists($font);
        Storage::disk('local')->assertExists($font->path);
        Storage::disk('local')->assertExists('package/arial.ttf');
        $this->assertCount(2, $this->manifest()['fonts']);
        Process::assertRan(fn($process): bool => str_ends_with($process->command[0], 'fc-cache'));
    }

    public function test_sync_conflicting_upload_does_not_prune_an_unrelated_stale_bundle(): void
    {
        $this->package();
        $upload = $this->activate('arial.ttf', uploaded: true, contents: file_get_contents(resource_path('fonts/documents/arial.ttf')) . "\0");
        $old = $this->activate('arialbd.ttf');
        $manifest = $this->manifest();
        $manifest['fonts'] = [$this->entry('arial.ttf')];
        Storage::disk('local')->put('package/manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));

        $this->artisan('document-fonts:sync')->assertFailed();

        $this->assertModelExists($upload);
        $this->assertModelExists($old);
        Storage::disk('local')->assertExists($upload->path);
        Storage::disk('local')->assertExists($old->path);
    }

    public function test_reference_check_matches_font_aliases_without_case_sensitivity(): void
    {
        $this->package();
        $version = $this->source(family: 'ALIAS FONT');
        $registry = $this->partialMock(DocumentFontRegistry::class);
        $registry->shouldReceive('scan')->once()->andReturn(['aliases' => ['Arial', 'Alias Font']]);

        $usage = app(InspectDocumentFontReferences::class)->inspect([$this->entry('arial.ttf')]);

        $this->assertSame([['Arial', 'Regular', $version->template->name, '1']], $usage['used']);
    }

    public function test_symlink_in_package_cannot_delete_the_file_it_points_to(): void
    {
        $this->package();
        $disk = Storage::disk('local');
        $bytes = $disk->get('package/arial.ttf');
        $disk->put('outside-package.ttf', $bytes);
        $disk->delete('package/arial.ttf');
        symlink($disk->path('outside-package.ttf'), $disk->path('package/arial.ttf'));
        $before = $disk->get('package/manifest.json');

        $this->artisan('document-fonts:delete', ['fonts' => ['arial.ttf'], '--yes' => true])->assertFailed();

        $this->assertSame($before, $disk->get('package/manifest.json'));
        $this->assertSame($bytes, $disk->get('outside-package.ttf'));
        $this->assertTrue(is_link($disk->path('package/arial.ttf')));
    }

    public function test_source_changes_during_sync_cannot_install_unexpected_bytes_and_pruning_is_rolled_back(): void
    {
        $this->package();
        $old = $this->activate('arial.ttf', contents: file_get_contents(resource_path('fonts/documents/arial.ttf')) . "\0old");
        $expected = $this->entry('arial.ttf');
        $changed = false;
        Process::fake(['*fc-scan*' => function ($process) use (&$changed) {
            $path = $process->command[array_key_last($process->command)];
            $bold = basename($path) === 'arialbd.ttf';
            if (!$bold && !$changed) {
                file_put_contents($path, file_get_contents($path) . "\0changed");
                $changed = true;
            }

            return Process::result(output: "Arial\t" . ($bold ? 'Bold' : 'Regular') . "\t" . ($bold ? '200' : '80') . "\t0\t" . $path . "\t0\nFalse\n");
        }]);

        $this->artisan('document-fonts:sync')->expectsOutput('Font paket berubah selama sinkronisasi: arial.ttf')->assertFailed();

        $this->assertTrue($changed);
        $this->assertModelExists($old);
        $this->assertDatabaseCount('document_fonts', 1);
        Storage::disk('local')->assertExists($old->path);
        Storage::disk('local')->assertMissing(config('document-fonts.bundled_directory') . '/' . $expected['sha256'] . '.ttf');
        Process::assertRan(fn($process): bool => str_ends_with($process->command[0], 'fc-scan'));
    }

    private function package(): void
    {
        Storage::fake('local');
        $disk = Storage::disk('local');
        $disk->makeDirectory('system');
        $disk->put('system.conf', '<fontconfig><dir>' . htmlspecialchars($disk->path('system'), ENT_XML1) . '</dir></fontconfig>');
        config(['document-fonts.system_config' => $disk->path('system.conf'), 'document-fonts.package_directory' => $disk->path('package')]);
        $shipped = json_decode(file_get_contents(resource_path('fonts/documents/manifest.json')), true)['fonts'];
        $entries = [];
        foreach (['arial.ttf', 'arialbd.ttf'] as $file) {
            $entries[] = collect($shipped)->firstWhere('file', $file);
            $disk->put('package/' . $file, file_get_contents(resource_path('fonts/documents/' . $file)));
        }
        $disk->put('package/manifest.json', json_encode(['schema_version' => 1, 'missing' => ['Cambria · Regular'], 'fonts' => $entries], JSON_THROW_ON_ERROR));
    }

    private function manifest(): array
    {
        return json_decode(Storage::disk('local')->get('package/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    private function entry(string $file): array
    {
        return collect($this->manifest()['fonts'])->firstWhere('file', $file);
    }

    private function activate(string $file, bool $uploaded = false, ?string $contents = null): DocumentFont
    {
        $entry = $this->entry($file);
        $contents ??= Storage::disk('local')->get('package/' . $file);
        $hash = hash('sha256', $contents);
        $path = config($uploaded ? 'document-fonts.directory' : 'document-fonts.bundled_directory') . '/' . $hash . '.ttf';
        Storage::disk('local')->put($path, $contents);

        return DocumentFont::factory()->create(['family' => $entry['family'], 'style' => $entry['style'], 'face_key' => app(DocumentFontRegistry::class)->faceKey($entry['family'], $entry['style']), 'is_bold' => $entry['style'] === 'Bold', 'is_italic' => false, 'sha256' => $hash, 'status' => 'ready', 'disk' => 'local', 'path' => $path, 'extension' => 'ttf', 'size' => strlen($contents), 'uploaded_by' => null]);
    }

    private function source(LetterTemplateVersionStatusEnum $status = LetterTemplateVersionStatusEnum::DRAFT, string $family = 'Arial'): LetterTemplateVersion
    {
        $version = LetterTemplateVersion::factory()->create(['status' => $status]);
        $document = new PhpWord();
        $document->setDefaultFontName($family);
        $document->addSection()->addText('Font yang dipakai', ['name' => $family]);
        $disk = Storage::disk('local');
        $disk->makeDirectory('sources');
        $path = 'sources/' . $version->uuid . '.docx';
        IOFactory::createWriter($document, 'Word2007')->save($disk->path($path));
        $version->update(['source_disk' => 'local', 'source_path' => $path, 'source_sha256' => hash_file('sha256', $disk->path($path))]);

        return $version;
    }
}
