<?php

namespace App\Services\LetterTemplate;

use App\Models\MasterData\User;
use Closure;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class LetterTemplateExchangeStage
{
    /** @param array{manifest: array{files: array<string, array{size: int, sha256: string}>}, files: array<string, string>} $package */
    public function create(array $package, User $actor): string
    {
        return $this->locked(function () use ($package, $actor): string {
            $token = bin2hex(random_bytes(32));
            $root = $this->root($token);
            $disk = Storage::disk('local');
            try {
                foreach ($package['files'] as $name => $bytes) {
                    if (!$disk->put($root . '/' . $name, $bytes, 'private')) {
                        throw new RuntimeException('Berkas pemeriksaan impor gagal disimpan.');
                    }
                }
                $metadata = ['owner' => $actor->uuid, 'expires_at' => now()->addMinutes(30)->timestamp, 'manifest' => $package['manifest']];
                if (!$disk->put($root . '/stage.json', json_encode($metadata, JSON_THROW_ON_ERROR), 'private')) {
                    throw new RuntimeException('Pemeriksaan impor gagal disimpan.');
                }
            } catch (Throwable $exception) {
                $disk->deleteDirectory($root);
                throw $exception;
            }

            return $token;
        });
    }

    public function use(string $token, User $actor, Closure $callback): mixed
    {
        return $this->locked(function () use ($token, $actor, $callback): mixed {
            $root = $this->root($token);
            $disk = Storage::disk('local');
            abort_unless($disk->exists($root . '/stage.json'), 404);
            $stage = json_decode($disk->get($root . '/stage.json'), true, 40, JSON_THROW_ON_ERROR);
            abort_unless(hash_equals($stage['owner'], $actor->uuid), 404);
            if ($stage['expires_at'] <= now()->timestamp) {
                $disk->deleteDirectory($root);
                throw ValidationException::withMessages(['package' => 'Pemeriksaan impor kedaluwarsa. Unggah dan periksa paket kembali.']);
            }
            $files = [];
            foreach ($stage['manifest']['files'] as $name => $metadata) {
                if (!$disk->exists($root . '/' . $name) || $disk->size($root . '/' . $name) !== $metadata['size'] || hash_file('sha256', $disk->path($root . '/' . $name)) !== $metadata['sha256']) {
                    $disk->deleteDirectory($root);
                    throw ValidationException::withMessages(['package' => 'Berkas pemeriksaan berubah. Unggah paket kembali.']);
                }
                $files[$name] = $disk->path($root . '/' . $name);
            }

            return $callback($stage['manifest'], $files, $stage['expires_at']);
        });
    }

    /** Caller already holds the stage lock through use(). */
    public function delete(string $token): void
    {
        if (!Storage::disk('local')->deleteDirectory($this->root($token))) {
            throw new RuntimeException('Berkas pemeriksaan impor gagal dibersihkan.');
        }
    }

    public function cleanup(): int
    {
        return $this->locked(function (): int {
            $disk = Storage::disk('local');
            $deleted = 0;
            foreach ($disk->directories(config('letter-templates.exchange.directory')) as $root) {
                $path = $root . '/stage.json';
                $stage = $disk->exists($path) ? json_decode($disk->get($path), true) : null;
                if (($stage['expires_at'] ?? 0) <= now()->timestamp && $disk->deleteDirectory($root)) {
                    $deleted++;
                }
            }

            return $deleted;
        });
    }

    private function root(string $token): string
    {
        abort_unless(preg_match('/\A[a-f0-9]{64}\z/D', $token), 404);

        return config('letter-templates.exchange.directory') . '/' . hash('sha256', $token);
    }

    private function locked(Closure $callback): mixed
    {
        $disk = Storage::disk('local');
        $directory = config('letter-templates.exchange.directory');
        $disk->makeDirectory($directory);
        $lock = fopen($disk->path($directory . '/stage.lock'), 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            throw new RuntimeException('Penyimpanan pemeriksaan impor sedang digunakan.');
        }
        try {
            return $callback();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
