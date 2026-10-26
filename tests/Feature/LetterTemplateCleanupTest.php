<?php

namespace Tests\Feature;

use App\Enums\LetterTemplateVersionStatusEnum;
use App\Models\MasterData\LetterTemplateVersion;
use App\Services\LetterTemplate\CleanupLetterTemplateFiles;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LetterTemplateCleanupTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[DataProvider('versionStatuses')]
    public function test_expired_preview_is_removed_without_changing_source_metadata_or_version_status(LetterTemplateVersionStatusEnum $status): void
    {
        $this->freezeTime();
        $disk = Storage::fake('local');
        $version = LetterTemplateVersion::factory()->create(['status' => $status, 'source_disk' => 'local', 'source_path' => 'letter-templates/sources/history.docx', 'test_disk' => 'local', 'test_path' => 'letter-templates/previews/expired.docx', 'tested_at' => now()->subDays(2), 'test_expires_at' => now(), 'visually_confirmed_at' => now()->subDay()]);
        $disk->put($version->source_path, 'Sumber historis');
        $disk->put($version->test_path, 'Hasil uji lama');
        if ($status === LetterTemplateVersionStatusEnum::PUBLISHED) {
            $version->template->update(['active_version_id' => $version->id]);
        }
        $before = $version->fresh()->getAttributes();
        $this->artisan('letter-templates:cleanup')->assertExitCode(0);
        $disk->assertMissing($version->test_path);
        $disk->assertExists($version->source_path);
        $this->assertSame($before, $version->fresh()->getAttributes());
        $this->assertSame($status === LetterTemplateVersionStatusEnum::PUBLISHED ? $version->id : null, $version->template->fresh()->active_version_id);
        $this->assertDatabaseCount('letter_template_audits', 0);
        $this->artisan('letter-templates:cleanup')->assertExitCode(0);
    }

    public static function versionStatuses(): array
    {
        return array_map(fn($status): array => [$status], LetterTemplateVersionStatusEnum::cases());
    }

    public function test_only_old_orphans_and_temporary_files_are_removed(): void
    {
        $this->freezeTime();
        $disk = Storage::fake('local');
        foreach (['letter-templates/previews/old.docx', 'letter-templates/temporary/old', 'letter-templates/previews/new.docx', 'letter-templates/temporary/new', 'letter-templates/sources/orphan.docx', 'other/old.docx'] as $path) {
            $disk->put($path, 'Contoh');
            if (!str_contains($path, '/new')) {
                touch($disk->path($path), now()->subDays(2)->timestamp);
            }
        }
        $this->artisan('letter-templates:cleanup')->assertExitCode(0);
        $disk->assertMissing(['letter-templates/previews/old.docx', 'letter-templates/temporary/old']);
        $disk->assertExists(['letter-templates/previews/new.docx', 'letter-templates/temporary/new', 'letter-templates/sources/orphan.docx', 'other/old.docx']);
    }

    public function test_current_shared_and_legacy_references_and_misplaced_sources_are_preserved(): void
    {
        $this->freezeTime();
        $disk = Storage::fake('local');
        foreach (['current', 'legacy', 'shared', 'source'] as $name) {
            $path = "letter-templates/previews/{$name}.docx";
            $disk->put($path, 'Tetap disimpan');
            touch($disk->path($path), now()->subDays(2)->timestamp);
            if ($name === 'source') {
                LetterTemplateVersion::factory()->create(['source_disk' => 'local', 'source_path' => $path]);
            } else {
                LetterTemplateVersion::factory()->create(['test_disk' => 'local', 'test_path' => $path, 'test_expires_at' => $name === 'legacy' ? null : now()->addHour()]);
                if ($name === 'shared') {
                    LetterTemplateVersion::factory()->create(['test_disk' => 'local', 'test_path' => $path, 'test_expires_at' => now()->subHour()]);
                }
            }
        }
        $this->assertSame(['eligible' => 0, 'deleted' => 0, 'kept' => 4, 'failed' => 0], app(CleanupLetterTemplateFiles::class)->run());
        $this->assertCount(4, $disk->allFiles('letter-templates/previews'));
    }

    public function test_dry_run_reports_eligible_files_without_deleting_them(): void
    {
        $this->freezeTime();
        $disk = Storage::fake('local');
        $path = 'letter-templates/temporary/old';
        $disk->put($path, 'Contoh');
        touch($disk->path($path), now()->subHours(2)->timestamp);
        $this->artisan('letter-templates:cleanup', ['--dry-run' => true])->expectsOutputToContain('Layak dihapus: 1; dihapus: 0')->assertExitCode(0);
        $disk->assertExists($path);
    }

    public function test_failed_file_deletion_is_reported_and_the_command_returns_failure(): void
    {
        Exceptions::fake();
        $disk = Storage::fake('local');
        $path = 'letter-templates/previews/failed.docx';
        $disk->put($path, 'Hasil uji');
        LetterTemplateVersion::factory()->create(['test_disk' => 'local', 'test_path' => $path, 'test_expires_at' => now()->subHour()]);
        $failing = \Mockery::mock($disk);
        $failing->shouldReceive('delete')->with($path)->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->andReturn($failing);
        $this->artisan('letter-templates:cleanup')->assertExitCode(1);
        $disk->assertExists($path);
        Exceptions::assertReported(\RuntimeException::class);
    }

    public function test_overlapping_cleanup_roots_fail_without_deleting_sources(): void
    {
        Exceptions::fake();
        $disk = Storage::fake('local');
        $disk->put('letter-templates/sources/source.docx', 'Sumber');
        config(['letter-templates.paths.previews' => 'letter-templates']);
        $this->artisan('letter-templates:cleanup')->assertExitCode(1);
        $disk->assertExists('letter-templates/sources/source.docx');
        Exceptions::assertReported(\RuntimeException::class);
    }
}
