<?php

namespace Tests\Feature;

use App\Enums\LetterOperationStatusEnum as OperationStatus;
use App\Enums\LetterStatusEnum;
use App\Enums\LetterTemplateVersionStatusEnum;
use App\Jobs\GenerateLetterPreview;
use App\Jobs\IssueLetter;
use App\Models\Letter;
use App\Models\LetterArtifact;
use App\Models\LetterAudit;
use App\Models\LetterOperation;
use App\Models\MasterData\LetterTemplateVersion;
use App\Models\WorkCalendarYear;
use App\Services\Letter\CleanupLetterFiles;
use App\Services\Letter\LetterPdfState;
use App\Services\Letter\ManageLetterLifecycle;
use App\Services\Letter\RunLetterIssuance;
use App\Services\Letter\RunLetterPreview;
use App\Services\LetterNumber\RetireLetterNumberCounters;
use App\Services\WorkCalendar\CalendarFailure;
use App\Services\WorkCalendar\ManageWorkCalendar;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\CreatesPdfArtifact;
use Tests\TestCase;
use Throwable;

#[Group('letter-concurrency')]
class LetterConcurrencyTest extends TestCase
{
    use CreatesPdfArtifact;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('LETTER_DATABASE_TESTS') !== '1') {
            $this->markTestSkipped('Run explicitly with phpunit.letters.xml on a disposable PostgreSQL database.');
        }
        if (!app()->environment('testing') || DB::getDriverName() !== 'pgsql' || preg_match('/\Atensura_test_letters_lifecycle_[a-z0-9_]+\z/D', DB::connection()->getDatabaseName()) !== 1 || !function_exists('pcntl_fork') || !function_exists('posix_kill')) {
            throw new RuntimeException('Letter concurrency requires an isolated PostgreSQL database, pcntl, and posix.');
        }
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        app(RetireLetterNumberCounters::class)->retire();
        config(['letters.health.cache_store' => 'array', 'letters.disk' => 'letter-concurrency', 'document-fonts.disk' => 'letter-concurrency']);
    }

    public function test_competing_publications_share_the_period_and_commit_distinct_numbers(): void
    {
        $first = $this->draft();
        $second = $this->draft(false, $first->template->activeVersion);
        $a = $this->prepare($first);
        $b = $this->prepare($second);
        $result = $this->whileLocked(
            fn() => app(RunLetterIssuance::class)->run($a),
            function () use ($b): array {
                app(RunLetterIssuance::class)->run($b);

                return ['status' => LetterOperation::where('uuid', $b)->sole()->status->value];
            },
        );
        $this->assertSame(OperationStatus::SUCCEEDED->value, $result['status']);
        $this->assertSame('445/01.01/PKM-KMJ/X/2026', $first->fresh()->official_number);
        $this->assertSame('445/01.02/PKM-KMJ/X/2026', $second->fresh()->official_number);
        $this->assertDatabaseCount('letter_versions', 2);
        $this->assertDatabaseCount('letter_number_assignments', 2);
    }

    public function test_duplicate_workers_and_delivery_after_commit_have_one_effect(): void
    {
        $letter = $this->draft();
        $uuid = $this->prepare($letter);
        $this->whileLocked(
            fn() => app(RunLetterIssuance::class)->run($uuid),
            function () use ($uuid): array {
                app(RunLetterIssuance::class)->run($uuid);

                return ['done' => true];
            },
        );
        app(RunLetterIssuance::class)->run($uuid);
        $this->assertDatabaseCount('letter_versions', 1);
        $this->assertDatabaseCount('letter_number_assignments', 1);
        $this->assertSame(1, LetterOperation::where('uuid', $uuid)->sole()->attempts);
    }

    public function test_cancellation_fences_a_waiting_revision_worker(): void
    {
        $letter = $this->draft();
        $this->publish($letter);
        $this->action($letter->fresh(), 'revise');
        $this->save($letter->fresh(), ['reason' => 'Koreksi']);
        $uuid = $this->prepare($letter->fresh());
        $this->whileLocked(
            fn() => $this->action($letter->fresh(), 'cancel', ['reason' => 'Tidak berlaku']),
            function () use ($uuid): array {
                app(RunLetterIssuance::class)->run($uuid);

                return ['done' => true];
            },
        );
        $this->assertSame(LetterStatusEnum::CANCELLED, $letter->fresh()->status);
        $this->assertSame(OperationStatus::STALE, LetterOperation::where('uuid', $uuid)->sole()->status);
        $this->assertDatabaseCount('letter_versions', 1);
        $this->assertSame(0, $letter->assignments()->whereNull('released_at')->count());
    }

    public function test_recovery_cannot_overwrite_a_competing_manual_claim(): void
    {
        $letter = $this->draft();
        $this->publish($letter);
        $this->action($letter->fresh(), 'cancel', ['reason' => 'Batal']);
        $this->action($letter->fresh(), 'recover');
        $this->save($letter->fresh(), ['reason' => 'Pulih']);
        $recovery = $this->prepare($letter->fresh());
        $other = $this->draft(false, $letter->template->activeVersion);
        $this->save($other, ['numbering_mode' => 2, 'page_number' => 1, 'sequence_number' => 1]);
        $manual = $this->prepare($other->fresh());
        $this->whileLocked(
            fn() => app(RunLetterIssuance::class)->run($manual),
            function () use ($recovery): array {
                app(RunLetterIssuance::class)->run($recovery);

                return ['done' => true];
            },
        );
        $this->assertSame(OperationStatus::FAILED, LetterOperation::where('uuid', $recovery)->sole()->status);
        $this->assertStringContainsString('445/01.02', LetterOperation::where('uuid', $recovery)->sole()->message);
        $this->assertSame(LetterStatusEnum::CANCELLED, $letter->fresh()->status);
        $this->assertSame(LetterStatusEnum::PUBLISHED, $other->fresh()->status);
        $this->assertDatabaseCount('letter_versions', 2);
        $this->assertSame(1, $other->assignments()->whereNull('released_at')->count());
    }

    public function test_cleanup_waits_for_a_worker_and_preserves_its_committed_official_file(): void
    {
        $letter = $this->draft();
        $uuid = $this->prepare($letter);
        $artifact = $letter->artifacts()->where('kind', 'docx')->sole();
        $artifact->update(['expires_at' => now()->subMinute()]);
        $result = $this->whileLocked(
            function () use ($artifact, $uuid): void {
                $artifact->update(['expires_at' => now()->addDay()]);
                app(RunLetterIssuance::class)->run($uuid);
            },
            fn(): array => app(CleanupLetterFiles::class)->run(),
        );
        $this->assertSame(0, $result['deleted']);
        Storage::disk('letter-concurrency')->assertExists($letter->fresh()->currentVersion->wordArtifact->path);
        Storage::disk('letter-concurrency')->assertExists($artifact->path);
    }

    public function test_template_change_committed_before_a_waiting_worker_consumes_no_number(): void
    {
        $letter = $this->draft();
        $uuid = $this->prepare($letter);
        $this->whileLocked(
            function () use ($letter): void {
                $template = $letter->template()->lockForUpdate()->firstOrFail();
                $template->update(['is_active' => false]);
            },
            function () use ($uuid): array {
                app(RunLetterIssuance::class)->run($uuid);

                return ['done' => true];
            },
        );
        $this->assertSame(OperationStatus::STALE, LetterOperation::where('uuid', $uuid)->sole()->status);
        $this->assertDatabaseCount('letter_versions', 0);
        $this->assertDatabaseCount('letter_number_assignments', 0);
    }

    public function test_process_death_before_publication_is_recovered_without_consuming_a_number(): void
    {
        $letter = $this->draft();
        $uuid = $this->prepare($letter);
        DB::purge();
        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new RuntimeException('Cannot start crash test process.');
        }
        if ($pid === 0) {
            DB::purge();
            Process::fake(['*letters:render-preview*' => function () {
                posix_kill(getmypid(), SIGKILL);
                throw new RuntimeException('Unreachable');
            }]);
            app(RunLetterIssuance::class)->run($uuid);
            exit(1);
        }
        pcntl_waitpid($pid, $status);
        DB::purge();
        $this->assertTrue(pcntl_wifsignaled($status));
        $this->assertSame(SIGKILL, pcntl_wtermsig($status));
        $this->assertSame(OperationStatus::RUNNING, LetterOperation::where('uuid', $uuid)->sole()->status);
        $this->assertDatabaseCount('letter_versions', 0);
        $this->assertDatabaseCount('letter_number_assignments', 0);
        $this->travel(config('letters.lease_seconds') + 1)->seconds();
        $this->artisan('letters:recover-operations')->assertExitCode(0);
        Bus::assertDispatched(IssueLetter::class, fn($job) => $job->operationUuid === $uuid);
        app(RunLetterIssuance::class)->run($uuid);
        app(RunLetterIssuance::class)->run($uuid);
        $this->assertSame(OperationStatus::SUCCEEDED, LetterOperation::where('uuid', $uuid)->sole()->status);
        $this->assertDatabaseCount('letter_versions', 1);
        $this->assertSame('445/01.01/PKM-KMJ/X/2026', $letter->fresh()->official_number);
    }

    public function test_death_after_file_move_rolls_back_metadata_and_recovery_cleans_only_the_orphan(): void
    {
        $letter = $this->draft();
        $uuid = $this->prepare($letter);
        $this->crash(function () use ($uuid): void {
            LetterAudit::creating(function ($audit): void {
                if ($audit->event === 'letter.issued') {
                    posix_kill(getmypid(), SIGKILL);
                }
            });
            app(RunLetterIssuance::class)->run($uuid);
        });
        $this->assertDatabaseCount('letter_versions', 0);
        $this->assertDatabaseCount('letter_number_assignments', 0);
        $files = Storage::disk('letter-concurrency')->allFiles(config('letters.paths.official'));
        $this->assertCount(1, $files);
        $this->travel(config('letters.lease_seconds') + 1)->seconds();
        $this->artisan('letters:recover-operations')->assertExitCode(0);
        app(RunLetterIssuance::class)->run($uuid);
        $this->assertSame('445/01.01/PKM-KMJ/X/2026', $letter->fresh()->official_number);
        touch(Storage::disk('letter-concurrency')->path($files[0]), now()->subHours(25)->timestamp);
        $this->assertSame(1, app(CleanupLetterFiles::class)->run()['deleted']);
        Storage::disk('letter-concurrency')->assertExists($letter->fresh()->currentVersion->wordArtifact->path);
        $this->assertDatabaseCount('letter_versions', 1);
    }

    public function test_death_after_commit_and_redelivery_preserve_the_same_version_and_number(): void
    {
        $letter = $this->draft();
        $uuid = $this->prepare($letter);
        $this->crash(function () use ($uuid): void {
            app(RunLetterIssuance::class)->run($uuid);
            posix_kill(getmypid(), SIGKILL);
        });
        app(RunLetterIssuance::class)->run($uuid);
        $this->assertSame(OperationStatus::SUCCEEDED, LetterOperation::where('uuid', $uuid)->sole()->status);
        $this->assertDatabaseCount('letter_versions', 1);
        $this->assertDatabaseCount('letter_number_assignments', 1);
        $this->assertSame(1, LetterOperation::where('uuid', $uuid)->sole()->attempts);
        Storage::disk('letter-concurrency')->assertExists($letter->fresh()->currentVersion->wordArtifact->path);
    }

    public function test_competing_calendar_events_reject_the_stale_revision_atomically(): void
    {
        $actor = UserFactory::new()->create();
        WorkCalendarYear::factory()->create(['year' => 2026]);
        $input = ['title' => 'Libur', 'kind' => 'holiday', 'all_day' => true, 'start_date' => '2026-11-02', 'end_date' => '2026-11-02', 'calendar_revisions' => ['2026' => 1]];
        $result = $this->whileLocked(
            fn() => app(ManageWorkCalendar::class)->event($input, $actor),
            function () use ($input, $actor): array {
                try {
                    app(ManageWorkCalendar::class)->event($input, $actor);
                } catch (CalendarFailure $exception) {
                    return ['code' => $exception->reason];
                }

                return ['code' => 'unexpected_success'];
            },
        );
        $this->assertSame('revision_conflict', $result['code']);
        $this->assertDatabaseCount('calendar_events', 1);
        $this->assertDatabaseCount('work_calendar_exceptions', 1);
    }

    private function crash(callable $work): void
    {
        DB::purge();
        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new RuntimeException('Cannot start crash test process.');
        }
        if ($pid === 0) {
            pcntl_alarm(15);
            DB::purge();
            $work();
            exit(1);
        }
        pcntl_waitpid($pid, $status);
        DB::purge();
        $this->assertTrue(pcntl_wifsignaled($status));
        $this->assertSame(SIGKILL, pcntl_wtermsig($status));
    }

    private function draft(bool $reset = true, ?LetterTemplateVersion $template = null): Letter
    {
        if ($reset) {
            Storage::fake('letter-concurrency');
            Bus::fake([GenerateLetterPreview::class, IssueLetter::class]);
            $this->travelTo(CarbonImmutable::parse('2026-10-26 12:00:00'));
            $this->actingAs(UserFactory::new()->create());
        }
        $version = $template ?? LetterTemplateVersion::factory()->create(['status' => LetterTemplateVersionStatusEnum::PUBLISHED, 'validation_result' => ['valid' => true, 'placeholders' => ['nomor_surat', 'tanggal_surat']]]);
        $version->letterNumber->update(['classification_code' => '445']);
        $version->template->update(['is_active' => true, 'active_version_id' => $version->id]);
        if ($template === null) {
            $disk = Storage::disk('letter-concurrency');
            $disk->makeDirectory('tests');
            $doc = new PhpWord();
            $doc->addSection()->addText('Surat ${nomor_surat} ${tanggal_surat}');
            IOFactory::createWriter($doc, 'Word2007')->save($disk->path('tests/source.docx'));
            $version->update(['source_disk' => 'letter-concurrency', 'source_path' => 'tests/source.docx', 'source_sha256' => hash_file('sha256', $disk->path('tests/source.docx'))]);
        }
        $this->post(route('ltr.store'), ['template' => $version->template->uuid, 'template_version' => $version->uuid, 'subject' => 'Surat awal', 'letter_date' => '2026-10-26', 'numbering_mode' => 1, 'number_year' => 2026, 'number_month' => 10, 'values' => [], 'signers' => []])->assertSessionHasNoErrors();

        return Letter::query()->latest('id')->firstOrFail();
    }

    private function action(Letter $letter, string $action, array $extra = []): TestResponse
    {
        $data = app(ManageLetterLifecycle::class)->detail($letter);
        $response = $this->post(route('ltr.' . $action, $letter->uuid), ['state_token' => $data['state_token'], 'base_version' => $data['base_version'], 'confirmed' => true, ...$extra]);
        if (!array_key_exists('reason', $extra) || $extra['reason'] !== '') {
            $response->assertSessionHasNoErrors();
        }

        return $response;
    }

    private function save(Letter $letter, array $extra = []): TestResponse
    {
        $draft = $letter->draft;

        return $this->put(route('ltr.update', $letter->uuid), ['template_version' => $draft->templateVersion->uuid, 'draft_id' => $draft->uuid, 'base_version' => $draft->baseVersion?->uuid, 'revision' => $draft->revision, 'subject' => $draft->subject ?? $letter->subject, 'reason' => $draft->reason, 'letter_date' => $draft->letter_date->toDateString(), 'numbering_mode' => $draft->numbering_mode->value, 'number_year' => $draft->number_year, 'number_month' => $draft->number_month, 'page_number' => $draft->page_number, 'sequence_number' => $draft->sequence_number, 'values' => $draft->form_data['values'], 'signers' => $draft->form_data['signers'], ...$extra]);
    }

    private function prepare(Letter $letter): string
    {
        Process::fake(['*letters:render-preview*' => fn($process) => Process::result(exitCode: Artisan::call('letters:render-preview', ['directory' => $process->command[3]]))]);
        $draft = $letter->draft;
        $uuid = $this->postJson(route('ltr.previews.store', $letter->uuid), ['draft_id' => $draft->uuid, 'base_version' => $draft->baseVersion?->uuid, 'revision' => $draft->revision, 'idempotency_key' => (string) Str::uuid()])->assertAccepted()->json('operation.id');
        app(RunLetterPreview::class)->run($uuid);
        $artifact = LetterArtifact::query()->where('letter_draft_id', $draft->id)->where('kind', 'docx')->latest('id')->firstOrFail();
        $artifact = $this->pdfArtifact($artifact);
        $check = ['draft_id' => $draft->uuid, 'base_version' => $draft->baseVersion?->uuid, 'revision' => $draft->revision, 'fingerprint' => app(LetterPdfState::class)->evidence($artifact), 'preview_kind' => 'pdf', 'confirmed' => true];
        $this->postJson(route('ltr.previews.rendered', [$letter->uuid, $artifact->uuid]), $check)->assertOk();
        $this->postJson(route('ltr.previews.confirm', [$letter->uuid, $artifact->uuid]), $check)->assertOk();
        $response = $this->postJson(route('ltr.issue', $letter->uuid), ['draft_id' => $draft->uuid, 'base_version' => $draft->baseVersion?->uuid, 'revision' => $draft->revision, 'artifact' => $artifact->uuid, 'fingerprint' => $check['fingerprint'], 'publication_month' => '2026-10', 'keep_period' => true, 'confirmed' => true, 'idempotency_key' => (string) Str::uuid()]);

        return $response->assertAccepted()->json('operation.id');
    }

    private function publish(Letter $letter): void
    {
        app(RunLetterIssuance::class)->run($this->prepare($letter));
        $this->assertSame(LetterStatusEnum::PUBLISHED, $letter->fresh()->status);
    }

    private function whileLocked(callable $winner, callable $contender): array
    {
        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        if ($sockets === false) {
            throw new RuntimeException('Cannot create the concurrency barrier.');
        }
        DB::purge();
        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new RuntimeException('Cannot start the competing process.');
        }
        if ($pid === 0) {
            fclose($sockets[0]);
            pcntl_alarm(15);
            stream_set_timeout($sockets[1], 10);
            try {
                DB::purge();
                DB::connection()->getPdo();
                fwrite($sockets[1], "ready\n");
                if (trim((string) fgets($sockets[1])) !== 'go') {
                    throw new RuntimeException('Missing concurrency release signal.');
                }
                $announced = false;
                DB::connection()->beforeExecuting(function (string $query, array $bindings, Connection $connection) use (&$announced, $sockets): void {
                    if (!$announced && (str_contains(strtolower($query), 'for update') || str_contains(strtolower($query), 'for share'))) {
                        $announced = true;
                        fwrite($sockets[1], "locking\n");
                    }
                });
                $result = $contender();
            } catch (Throwable $exception) {
                $result = ['error' => $exception::class . ': ' . $exception->getMessage()];
            }
            fwrite($sockets[1], json_encode($result, JSON_THROW_ON_ERROR) . "\n");
            DB::disconnect();
            fclose($sockets[1]);
            exit(0);
        }

        fclose($sockets[1]);
        stream_set_timeout($sockets[0], 10);
        try {
            $this->assertSame("ready\n", fgets($sockets[0]));
            DB::beginTransaction();
            $winner();
            fwrite($sockets[0], "go\n");
            $this->assertSame("locking\n", fgets($sockets[0]));
            $read = [$sockets[0]];
            $write = $except = [];
            $this->assertSame(0, stream_select($read, $write, $except, 0, 200_000), 'The contender finished before the winner released its locks.');
            DB::commit();
            $result = json_decode((string) fgets($sockets[0]), true, 512, JSON_THROW_ON_ERROR);
            $this->assertArrayNotHasKey('error', $result);

            return $result;
        } finally {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            fclose($sockets[0]);
            if (pcntl_waitpid($pid, $status, WNOHANG) === 0) {
                posix_kill($pid, SIGTERM);
                pcntl_waitpid($pid, $status);
            }
        }
    }
}
