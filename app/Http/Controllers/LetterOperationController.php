<?php

namespace App\Http\Controllers;

use App\Http\Resources\LetterOperationResource;
use App\Models\Letter;
use App\Models\LetterOperation;
use App\Services\Letter\LetterOperationEvents;
use App\Services\Letter\LetterOperationListener;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class LetterOperationController extends Controller
{
    public function show(Request $request, Letter $letter, LetterOperation $operation): JsonResponse
    {
        abort_unless($operation->letter_id === $letter->id && $operation->actor_uuid === $request->user()->uuid, 404);

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
            $send = static function (array $operations): void {
                echo 'event: operations' . "\n" . 'data: ' . json_encode(['operations' => $operations], JSON_THROW_ON_ERROR) . "\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            };
            try {
                $send($events->snapshot($actor));
                $deadline = microtime(true) + config('letters.sse.seconds');
                while (!connection_aborted() && microtime(true) < $deadline) {
                    $milliseconds = (int) min(config('letters.sse.heartbeat_seconds') * 1000, max(1, ($deadline - microtime(true)) * 1000));
                    $operation = $listener->wait($milliseconds);
                    if ($operation !== null) {
                        $send($events->snapshot($actor, $operation));
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
