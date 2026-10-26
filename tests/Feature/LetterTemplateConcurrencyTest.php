<?php

namespace Tests\Feature;

use App\Enums\AsnStatusEnum;
use App\Enums\LetterTemplateVersionStatusEnum;
use App\Models\MasterData\Employee;
use App\Models\MasterData\LetterTemplate;
use App\Models\MasterData\LetterTemplateVersion;
use App\Models\MasterData\ReferenceGroup;
use App\Services\LetterTemplate\CleanupLetterTemplateFiles;
use App\Services\LetterTemplate\LetterTemplateTestEvidence;
use App\Services\LetterTemplate\LetterTemplateVersionState;
use Database\Factories\LetterNumberFactory;
use Database\Factories\UserFactory;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PDOException;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\TestCase;
use Throwable;

#[Group('letter-template-concurrency')]
class LetterTemplateConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('LETTER_TEMPLATE_DATABASE_TESTS') !== '1') {
            $this->markTestSkipped('Run explicitly with phpunit.letter-templates.xml on a disposable PostgreSQL database.');
        }
        if (!app()->environment('testing') || DB::getDriverName() !== 'pgsql' || preg_match('/\Atensura_test_letters_[a-z0-9_]+\z/D', DB::connection()->getDatabaseName()) !== 1 || !function_exists('pcntl_fork') || !function_exists('posix_kill')) {
            throw new RuntimeException('Letter template concurrency requires an isolated PostgreSQL database, pcntl, and posix.');
        }
        config(['letter-templates.disk' => 'letter-template-concurrency']);
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        RefreshDatabaseState::$migrated = false;
    }

    #[DataProvider('draftModes')]
    public function test_competing_draft_requests_create_or_reopen_only_one_draft(bool $used): void
    {
        $version = $this->ready();
        $args = [$version->template->uuid, $version->uuid];
        $this->postJson(route('lt.versions.publish', $args), $this->payload($version, true))->assertRedirect();
        if ($used) {
            $version->refresh()->update(['first_used_at' => now()]);
        }
        $input = $this->payload($version);
        $result = $this->whileLocked(
            fn() => $this->postJson(route('lt.versions.draft', $args), $input)->assertRedirect(),
            fn(): array => $this->response($this->postJson(route('lt.versions.draft', $args), $input)),
        );
        $this->assertSame(422, $result['status']);
        $this->assertSame(1, $version->template->versions()->where('status', LetterTemplateVersionStatusEnum::DRAFT)->count());
        $this->assertSame($used ? 2 : 1, $version->template->versions()->count());
        $this->assertCount($used ? 2 : 1, Storage::disk('letter-template-concurrency')->allFiles('letter-templates/sources'));
    }

    public static function draftModes(): array
    {
        return [[false], [true]];
    }

    #[DataProvider('competingVersionOperations')]
    public function test_waiting_publication_archive_or_item_update_rechecks_the_committed_state(string $first, string $second): void
    {
        $version = $this->ready();
        $input = $this->payload($version, true);
        $result = $this->whileLocked(
            fn() => $this->operate($version, $first, $input)->assertRedirect(),
            fn(): array => $this->response($this->operate($version, $second, $input)),
        );
        $this->assertSame(422, $result['status']);
        $this->assertSame(match ($first) {
            'publish' => LetterTemplateVersionStatusEnum::PUBLISHED, 'archive' => LetterTemplateVersionStatusEnum::ARCHIVED, default => LetterTemplateVersionStatusEnum::DRAFT
        }, $version->fresh()->status);
        $this->assertSame($first === 'publish' ? $version->id : null, $version->template->fresh()->active_version_id);
    }

    public static function competingVersionOperations(): array
    {
        return [['publish', 'publish'], ['configuration', 'publish'], ['publish', 'configuration'], ['configuration', 'archive'], ['archive', 'configuration']];
    }

    #[DataProvider('candidateOperations')]
    public function test_candidate_status_changes_and_waiting_operations_are_serialized(string $operation, bool $statusFirst): void
    {
        $version = $this->ready(true);
        $employee = $version->signerSlots->sole()->candidates->sole()->employee;
        $input = $this->payload($version, true);
        $change = function () use ($employee): array {
            $record = Employee::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $record->update(['status' => AsnStatusEnum::PENSIUN]);

            return ['status' => 200];
        };
        $result = $this->whileLocked(
            $statusFirst ? $change : fn() => $this->operate($version, $operation, $input)->assertRedirect(),
            $statusFirst ? fn(): array => $this->response($this->operate($version, $operation, $input)) : fn(): array => DB::transaction($change),
        );
        $this->assertSame($statusFirst ? 422 : 200, $result['status']);
        $this->assertSame(AsnStatusEnum::PENSIUN, $employee->fresh()->status);
        $this->assertSame($operation === 'publish' && !$statusFirst ? LetterTemplateVersionStatusEnum::PUBLISHED : LetterTemplateVersionStatusEnum::DRAFT, $version->fresh()->status);
        $this->assertDatabaseCount('letter_template_signer_candidates', 1);
    }

    public static function candidateOperations(): array
    {
        return array_merge(...array_map(fn(string $operation): array => [[$operation, true], [$operation, false]], ['publish', 'generate', 'candidates']));
    }

    #[DataProvider('masterOperations')]
    public function test_configuration_and_master_removal_recheck_references_after_waiting(string $kind, bool $saveFirst): void
    {
        $version = $this->ready();
        $args = [$version->template->uuid, $version->uuid];
        $master = match ($kind) {
            'employee' => Employee::factory()->create(['status' => AsnStatusEnum::AKTIF]),
            'group' => ReferenceGroup::factory()->create(),
            default => LetterNumberFactory::new()->create(),
        };
        $save = fn(): TestResponse => match ($kind) {
            'employee' => $this->postJson(route('lt.configuration.slots.store', $args), ['item' => ['candidates' => [$master->uuid]]]),
            'group' => $this->postJson(route('lt.configuration.variables.store', $args), ['item' => ['name' => 'referensi', 'label' => 'Referensi', 'type' => 4, 'option_source' => 1, 'reference_group' => $master->uuid, 'is_required' => false, 'is_multiple' => false, 'columns' => []]]),
            default => $this->patchJson(route('lt.update', $version->template->uuid), ['name' => $version->template->name, 'letter_number' => $master->uuid]),
        };
        $remove = fn(): TestResponse => $this->deleteJson(route(match ($kind) {
            'employee' => 'empl.destroy', 'group' => 'ref.destroy', default => 'ln.destroy'
        }, $master->uuid));
        $result = $this->whileLocked(
            fn() => ($saveFirst ? $save() : $remove())->assertRedirect(),
            fn(): array => $this->response($saveFirst ? $remove() : $save()),
        );
        $this->assertSame(422, $result['status']);
        if ($saveFirst) {
            $this->assertModelExists($master);
        } else {
            $this->assertModelMissing($master);
        }
        $this->assertDatabaseCount('archives', $kind === 'group' && !$saveFirst ? 1 : 0);
    }

    public static function masterOperations(): array
    {
        return array_merge(...array_map(fn(string $kind): array => [[$kind, true], [$kind, false]], ['employee', 'group', 'classification']));
    }

    public function test_cleanup_waits_for_a_refreshed_preview_and_keeps_its_file(): void
    {
        $version = $this->ready();
        $version->update(['test_expires_at' => now()->subHour()]);
        $result = $this->whileLocked(
            function () use ($version): void {
                LetterTemplate::query()->whereKey($version->letter_template_id)->lockForUpdate()->firstOrFail();
                $version->update(['test_expires_at' => now()->addDay()]);
            },
            fn(): array => app(CleanupLetterTemplateFiles::class)->run(),
        );
        $this->assertSame(0, $result['deleted']);
        Storage::disk('letter-template-concurrency')->assertExists([$version->source_path, $version->test_path]);
    }

    #[DataProvider('draftModes')]
    public function test_first_use_of_the_previous_version_is_serialized_with_publication(bool $useFirst): void
    {
        $old = $this->ready();
        $this->operate($old, 'publish', $this->payload($old, true))->assertRedirect();
        $new = $this->ready(false, $old->template);
        $input = $this->payload($new, true);
        $use = fn(): array => DB::transaction(function () use ($old): array {
            $template = LetterTemplate::query()->whereKey($old->letter_template_id)->lockForUpdate()->firstOrFail();
            $version = $template->versions()->whereKey($old->id)->lockForUpdate()->firstOrFail();
            if ($template->active_version_id !== $version->id || $version->status !== LetterTemplateVersionStatusEnum::PUBLISHED) {
                return ['status' => 422];
            }
            $version->update(['first_used_at' => now()]);

            return ['status' => 200];
        });
        $result = $this->whileLocked(
            $useFirst ? $use : fn() => $this->operate($new, 'publish', $input)->assertRedirect(),
            $useFirst ? fn(): array => $this->response($this->operate($new, 'publish', $input)) : $use,
        );
        $this->assertSame($useFirst ? 302 : 422, $result['status']);
        $this->assertSame($new->id, $old->template->fresh()->active_version_id);
        $this->assertSame($useFirst, $old->fresh()->first_used_at !== null);
    }

    public function test_independent_item_updates_preserve_both_committed_changes(): void
    {
        $version = $this->ready();
        $first = $version->variables->sole();
        $second = $version->variables()->create(['name' => 'lain', 'label' => 'Pertanyaan lain', 'type' => 1, 'is_required' => false, 'is_multiple' => false, 'sort_order' => 1]);
        $save = fn($variable, string $label): TestResponse => $this->putJson(route('lt.configuration.variables.update', [$version->template->uuid, $version->uuid, $variable->uuid]), ['item' => ['id' => $variable->uuid, 'name' => $variable->name, 'label' => $label, 'type' => 1, 'is_required' => false, 'is_multiple' => false, 'columns' => []]]);
        $result = $this->whileLocked(
            fn() => $save($first, 'Perubahan pertama')->assertRedirect(),
            fn(): array => $this->response($save($second, 'Perubahan kedua')),
        );
        $this->assertSame(302, $result['status']);
        $this->assertSame('Perubahan pertama', $first->fresh()->label);
        $this->assertSame('Perubahan kedua', $second->fresh()->label);
    }

    public function test_retry_after_a_copy_write_rolls_back_and_removes_the_failed_attempt_file(): void
    {
        $version = $this->ready();
        $this->operate($version, 'publish', $this->payload($version, true))->assertRedirect();
        $version->refresh()->update(['first_used_at' => now()]);
        $attempts = 0;
        DB::connection()->beforeExecuting(function (string $query) use (&$attempts): void {
            if (str_starts_with($query, 'insert into "letter_template_audits"') && $attempts++ === 0) {
                throw new PDOException('deadlock detected after writing copied DOCX', 40001);
            }
        });
        $this->postJson(route('lt.versions.draft', [$version->template->uuid, $version->uuid]), $this->payload($version))->assertRedirect();
        $this->assertSame(2, $attempts);
        $this->assertSame(2, $version->template->versions()->count());
        $this->assertCount(2, Storage::disk('letter-template-concurrency')->allFiles('letter-templates/sources'));
        $this->assertSame(1, $version->template->audits()->where('operation', 'draft.copied')->count());
    }

    private function ready(bool $signer = false, ?LetterTemplate $template = null): LetterTemplateVersion
    {
        if ($template === null) {
            Storage::fake('letter-template-concurrency');
        }
        $this->actingAs(UserFactory::new()->create());
        $version = LetterTemplateVersion::factory()->create($template === null ? [] : ['letter_template_id' => $template->id, 'version_number' => 2]);
        $version->variables()->create(['name' => 'tujuan', 'label' => 'Tujuan', 'type' => 1, 'is_required' => false, 'is_multiple' => false, 'sort_order' => 0]);
        if ($signer) {
            $employee = Employee::factory()->create(['status' => AsnStatusEnum::AKTIF]);
            $version->signerSlots()->create(['sort_order' => 1])->candidates()->create(['employee_id' => $employee->id]);
        }
        $path = tempnam(sys_get_temp_dir(), 'letter-race-');
        try {
            $word = new PhpWord();
            $word->addSection()->addText('${tujuan}' . ($signer ? ' ${penandatangan_nama}' : ''));
            IOFactory::createWriter($word, 'Word2007')->save($path);
            $args = [$version->template->uuid, $version->uuid];
            $this->postJson(route('lt.source.store', $args), ['docx' => UploadedFile::fake()->createWithContent('source.docx', file_get_contents($path))])->assertRedirect();
            $this->postJson(route('lt.validation.store', $args))->assertRedirect();
            $this->operate($version, 'generate', [])->assertRedirect();
            $this->postJson(route('lt.test.confirm', $args), ['test_token' => app(LetterTemplateTestEvidence::class)->token($version->fresh()), 'confirmed' => true])->assertRedirect();
        } finally {
            unlink($path);
        }

        return $version->fresh(['template', 'signerSlots.candidates.employee', 'variables']);
    }

    private function payload(LetterTemplateVersion $version, bool $publish = false): array
    {
        $version = $version->fresh();

        return ['confirmed' => true, 'version_token' => app(LetterTemplateVersionState::class)->token($version), ...($publish ? ['test_token' => app(LetterTemplateTestEvidence::class)->token($version)] : [])];
    }

    private function operate(LetterTemplateVersion $version, string $operation, array $input): TestResponse
    {
        $args = [$version->template->uuid, $version->uuid];
        if (in_array($operation, ['publish', 'archive'], true)) {
            return $this->postJson(route('lt.versions.' . $operation, $args), $input);
        }
        if ($operation === 'generate') {
            $version->refresh()->load('signerSlots.candidates.employee');
            $slot = $version->signerSlots->first();

            return $this->postJson(route('lt.test.store', $args), ['validation_token' => app(LetterTemplateTestEvidence::class)->validationToken($version), 'values' => [], 'signers' => $slot === null ? [] : [$slot->uuid => $slot->candidates->sole()->employee->uuid]]);
        }
        if ($operation === 'candidates') {
            $slot = $version->signerSlots->sole();

            return $this->putJson(route('lt.configuration.slots.update', [...$args, $slot->uuid]), ['item' => ['id' => $slot->uuid, 'candidates' => $slot->candidates->pluck('employee.uuid')->all()]]);
        }
        $variable = $version->variables()->firstOrFail();

        return $this->putJson(route('lt.configuration.variables.update', [...$args, $variable->uuid]), ['item' => ['id' => $variable->uuid, 'name' => $variable->name, 'label' => 'Tujuan diubah', 'type' => 1, 'is_required' => false, 'is_multiple' => false, 'option_source' => null, 'reference_group' => null, 'columns' => []]]);
    }

    private function response(TestResponse $response): array
    {
        return ['status' => $response->status(), 'errors' => $response->status() === 422 ? $response->json('errors') : null];
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
