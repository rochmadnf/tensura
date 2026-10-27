<?php

namespace App\Http\Controllers;

use App\Http\Resources\LetterOperationResource;
use App\Models\Letter;
use App\Models\LetterOperation;
use App\Services\Letter\LetterOperationEvents;
use App\Services\Letter\LetterOperationListener;
use App\Services\Notification\NotificationEvents;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class LetterOperationController extends Controller
{
    public function show(Request $request, Letter $letter, LetterOperation $operation): JsonResponse
    {
        abort_unless($operation->letter_id === $letter->id && ($operation->actor_uuid === $request->user()->uuid || DB::table('letter_pdf_requests')->where('letter_operation_id', $operation->id)->where('actor_uuid', $request->user()->uuid)->exists()), 404);

        return response()->json(['operation' => (new LetterOperationResource($operation))->resolve()]);
    }

    public function stream(Request $request, LetterOperationListener $listener, LetterOperationEvents $events): StreamedResponse
    {
        try {
            $listener->listen();
        } catch (Throwable $exception) {
            report($exception);
            abort(503, 'Notifikasi Surat belum dapat tersambung.');
        }
        $actor = $request->user()->uuid;
        $request->session()->save();

        return response()->stream(function () use ($listener, $events, $actor): void {
            $send = static function (string $event, array $data): void {
                echo 'event: ' . $event . "\n" . 'data: ' . json_encode($data, JSON_THROW_ON_ERROR) . "\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            };
            try {
                $send('operations', ['operations' => $events->snapshot($actor)]);
                $send('notifications', ['changed' => true, 'reason' => 'sync']);
                $deadline = microtime(true) + config('letters.sse.seconds');
                while (!connection_aborted() && microtime(true) < $deadline) {
                    $milliseconds = (int) min(config('letters.sse.heartbeat_seconds') * 1000, max(1, ($deadline - microtime(true)) * 1000));
                    $signal = $listener->wait($milliseconds);
                    if ($signal !== null) {
                        if (str_starts_with($signal, NotificationEvents::PREFIX)) {
                            if ($signal === NotificationEvents::PREFIX . $actor) {
                                $send('notifications', ['changed' => true, 'reason' => 'read']);
                            }
                        } elseif (Str::isUuid($signal)) {
                            $operations = $events->snapshot($actor, $signal);
                            if ($operations !== []) {
                                $send('operations', ['operations' => $operations]);
                            }
                        } else {
                            $envelope = json_decode($signal, true, 8);
                            if (is_array($envelope)
                                && ($envelope['kind'] ?? null) === 'notification-created'
                                && is_string($envelope['actor_uuid'] ?? null)
                                && Str::isUuid($envelope['actor_uuid'])
                                && $envelope['actor_uuid'] === $actor
                                && is_string($envelope['notification_id'] ?? null)
                                && Str::isUuid($envelope['notification_id'])) {
                                $send('notifications', ['changed' => true, 'reason' => 'created', 'notification_id' => $envelope['notification_id']]);
                            }
                        }
                    } else {
                        echo ": heartbeat\n\n";
                        if (ob_get_level() > 0) {
                            ob_flush();
                        } flush();
                    }
                }
            } finally {
                $listener->close();
            }
        }, 200, ['Content-Type' => 'text/event-stream', 'Cache-Control' => 'private, no-cache, no-store', 'X-Accel-Buffering' => 'no']);
    }
}
