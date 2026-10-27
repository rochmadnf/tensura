<?php

namespace Tests\Feature;

use App\Enums\LetterOperationStatusEnum as Status;
use App\Models\LetterOperation;
use App\Services\Letter\LetterOperationEvents;
use App\Services\Letter\LetterOperationListener;
use App\Services\Notification\NotificationEvents;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class LetterOperationEventsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_postgres_listener_receives_only_committed_notifications_from_another_connection(): void
    {
        $listener = app(LetterOperationListener::class);
        $listener->listen();
        $writer = DB::build([...DB::connection()->getConfig(), 'name' => 'letter_test_notify_writer']);
        $uuid = (string) Str::uuid();
        try {
            $writer->beginTransaction();
            $writer->select('select pg_notify(?, ?)', [config('letters.sse.channel'), $uuid]);
            $this->assertNull($listener->wait(1));
            $writer->commit();
            $this->assertSame($uuid, $listener->wait(1000));
            $writer->beginTransaction();
            $writer->select('select pg_notify(?, ?)', [config('letters.sse.channel'), (string) Str::uuid()]);
            $writer->rollBack();
            $this->assertNull($listener->wait(1));
        } finally {
            $listener->close();
            $writer->disconnect();
        }
    }

    public function test_reconnect_snapshot_contains_only_the_actors_active_and_recent_terminal_operations(): void
    {
        $this->freezeTime();
        $actor = UserFactory::new()->create();
        $active = LetterOperation::factory()->create(['actor_uuid' => $actor->uuid, 'status' => Status::RUNNING, 'updated_at' => now()->subDays(9)]);
        $recent = LetterOperation::factory()->create(['actor_uuid' => $actor->uuid, 'status' => Status::SUCCEEDED]);
        LetterOperation::factory()->create(['actor_uuid' => $actor->uuid, 'status' => Status::FAILED, 'updated_at' => now()->subDays(8)]);
        LetterOperation::factory()->create(['status' => Status::SUCCEEDED]);
        $events = app(LetterOperationEvents::class);
        $snapshot = $events->snapshot($actor->uuid);
        $this->assertSame([$active->uuid, $recent->uuid], array_column($snapshot, 'id'));
        $this->assertArrayNotHasKey('payload', $snapshot[0]);
        $this->assertArrayNotHasKey('actor_uuid', $snapshot[0]);
        $this->assertSame([], $events->snapshot($actor->uuid, (string) Str::uuid()));
        config(['letters.sse.seconds' => 0]);
        $response = $this->actingAs($actor)->get(route('ltr.events'))->assertOk()->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8')->assertHeader('X-Accel-Buffering', 'no');
        ob_start();
        $content = $response->streamedContent();
        $content = ob_get_clean() . $content;
        $this->assertStringContainsString($recent->uuid, $content);
        $this->assertStringContainsString('event: operations', $content);
        $this->assertStringContainsString("event: notifications\ndata: {\"changed\":true,\"reason\":\"sync\"}", $content);
        $this->assertStringNotContainsString('source_path', $content);
    }

    public function test_status_and_notification_roll_back_together(): void
    {
        $actor = UserFactory::new()->create();
        $operation = LetterOperation::factory()->create(['actor_uuid' => $actor->uuid, 'kind' => 'issue']);
        DB::beginTransaction();
        try {
            app(LetterOperationEvents::class)->transition($operation, Status::SUCCEEDED, 'Ready');
            $this->assertSame(1, $actor->notifications()->count());
            $this->assertSame(Status::SUCCEEDED, $operation->fresh()->status);
        } finally {
            DB::rollBack();
        }
        $this->assertSame(Status::QUEUED, $operation->fresh()->status);
        $this->assertSame(1, $operation->fresh()->status_revision);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_notification_signals_are_delivered_only_after_commit_and_are_discarded_on_rollback(): void
    {
        $actor = UserFactory::new()->create();
        $notificationId = (string) Str::uuid();
        $listener = app(LetterOperationListener::class);
        $listener->listen();
        $originalConnection = DB::getDefaultConnection();
        config(['database.connections.notification_test_writer' => DB::connection()->getConfig()]);
        DB::setDefaultConnection('notification_test_writer');
        $writer = DB::connection();
        try {
            $writer->beginTransaction();
            app(NotificationEvents::class)->created($actor, $notificationId);
            app(NotificationEvents::class)->changed($actor);
            $this->assertNull($listener->wait(1));
            $writer->commit();
            $this->assertSame([
                'kind' => 'notification-created', 'actor_uuid' => $actor->uuid, 'notification_id' => $notificationId,
            ], json_decode($listener->wait(1000), true, flags: JSON_THROW_ON_ERROR));
            $this->assertSame('notifications:' . $actor->uuid, $listener->wait(1000));
            $writer->beginTransaction();
            app(NotificationEvents::class)->created($actor, (string) Str::uuid());
            app(NotificationEvents::class)->changed($actor);
            $writer->rollBack();
            $this->assertNull($listener->wait(1));
        } finally {
            if ($writer->transactionLevel() > 0) {
                $writer->rollBack();
            }
            DB::setDefaultConnection($originalConnection);
            DB::purge('notification_test_writer');
            $listener->close();
        }
    }

    public function test_live_stream_filters_notification_recipients_and_keeps_legacy_operation_events(): void
    {
        $actor = UserFactory::new()->create();
        $otherActor = UserFactory::new()->create();
        $operation = LetterOperation::factory()->create(['actor_uuid' => $actor->uuid]);
        $otherOperation = LetterOperation::factory()->create(['actor_uuid' => $otherActor->uuid]);
        $notificationId = (string) Str::uuid();
        $otherNotificationId = (string) Str::uuid();
        $created = ['kind' => 'notification-created', 'actor_uuid' => $actor->uuid, 'notification_id' => $notificationId];
        $signals = [
            'notifications:' . $otherActor->uuid, 'notifications:' . $actor->uuid,
            'malformed', '{', 'null', '42', '[]', '{}',
            json_encode([...$created, 'actor_uuid' => $otherActor->uuid, 'notification_id' => $otherNotificationId]),
            json_encode([...$created, 'kind' => 'unknown']),
            json_encode([...$created, 'actor_uuid' => 'invalid']),
            json_encode([...$created, 'actor_uuid' => [$actor->uuid]]),
            json_encode([...$created, 'notification_id' => 'invalid']),
            json_encode([...$created, 'notification_id' => 42]),
            json_encode([...$created, 'notification_id' => null]),
            json_encode(['kind' => 'notification-created', 'actor_uuid' => $actor->uuid]),
            json_encode($created), $otherOperation->uuid, $operation->uuid,
        ];
        $endOfStream = new RuntimeException('End of test stream');
        config(['letters.sse.seconds' => 10]);
        $this->mock(LetterOperationListener::class, function (MockInterface $mock) use ($signals, $endOfStream): void {
            $mock->shouldReceive('listen')->once();
            foreach ($signals as $signal) {
                $mock->shouldReceive('wait')->once()->ordered()->andReturn($signal);
            }
            $mock->shouldReceive('wait')->once()->ordered()->andThrow($endOfStream);
            $mock->shouldReceive('close')->once();
        });
        $response = $this->actingAs($actor)->get(route('ltr.events'))->assertOk();

        ob_start();
        ob_start();
        try {
            $response->baseResponse->sendContent();
            $this->fail('The listener must terminate the test stream.');
        } catch (RuntimeException $exception) {
            $this->assertSame($endOfStream, $exception);
        } finally {
            $remaining = ob_get_clean();
            $content = ob_get_clean() . $remaining;
        }

        preg_match_all('/event: ([^\n]+)\ndata: ([^\n]+)\n\n/', $content, $frames);
        $this->assertSame(['operations', 'notifications', 'notifications', 'notifications', 'operations'], $frames[1]);
        $this->assertSame(['changed' => true, 'reason' => 'sync'], json_decode($frames[2][1], true, flags: JSON_THROW_ON_ERROR));
        $this->assertSame(['changed' => true, 'reason' => 'read'], json_decode($frames[2][2], true, flags: JSON_THROW_ON_ERROR));
        $this->assertSame(['changed' => true, 'reason' => 'created', 'notification_id' => $notificationId], json_decode($frames[2][3], true, flags: JSON_THROW_ON_ERROR));
        $live = json_decode($frames[2][4], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame([$operation->uuid], array_column($live['operations'], 'id'));
        $this->assertStringNotContainsString($otherOperation->uuid, $content);
        $this->assertStringNotContainsString($otherActor->uuid, $content);
        $this->assertStringNotContainsString($otherNotificationId, $content);
    }
}
