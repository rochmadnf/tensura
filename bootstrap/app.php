<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('letter-templates:cleanup-exchanges')->everyMinute()->withoutOverlapping(5);
        $schedule->command('letter-templates:cleanup')->hourly()->withoutOverlapping(60);
        $schedule->command('letters:cleanup')->hourly()->withoutOverlapping(60);
        $schedule->command('letters:recover-operations')->everyMinute()->withoutOverlapping(5);
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);
        $middleware->redirectGuestsTo(fn(Request $request) => $request->routeIs('login.index') ? null : route('login.index'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn(Request $request) => $request->is('api/*') || $request->expectsJson() || ($request->routeIs('wc.*', 'lt.values.resolve') && !$request->routeIs('wc.index')),
        );
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            if (!($request->routeIs('wc.*', 'lt.values.resolve') && !$request->routeIs('wc.index')) || $response->getStatusCode() < 400) {
                return $response;
            }
            $body = $response instanceof JsonResponse ? $response->getData(true) : [];
            if (isset($body['code'])) {
                return $response;
            }
            [$code, $message] = match ($response->getStatusCode()) {
                401 => ['unauthenticated', 'Sesi berakhir. Silakan masuk kembali.'],
                403 => ['forbidden', 'Anda tidak memiliki akses untuk tindakan ini.'],
                404 => ['not_found', 'Data tidak ditemukan.'],
                419 => ['session_expired', 'Sesi berakhir. Muat ulang halaman sebelum melanjutkan.'],
                422 => ['validation_failed', 'Periksa kembali isian.'],
                429 => ['rate_limited', 'Terlalu banyak permintaan. Tunggu sebelum mencoba kembali.'],
                default => ['server_error', 'Terjadi gangguan pada server. Silakan coba kembali.'],
            };
            $result = response()->json(['code' => $code, 'message' => $message, 'errors' => (object) ($body['errors'] ?? [])], $response->getStatusCode());
            if ($response->headers->has('Retry-After')) {
                $result->headers->set('Retry-After', $response->headers->get('Retry-After'));
            }

            return $result;
        });
    })->create();
