<?php

namespace App\Services\Letter;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class LetterHealth
{
    public function __construct(private DocumentFontRegistry $fonts, private LetterOperationListener $listener) {}

    public static function heartbeat(string $kind): void
    {
        Cache::store(config('letters.health.cache_store'))->put('letters.health.' . $kind, now()->timestamp, 600);
    }

    /** @return array<string, array{ok: bool, message: string}> */
    public function check(): array
    {
        $checks = [];
        $checks['runtime'] = $this->inspect(function (): string {
            if (PHP_OS_FAMILY !== 'Linux' || version_compare(PHP_VERSION, '8.4', '<') || !extension_loaded('pcntl')) {
                throw new RuntimeException('Gunakan PHP 8.4+ Linux/WSL2 dengan ekstensi pcntl untuk timeout worker.');
            }

            return 'PHP Linux dan timeout worker tersedia.';
        });
        $checks['postgres'] = $this->inspect(function (): string {
            $this->listener->probe();

            return 'PDO PostgreSQL langsung dan LISTEN/NOTIFY tersedia.';
        });
        $checks['fonts'] = $this->inspect(function (): string {
            $this->fonts->inventory(true);
            $report = $this->fonts->report([]);
            if (!$report['available'] || $report['fallback'] === null) {
                throw new RuntimeException('Fontconfig atau font fallback belum siap.');
            }
            foreach (['scan', 'cache'] as $command) {
                if (Process::env($this->fonts->environment())->timeout(10)->run([config('document-fonts.executables.' . $command), '--version'])->failed()) {
                    throw new RuntimeException('Executable Fontconfig ' . $command . ' belum siap.');
                }
            }

            return 'Fontconfig siap; fallback: ' . $report['fallback'] . '.';
        });
        $checks['converter'] = $this->inspect(function (): string {
            $result = Process::timeout(10)->run([config('document-fonts.executables.version'), '--version']);
            if ($result->failed() || !str_contains($result->output(), 'LibreOffice')) {
                throw new RuntimeException('LibreOffice belum siap. Word tetap tersedia.');
            }

            return trim($result->output());
        });
        $checks['storage'] = $this->inspect(function (): string {
            $disk = Storage::disk(config('letters.disk'));
            $path = config('letters.paths.staging') . '/health-' . Str::uuid();
            try {
                if (!$disk->put($path, 'letters-health') || $disk->get($path) !== 'letters-health' || !$disk->delete($path)) {
                    throw new RuntimeException('Storage Surat tidak dapat ditulis/dibaca/dibersihkan.');
                }
            } finally {
                $disk->delete($path);
            }

            return 'Storage privat dapat ditulis, dibaca, dan dibersihkan.';
        });
        $checks['queue'] = $this->inspect(function (): string {
            if (config('queue.connections.letters.driver') !== 'database' || config('queue.connections.letters.retry_after') <= config('letters.lease_seconds') || config('letters.lease_seconds') <= config('letters.job_timeout') || config('letters.job_timeout') <= config('letters.pdf.timeout')) {
                throw new RuntimeException('Periksa antrean database letters dan urutan timeout < lease < retry_after.');
            }
            $count = DB::table('jobs')->where('queue', 'letters')->count();

            return 'Antrean database letters tersedia; pekerjaan menunggu: ' . $count . '.';
        });
        foreach (['worker' => config('letters.health.worker_seconds'), 'scheduler' => config('letters.health.scheduler_seconds')] as $kind => $seconds) {
            $checks[$kind] = $this->inspect(function () use ($kind, $seconds): string {
                $stamp = Cache::store(config('letters.health.cache_store'))->get('letters.health.' . $kind);
                if (!is_int($stamp) || $stamp < now()->subSeconds($seconds)->timestamp) {
                    throw new RuntimeException($kind === 'worker' ? 'Heartbeat worker belum tersedia/usang. Jalankan ulang queue:work letters --queue=letters.' : 'Heartbeat recovery scheduler belum tersedia/usang. Jalankan schedule:work atau cron schedule:run.');
                }

                return 'Heartbeat ' . $kind . ' terbaru tersedia.';
            });
        }
        $checks['counters'] = $this->inspect(function (): string {
            if (Schema::hasColumn('letter_numbers', 'page_number') || Schema::hasColumn('letter_numbers', 'sequence_number')) {
                throw new RuntimeException('Transisi counter belum selesai. Jalankan letters:retire-counters --check lalu letters:retire-counters.');
            }

            return 'Counter operasional hanya berada pada periode.';
        });

        return $checks;
    }

    private function inspect(callable $check): array
    {
        try {
            return ['ok' => true, 'message' => $check()];
        } catch (Throwable $exception) {
            return ['ok' => false, 'message' => $exception instanceof RuntimeException ? $exception->getMessage() : 'Pemeriksaan belum berhasil; periksa konfigurasi dan log aplikasi.'];
        }
    }
}
