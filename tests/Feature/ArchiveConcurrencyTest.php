<?php

namespace Tests\Feature;

use App\Models\Archive;
use App\Models\MasterData\ReferenceGroup;
use App\Models\MasterData\ReferenceItem;
use App\Models\MasterData\User;
use App\Services\Archive\ArchiveReference;
use Database\Factories\UserFactory;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\TestCase;
use Throwable;

#[Group('archive-concurrency')]
class ArchiveConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('ARCHIVE_DATABASE_TESTS') !== '1') {
            $this->markTestSkipped('Run with phpunit.archive.xml against a disposable MySQL/PostgreSQL database.');
        }
        if (!app()->environment('testing')
            || !in_array(DB::getDriverName(), ['mysql', 'pgsql'], true)
            || preg_match('/\Atensura_archive_test_[a-z0-9_]+\z/D', DB::connection()->getDatabaseName()) !== 1) {
            throw new RuntimeException('Concurrency tests require an isolated tensura_archive_test_* database.');
        }
        if (!function_exists('pcntl_fork') || !function_exists('posix_kill')) {
            $this->markTestSkipped('Concurrency tests require pcntl and posix.');
        }
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        RefreshDatabaseState::$migrated = false;
    }

    #[DataProvider('competingArchiveActions')]
    public function test_competing_confirmations_process_the_package_once(string $first, string $second): void
    {
        [$archive, $actor, $group, $item] = $this->package();
        $this->actingAs($actor);
        $firstInput = $this->confirmation($archive, $first);
        $secondInput = $this->confirmation($archive, $second);

        $response = $this->whileLocked(
            fn() => $this->execute($archive, $first, $firstInput)->assertOk(),
            fn(): array => $this->responseData($this->execute($archive, $second, $secondInput)),
        );

        $this->assertSame(409, $response['status']);
        $this->assertSame('archive_changed', $response['code']);
        $this->assertModelMissing($archive);
        $this->assertDatabaseCount('archive_records', 0);
        $this->assertDatabaseCount('reference_groups', $first === 'restore' ? 1 : 0);
        $this->assertDatabaseCount('reference_items', $first === 'restore' ? 1 : 0);
        if ($first === 'restore') {
            $this->assertEquals($group->getAttributes(), $group->fresh()->getAttributes());
            $this->assertEquals($item->getAttributes(), $item->fresh()->getAttributes());
        }
    }

    /** @return array<string, array{string, string}> */
    public static function competingArchiveActions(): array
    {
        return [
            'restore wins over purge' => ['restore', 'delete_forever'],
            'purge wins over restore' => ['delete_forever', 'restore'],
            'repeated restore' => ['restore', 'restore'],
            'repeated purge' => ['delete_forever', 'delete_forever'],
        ];
    }

    #[DataProvider('activeOperations')]
    public function test_active_reference_changes_force_a_waiting_restore_to_request_a_new_preview(string $operation): void
    {
        [$archive, $actor, $source, $archivedItem] = $this->package();
        $target = ReferenceGroup::factory()->create();
        $active = ReferenceItem::factory()->for($target, 'group')->create(['content' => 'Aktif', 'sort_order' => 8]);
        $other = ReferenceItem::factory()->for($target, 'group')->create(['content' => 'Lainnya', 'sort_order' => 9]);
        $this->actingAs($actor);
        $input = $this->confirmation($archive, 'restore', ['mode' => 'children_all', 'target_group_uuid' => $target->uuid]);

        $response = $this->whileLocked(
            function () use ($operation, $target, $active, $other): void {
                match ($operation) {
                    'store' => $this->postJson(route('ref.item.store', $target->uuid), ['content' => 'Baru'])->assertRedirect(),
                    'update' => $this->patchJson(route('ref.item.update', [$target->uuid, $active->uuid]), ['content' => 'Diubah'])->assertRedirect(),
                    'reorder' => $this->patchJson(route('ref.item.reorder', $target->uuid), ['ids' => [$other->uuid, $active->uuid]])->assertRedirect(),
                    'item delete' => $this->deleteJson(route('ref.item.destroy', [$target->uuid, $active->uuid]))->assertRedirect(),
                    'group update' => $this->patchJson(route('ref.update', $target->uuid), ['name' => 'Tujuan diubah', 'description' => 'Deskripsi baru'])->assertRedirect(),
                    'group delete' => $this->deleteJson(route('ref.destroy', $target->uuid))->assertRedirect(),
                };
            },
            fn(): array => $this->responseData($this->execute($archive, 'restore', $input)),
        );

        $this->assertSame(409, $response['status']);
        $this->assertSame('archive_changed', $response['code']);
        $this->assertModelExists($archive);
        $this->assertSame(2, $archive->records()->count());
        $this->assertModelMissing($source);
        $this->assertModelMissing($archivedItem);
        match ($operation) {
            'store' => $this->assertDatabaseHas('reference_items', ['reference_group_id' => $target->id, 'content' => 'Baru', 'sort_order' => 10]),
            'update' => $this->assertDatabaseHas('reference_items', ['uuid' => $active->uuid, 'content' => 'Diubah']),
            'reorder' => $this->assertSame([$other->uuid, $active->uuid], $target->items()->pluck('uuid')->all()),
            'item delete' => $this->assertModelMissing($active),
            'group update' => $this->assertDatabaseHas('reference_groups', ['uuid' => $target->uuid, 'name' => 'Tujuan diubah']),
            'group delete' => $this->assertModelMissing($target),
        };
    }

    /** @return array<string, array{string}> */
    public static function activeOperations(): array
    {
        return array_combine(
            ['store', 'update', 'reorder', 'item delete', 'group update', 'group delete'],
            array_map(fn(string $operation): array => [$operation], ['store', 'update', 'reorder', 'item delete', 'group update', 'group delete']),
        );
    }

    public function test_restore_and_an_active_append_share_the_group_lock_and_keep_distinct_sort_orders(): void
    {
        [$archive, $actor, $source, $item] = $this->package();
        $target = ReferenceGroup::factory()->create();
        ReferenceItem::factory()->for($target, 'group')->create(['sort_order' => 8]);
        $this->actingAs($actor);
        $input = $this->confirmation($archive, 'restore', ['mode' => 'children_all', 'target_group_uuid' => $target->uuid]);

        $response = $this->whileLocked(
            fn() => $this->execute($archive, 'restore', $input)->assertOk(),
            fn(): array => $this->responseData($this->postJson(route('ref.item.store', $target->uuid), ['content' => 'Baru setelah restore'])),
        );

        $this->assertSame(302, $response['status']);
        $this->assertDatabaseHas('reference_items', ['uuid' => $item->uuid, 'reference_group_id' => $target->id, 'sort_order' => 9]);
        $this->assertDatabaseHas('reference_items', ['reference_group_id' => $target->id, 'content' => 'Baru setelah restore', 'sort_order' => 10]);
        $this->assertSame(1, $archive->records()->count());
        $this->assertFalse($archive->fresh()->context['parent_resolved']);
        $this->assertModelMissing($source);
    }

    /** @return array{Archive, User, ReferenceGroup, ReferenceItem} */
    private function package(): array
    {
        $actor = UserFactory::new()->create();
        $group = ReferenceGroup::factory()->create();
        $item = ReferenceItem::factory()->for($group, 'group')->create(['sort_order' => 3]);

        return [app(ArchiveReference::class)->group($group, $actor), $actor, $group, $item];
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function confirmation(Archive $archive, string $action, array $overrides = []): array
    {
        $input = ['action' => $action, 'mode' => $action === 'restore' ? 'parent_with_children' : 'package', ...$overrides];
        $preview = $this->postJson(route('archives.preview', $archive->uuid), $input)->assertOk()->assertJsonPath('data.can_confirm', true);

        return [...$input, 'confirmation_token' => $preview->json('data.confirmation_token')];
    }

    /** @param array<string, mixed> $input */
    private function execute(Archive $archive, string $action, array $input): TestResponse
    {
        return $action === 'restore'
            ? $this->postJson(route('archives.restore', $archive->uuid), $input)
            : $this->deleteJson(route('archives.destroy', $archive->uuid), $input);
    }

    /** @return array{status: int, code: ?string} */
    private function responseData(TestResponse $response): array
    {
        return ['status' => $response->status(), 'code' => $response->headers->get('Content-Type') === 'application/json' ? $response->json('code') : null];
    }

    /**
     * The winner retains its real application locks in an outer transaction. An
     * independent process announces its locking query before executing it. The
     * parent verifies it cannot finish until the winner commits, then reads its
     * HTTP response. No database connection is shared across the fork.
     *
     * @param  callable(): mixed  $winner
     * @param  callable(): array{status: int, code: ?string}  $contender
     * @return array{status: int, code: ?string}
     */
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
                    if (!$announced && str_contains(strtolower($query), 'for update')) {
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
