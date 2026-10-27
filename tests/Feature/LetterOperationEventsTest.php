<?php

namespace Tests\Feature;

use App\Enums\LetterOperationStatusEnum as Status;
use App\Models\LetterOperation;
use App\Services\Letter\LetterOperationEvents;
use App\Services\Letter\LetterOperationListener;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        $this->assertStringNotContainsString('source_path', $content);
    }

    public function test_status_and_notification_roll_back_together(): void
    {
        $operation = LetterOperation::factory()->create();
        DB::beginTransaction();
        app(LetterOperationEvents::class)->transition($operation, Status::SUCCEEDED, 'Ready');
        DB::rollBack();
        $this->assertSame(Status::QUEUED, $operation->fresh()->status);
        $this->assertSame(1, $operation->fresh()->status_revision);
    }
}
