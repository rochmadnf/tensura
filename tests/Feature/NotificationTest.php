<?php

namespace Tests\Feature;

use App\Enums\LetterOperationStatusEnum as Status;
use App\Models\LetterOperation;
use App\Models\MasterData\User;
use App\Services\Notification\NotificationEvents;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[DataProvider('protectedEndpoints')]
    public function test_notification_endpoints_require_authentication(string $method, string $name, array $parameters): void
    {
        $this->json($method, route($name, $parameters))->assertUnauthorized();
    }

    public static function protectedEndpoints(): array
    {
        return [
            'list' => ['GET', 'notifications.index', []],
            'summary' => ['GET', 'notifications.summary', []],
            'read one' => ['PATCH', 'notifications.read', ['notification' => '00000000-0000-4000-8000-000000000001']],
            'read all' => ['PATCH', 'notifications.readAll', []],
        ];
    }

    public function test_list_and_summary_return_only_the_accounts_snapshots_and_public_fields(): void
    {
        $this->freezeTime();
        $actor = UserFactory::new()->create();
        $entry = $this->notification($actor, ['data' => [
            'title' => 'PDF surat siap', 'subject' => 'Undangan rapat', 'message' => 'Selesai',
            'letter_id' => '00000000-0000-4000-8000-000000000010',
            'operation_id' => '00000000-0000-4000-8000-000000000011', 'status' => 'succeeded',
            'payload' => ['secret' => 'private-payload'], 'source_path' => '/private/documents/source.docx',
            'actor_uuid' => $actor->uuid, 'internal_id' => 42, 'schema_version' => 1, 'kind' => 'pdf',
        ]]);
        $this->notification(UserFactory::new()->create());

        $this->actingAs($actor)->getJson(route('notifications.index'))
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertExactJson([
                'data' => [[
                    'id' => $entry->id, 'type' => 'letter-operation', 'title' => 'PDF surat siap',
                    'subject' => 'Undangan rapat', 'message' => 'Selesai',
                    'letter_id' => '00000000-0000-4000-8000-000000000010',
                    'operation_id' => '00000000-0000-4000-8000-000000000011', 'status' => 'succeeded',
                    'created_at' => now()->toIso8601String(), 'read_at' => null,
                ]],
                'next_cursor' => null, 'unread_count' => 1,
            ]);
        $this->getJson(route('notifications.summary'))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')->assertExactJson(['unread_count' => 1]);
    }

    public function test_unread_filter_excludes_read_entries_without_changing_the_unread_count(): void
    {
        $actor = UserFactory::new()->create();
        $unread = $this->notification($actor);
        $this->notification($actor, ['read_at' => now()]);
        $this->notification(UserFactory::new()->create());

        $this->actingAs($actor)->getJson(route('notifications.index', ['filter' => 'unread']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $unread->id)
            ->assertJsonPath('unread_count', 1)->assertJsonPath('next_cursor', null);
        $this->getJson(route('notifications.index', ['filter' => 'all']))
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('unread_count', 1);
    }

    public function test_cursor_paginates_twenty_entries_by_creation_time_then_id_without_duplicates(): void
    {
        $this->freezeTime();
        $actor = UserFactory::new()->create();
        $oldest = $this->notification($actor, [
            'id' => 'ffffffff-ffff-4fff-8fff-ffffffffffff', 'created_at' => now()->subDay(),
        ]);
        $ids = [];
        for ($index = 1; $index <= 21; $index++) {
            $ids[] = $this->notification($actor, [
                'id' => sprintf('00000000-0000-4000-8000-%012d', $index),
            ])->id;
        }
        $this->notification(UserFactory::new()->create(), ['created_at' => now()->addDay()]);

        $first = $this->actingAs($actor)->getJson(route('notifications.index'))
            ->assertOk()->assertJsonCount(20, 'data')->assertJsonPath('unread_count', 22);

        $this->assertSame(array_reverse(array_slice($ids, 1)), array_column($first->json('data'), 'id'));
        $this->assertIsString($first->json('next_cursor'));
        $second = $this->getJson(route('notifications.index', ['cursor' => $first->json('next_cursor')]))
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('next_cursor', null)
            ->assertJsonPath('unread_count', 22);
        $this->assertSame([$ids[0], $oldest->id], array_column($second->json('data'), 'id'));
    }

    public function test_read_is_persisted_on_reload_and_repeated_reads_preserve_the_original_timestamp(): void
    {
        $this->freezeTime();
        $actor = UserFactory::new()->create();
        $entry = $this->notification($actor);
        $other = $this->notification($actor);
        $readAt = now()->toIso8601String();
        $this->mock(NotificationEvents::class, function (MockInterface $mock) use ($actor): void {
            $mock->shouldReceive('changed')->once()->withArgs(fn(User $user): bool => $user->is($actor));
        });

        $this->actingAs($actor)->patchJson(route('notifications.read', $entry->id))
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertExactJson(['unread_count' => 1]);

        $this->assertSame($readAt, $entry->fresh()->read_at->toIso8601String());
        $this->assertNull($other->fresh()->read_at);
        $this->travel(5)->minutes();
        $this->patchJson(route('notifications.read', $entry->id))->assertOk()->assertExactJson(['unread_count' => 1]);
        $this->assertSame($readAt, $entry->fresh()->read_at->toIso8601String());
        $list = $this->getJson(route('notifications.index'))->assertOk()->json('data');
        $this->assertSame($readAt, collect($list)->firstWhere('id', $entry->id)['read_at']);
        $this->getJson(route('notifications.index', ['filter' => 'unread']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $other->id);
        $this->getJson(route('notifications.summary'))->assertOk()->assertExactJson(['unread_count' => 1]);
    }

    public function test_read_all_is_account_scoped_and_idempotent_without_rewriting_read_timestamps(): void
    {
        $this->freezeTime();
        $actor = UserFactory::new()->create();
        $readAt = now()->toIso8601String();
        $unread = $this->notification($actor);
        $secondUnread = $this->notification($actor);
        $alreadyRead = $this->notification($actor, ['read_at' => now()->subDay()]);
        $originalReadAt = $alreadyRead->read_at->toIso8601String();
        $foreign = $this->notification(UserFactory::new()->create());
        $this->mock(NotificationEvents::class, function (MockInterface $mock) use ($actor): void {
            $mock->shouldReceive('changed')->once()->withArgs(fn(User $user): bool => $user->is($actor));
        });

        $this->actingAs($actor)->patchJson(route('notifications.readAll'))
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertExactJson(['unread_count' => 0]);

        $this->assertSame($readAt, $unread->fresh()->read_at->toIso8601String());
        $this->assertSame($readAt, $secondUnread->fresh()->read_at->toIso8601String());
        $this->assertSame($originalReadAt, $alreadyRead->fresh()->read_at->toIso8601String());
        $this->assertNull($foreign->fresh()->read_at);
        $this->travel(5)->minutes();
        $this->patchJson(route('notifications.readAll'))->assertOk()->assertExactJson(['unread_count' => 0]);
        $this->assertSame($readAt, $unread->fresh()->read_at->toIso8601String());
        $this->assertSame($readAt, $secondUnread->fresh()->read_at->toIso8601String());
        $this->assertSame($originalReadAt, $alreadyRead->fresh()->read_at->toIso8601String());
        $this->getJson(route('notifications.index', ['filter' => 'unread']))
            ->assertOk()->assertExactJson(['data' => [], 'next_cursor' => null, 'unread_count' => 0]);
        $this->getJson(route('notifications.summary'))->assertOk()->assertExactJson(['unread_count' => 0]);
    }

    public function test_reading_another_accounts_notification_returns_404_without_mutating_either_account(): void
    {
        $actor = UserFactory::new()->create();
        $own = $this->notification($actor);
        $foreign = $this->notification(UserFactory::new()->create());
        $this->mock(NotificationEvents::class)->shouldNotReceive('changed');

        $this->actingAs($actor)->patchJson(route('notifications.read', $foreign->id))->assertNotFound();
        $this->patchJson(route('notifications.read', (string) Str::uuid()))->assertNotFound();

        $this->assertNull($own->fresh()->read_at);
        $this->assertNull($foreign->fresh()->read_at);
        $this->assertDatabaseCount('notifications', 2);
    }

    #[DataProvider('invalidFilters')]
    public function test_invalid_filters_return_422_without_changing_notifications(mixed $filter): void
    {
        $actor = UserFactory::new()->create();
        $entry = $this->notification($actor);

        $this->actingAs($actor)->getJson(route('notifications.index', ['filter' => $filter]))
            ->assertUnprocessable()->assertJsonValidationErrors('filter');

        $this->assertNull($entry->fresh()->read_at);
        $this->assertDatabaseCount('notifications', 1);
    }

    public static function invalidFilters(): array
    {
        return ['unknown' => ['everything'], 'empty' => [''], 'array' => [['unread']]];
    }

    #[DataProvider('invalidCursors')]
    public function test_invalid_cursors_return_422_instead_of_running_an_invalid_pagination_query(string $cursor): void
    {
        $actor = UserFactory::new()->create();
        $entry = $this->notification($actor);

        $this->actingAs($actor)->getJson(route('notifications.index', ['cursor' => $cursor]))
            ->assertUnprocessable()->assertJsonValidationErrors('cursor');

        $this->assertNull($entry->fresh()->read_at);
        $this->assertDatabaseCount('notifications', 1);
    }

    public static function invalidCursors(): array
    {
        $valid = ['id' => '00000000-0000-4000-8000-000000000001', 'created_at' => '2026-10-27 08:00:00', '_pointsToNextItems' => true];

        return [
            'invalid encoding' => ['not-a-cursor'],
            'missing parameters' => [base64_encode(json_encode(['_pointsToNextItems' => true], JSON_THROW_ON_ERROR))],
            'invalid uuid' => [base64_encode(json_encode([...$valid, 'id' => 'invalid'], JSON_THROW_ON_ERROR))],
            'invalid timestamp' => [base64_encode(json_encode([...$valid, 'created_at' => 'not-a-date'], JSON_THROW_ON_ERROR))],
            'timestamp array' => [base64_encode(json_encode([...$valid, 'created_at' => ['2026-10-27']], JSON_THROW_ON_ERROR))],
            'invalid direction' => [base64_encode(json_encode([...$valid, '_pointsToNextItems' => []], JSON_THROW_ON_ERROR))],
        ];
    }

    public function test_listing_and_summary_do_not_backfill_historical_operations(): void
    {
        $actor = UserFactory::new()->create();
        LetterOperation::factory()->create(['actor_uuid' => $actor->uuid, 'kind' => 'issue', 'status' => Status::SUCCEEDED]);

        $this->actingAs($actor)->getJson(route('notifications.index'))
            ->assertOk()->assertExactJson(['data' => [], 'next_cursor' => null, 'unread_count' => 0]);
        $this->getJson(route('notifications.summary'))->assertOk()->assertExactJson(['unread_count' => 0]);

        $this->assertDatabaseCount('notifications', 0);
    }

    /** @param array<string, mixed> $attributes */
    private function notification(User $user, array $attributes = []): DatabaseNotification
    {
        return $user->notifications()->create([
            'id' => (string) Str::uuid(), 'type' => 'letter-operation',
            'data' => [
                'title' => 'Surat berhasil diterbitkan', 'subject' => 'Undangan', 'message' => null,
                'letter_id' => (string) Str::uuid(), 'operation_id' => (string) Str::uuid(), 'status' => 'succeeded',
            ],
            ...$attributes,
        ]);
    }
}
