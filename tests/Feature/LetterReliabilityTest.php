<?php

namespace Tests\Feature;

use App\Enums\LetterOperationStatusEnum as Status;
use App\Models\LetterArtifact;
use App\Models\LetterVersion;
use App\Services\Letter\CleanupLetterFiles;
use App\Services\Letter\LetterHealth;
use App\Services\LetterNumber\RetireLetterNumberCounters;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class LetterReliabilityTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_cleanup_removes_expired_and_old_orphan_files_but_keeps_inputs_metadata_and_official_history(): void
    {
        $this->freezeTime();
        Storage::fake('local');
        $disk = Storage::disk('local');
        $expired = LetterArtifact::factory()->create(['expires_at' => now()->subSecond()]);
        $expired->operation->update(['status' => Status::SUCCEEDED]);
        $disk->put($expired->path, 'expired');
        $version = LetterVersion::factory()->create();
        $official = LetterArtifact::factory()->create(['letter_id' => $version->letter_id, 'letter_draft_id' => null, 'letter_version_id' => $version->id, 'letter_operation_id' => $version->letter_operation_id, 'expires_at' => now()->subYear(), 'path' => config('letters.paths.official') . '/historical.docx']);
        $version->operation->update(['status' => Status::SUCCEEDED]);
        $version->update(['word_artifact_id' => $official->id]);
        $disk->put($official->path, 'official');
        $disk->put('letters/staging/orphan/old.docx', 'orphan');
        touch($disk->path('letters/staging/orphan/old.docx'), now()->subHours(25)->timestamp);
        $disk->put('letters/staging/recent/new.docx', 'recent');
        $disk->put('letter-templates/sources/source.docx', 'source');
        $dry = app(CleanupLetterFiles::class)->run(true);
        $this->assertSame(2, $dry['eligible']);
        $this->assertTrue($disk->exists($expired->path));
        $result = app(CleanupLetterFiles::class)->run();
        $this->assertSame(2, $result['deleted']);
        $this->assertSame(0, $result['failed']);
        $this->assertFalse($disk->exists($expired->path));
        $this->assertFalse($disk->exists('letters/staging/orphan/old.docx'));
        $this->assertTrue($disk->exists($official->path));
        $this->assertTrue($disk->exists('letters/staging/recent/new.docx'));
        $this->assertTrue($disk->exists('letter-templates/sources/source.docx'));
        $this->assertModelExists($expired);
        $this->assertModelExists($expired->draft);
        $this->assertModelExists($version);
    }

    public function test_cleanup_keeps_expired_dependencies_and_old_staging_for_active_operations_until_they_are_terminal(): void
    {
        $this->freezeTime();
        Storage::fake('local');
        $disk = Storage::disk('local');
        $artifact = LetterArtifact::factory()->create(['expires_at' => now()->subHour()]);
        $operation = $artifact->operation;
        $staging = config('letters.paths.staging') . '/' . $operation->uuid . '/' . Str::uuid() . '/input.json';
        $disk->put($artifact->path, 'input');
        $disk->put($staging, 'staged');
        touch($disk->path($staging), now()->subHours(25)->timestamp);
        $this->assertSame(0, app(CleanupLetterFiles::class)->run()['deleted']);
        $operation->update(['status' => Status::FAILED]);
        $this->assertSame(2, app(CleanupLetterFiles::class)->run()['deleted']);
        $this->assertModelExists($artifact);
    }

    public function test_cleanup_rejects_overlapping_or_protected_directories_before_deleting_files(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('letter-templates/sources/keep.docx', 'source');
        config(['letters.paths.previews' => config('letter-templates.paths.sources')]);
        try {
            app(CleanupLetterFiles::class)->run();
            $this->fail('Expected unsafe root guard.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('tumpang tindih', $exception->getMessage());
        }
        $this->assertTrue(Storage::disk('local')->exists('letter-templates/sources/keep.docx'));
    }

    public function test_health_probes_notifications_and_rejects_stale_worker_heartbeat_and_unretired_counters(): void
    {
        $this->freezeTime();
        Storage::fake('local');
        config(['letters.health.cache_store' => 'array']);
        $health = app(LetterHealth::class);
        $checks = $health->check();
        $this->assertTrue($checks['postgres']['ok']);
        $this->assertFalse($checks['worker']['ok']);
        $this->assertFalse($checks['scheduler']['ok']);
        $this->assertFalse($checks['counters']['ok']);
        $this->assertTrue($checks['storage']['ok']);
        Event::dispatch(new Looping('database', 'default'));
        $this->assertNull(Cache::store('array')->get('letters.health.worker'));
        Event::dispatch(new Looping('letters', 'letters'));
        $this->artisan('letters:recover-operations')->assertSuccessful();
        app(RetireLetterNumberCounters::class)->retire();
        $checks = $health->check();
        $this->assertTrue($checks['worker']['ok']);
        $this->assertTrue($checks['scheduler']['ok']);
        $this->assertTrue($checks['counters']['ok']);
        Cache::store('array')->put('letters.health.worker', now()->subSeconds(121)->timestamp, 600);
        $this->assertFalse($health->check()['worker']['ok']);
    }
}
