<?php

namespace Tests\Feature;

use App\Enums\LetterOperationStatusEnum as Status;
use App\Models\LetterOperation;
use App\Models\MasterData\User;
use App\Services\Letter\LetterOperationEvents;
use App\Services\Letter\RecordLetterNotification;
use App\Services\Notification\NotificationEvents;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LetterNotificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[DataProvider('importantResults')]
    public function test_terminal_transitions_record_the_important_results_as_unread_snapshots(string $kind, Status $status, string $title, string $publicStatus): void
    {
        $this->freezeTime();
        $actor = UserFactory::new()->create();
        $operation = LetterOperation::factory()->create(['actor_uuid' => $actor->uuid, 'kind' => $kind]);
        $operation->letter->update(['subject' => 'Undangan rapat']);
        $this->mock(NotificationEvents::class, function (MockInterface $mock) use ($actor): void {
            $mock->shouldNotReceive('changed');
            $mock->shouldReceive('created')->once()->withArgs(fn(User $user, string $id): bool => $user->is($actor) && $user->notifications()->whereKey($id)->exists());
        });

        DB::transaction(fn() => app(LetterOperationEvents::class)->transition($operation, $status, 'Hasil operasi'));

        $entry = $actor->notifications()->sole();
        $this->assertSame('letter-operation:' . $operation->uuid, $entry->event_key);
        $this->assertSame('letter-operation', $entry->type);
        $this->assertNull($entry->read_at);
        $this->assertSame([
            'schema_version' => 1, 'title' => $title, 'subject' => 'Undangan rapat',
            'message' => 'Hasil operasi', 'letter_id' => $operation->letter->uuid,
            'operation_id' => $operation->uuid, 'status' => $publicStatus, 'kind' => $kind,
        ], $entry->data);
        $this->assertDatabaseCount('notifications', 1);
    }

    public static function importantResults(): array
    {
        return [
            'issuance succeeded' => ['issue', Status::SUCCEEDED, 'Surat berhasil diterbitkan', 'succeeded'],
            'PDF succeeded' => ['pdf', Status::SUCCEEDED, 'PDF surat siap', 'succeeded'],
            'preview failed' => ['preview', Status::FAILED, 'Preview surat gagal', 'failed'],
            'issuance failed' => ['issue', Status::FAILED, 'Penerbitan surat gagal', 'failed'],
            'PDF failed' => ['pdf', Status::FAILED, 'Konversi PDF gagal', 'failed'],
            'preview stale' => ['preview', Status::STALE, 'Preview surat perlu diulang', 'stale'],
            'issuance stale' => ['issue', Status::STALE, 'Penerbitan surat perlu diulang', 'stale'],
            'PDF stale' => ['pdf', Status::STALE, 'Konversi PDF perlu diulang', 'stale'],
        ];
    }

    #[DataProvider('unimportantResults')]
    public function test_queued_running_and_successful_preview_results_do_not_create_notifications(string $kind, Status $status): void
    {
        $actor = UserFactory::new()->create();
        $operation = LetterOperation::factory()->create(['actor_uuid' => $actor->uuid, 'kind' => $kind]);
        $this->mock(NotificationEvents::class)->shouldNotReceive('created', 'changed');

        DB::transaction(fn() => app(LetterOperationEvents::class)->transition($operation, $status));

        $this->assertDatabaseCount('notifications', 0);
        $this->assertSame($status, $operation->fresh()->status);
    }

    public static function unimportantResults(): array
    {
        return [
            'preview queued' => ['preview', Status::QUEUED],
            'preview running' => ['preview', Status::RUNNING],
            'preview succeeded' => ['preview', Status::SUCCEEDED],
            'issuance queued' => ['issue', Status::QUEUED],
            'issuance running' => ['issue', Status::RUNNING],
            'PDF queued' => ['pdf', Status::QUEUED],
            'PDF running' => ['pdf', Status::RUNNING],
        ];
    }

    public function test_repeated_recording_is_once_per_operation_and_recipient_and_preserves_the_read_snapshot(): void
    {
        $this->freezeTime();
        $actor = UserFactory::new()->create();
        $second = UserFactory::new()->create();
        $operation = LetterOperation::factory()->create([
            'actor_uuid' => $actor->uuid, 'kind' => 'pdf', 'status' => Status::SUCCEEDED, 'message' => 'PDF siap',
        ]);
        $this->mock(NotificationEvents::class, function (MockInterface $mock) use ($actor, $second): void {
            $mock->shouldNotReceive('changed');
            $mock->shouldReceive('created')->once()->withArgs(fn(User $user, string $id): bool => $user->is($actor) && $user->notifications()->whereKey($id)->exists());
            $mock->shouldReceive('created')->once()->withArgs(fn(User $user, string $id): bool => $user->is($second) && $user->notifications()->whereKey($id)->exists());
        });
        $recorder = app(RecordLetterNotification::class);
        $recorder->record($operation);
        $entry = $actor->notifications()->sole();
        $entry->markAsRead();
        $snapshot = $entry->data;
        $readAt = $entry->fresh()->read_at->toIso8601String();
        $this->travel(10)->minutes();
        $operation->letter->update(['subject' => 'Judul diubah']);
        $operation->update(['message' => 'Pesan diubah']);

        $recorder->record($operation);
        $recorder->record($operation, $actor);
        $recorder->record($operation, $second);
        $recorder->record($operation, $second);

        $this->assertDatabaseCount('notifications', 2);
        $this->assertSame($entry->id, $actor->notifications()->sole()->id);
        $this->assertSame($snapshot, $entry->fresh()->data);
        $this->assertSame($readAt, $entry->fresh()->read_at->toIso8601String());
        $this->assertNull($second->notifications()->sole()->read_at);
        $this->assertSame('letter-operation:' . $operation->uuid, $second->notifications()->sole()->event_key);
    }

    public function test_a_stored_notification_remains_listable_after_the_operation_is_deleted_and_letter_changes(): void
    {
        $actor = UserFactory::new()->create();
        $operation = LetterOperation::factory()->create([
            'actor_uuid' => $actor->uuid, 'kind' => 'issue', 'status' => Status::FAILED, 'message' => 'Penerbitan gagal',
        ]);
        $operation->letter->update(['subject' => 'Judul saat operasi']);
        app(RecordLetterNotification::class)->record($operation);
        $operationUuid = $operation->uuid;
        $letterUuid = $operation->letter->uuid;
        $operation->letter->update(['subject' => 'Judul terbaru']);

        $operation->delete();

        $this->assertModelMissing($operation);
        $this->actingAs($actor)->getJson(route('notifications.index'))->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('unread_count', 1)
            ->assertJsonPath('data.0.subject', 'Judul saat operasi')
            ->assertJsonPath('data.0.message', 'Penerbitan gagal')
            ->assertJsonPath('data.0.letter_id', $letterUuid)
            ->assertJsonPath('data.0.operation_id', $operationUuid);
    }
}
