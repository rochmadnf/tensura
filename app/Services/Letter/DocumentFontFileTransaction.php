<?php

namespace App\Services\Letter;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DocumentFontFileTransaction
{
    /** @var array<string, string|null> */
    private array $backups = [];

    private ?string $directory = null;

    public function __construct(private Filesystem $files) {}

    public function capture(string $path): void
    {
        if (array_key_exists($path, $this->backups)) {
            return;
        }
        if (is_link($path) || (file_exists($path) && !is_file($path))) {
            throw new RuntimeException('Tujuan perubahan font bukan berkas biasa: ' . basename($path));
        }
        if (!file_exists($path)) {
            $this->backups[$path] = null;

            return;
        }
        if ($this->directory === null) {
            $disk = Storage::disk(config('document-fonts.disk'));
            $relative = config('document-fonts.runtime_directory') . '/changes-' . Str::uuid();
            $disk->makeDirectory($relative);
            $this->directory = $disk->path($relative);
        }
        $backup = $this->directory . '/' . count($this->backups);
        if (!$this->files->copy($path, $backup) || hash_file('sha256', $backup) !== hash_file('sha256', $path)) {
            throw new RuntimeException('Salinan pemulihan font gagal dibuat.');
        }
        $this->backups[$path] = $backup;
    }

    public function remove(string $path): void
    {
        $this->capture($path);
        if (file_exists($path) && !$this->files->delete($path)) {
            throw new RuntimeException('Berkas font gagal dihapus: ' . basename($path));
        }
    }

    public function write(string $path, string $contents): void
    {
        $this->capture($path);
        $this->replace($path, $contents);
    }

    public function rollback(): void
    {
        $errors = [];
        foreach (array_reverse($this->backups, true) as $path => $backup) {
            try {
                if ($backup === null) {
                    if (file_exists($path) && !$this->files->delete($path)) {
                        throw new RuntimeException('Berkas baru gagal dibersihkan: ' . basename($path));
                    }
                } else {
                    $this->replace($path, $this->files->get($backup));
                }
            } catch (Throwable $exception) {
                $errors[] = $exception->getMessage();
            }
        }
        if ($errors !== []) {
            throw new RuntimeException('Pemulihan berkas gagal: ' . implode(' ', $errors) . ' Salinan pemulihan: ' . $this->directory);
        }
    }

    public function cleanup(): void
    {
        if ($this->directory !== null && !$this->files->deleteDirectory($this->directory)) {
            throw new RuntimeException('Salinan pemulihan belum dapat dibersihkan: ' . $this->directory);
        }
        $this->backups = [];
        $this->directory = null;
    }

    private function replace(string $path, string $contents): void
    {
        $temporary = tempnam(dirname($path), '.font-');
        if ($temporary === false) {
            throw new RuntimeException('Berkas sementara font gagal dibuat.');
        }
        try {
            if ($this->files->put($temporary, $contents) !== strlen($contents) || !chmod($temporary, 0666 & ~umask()) || !$this->files->move($temporary, $path)) {
                throw new RuntimeException('Perubahan berkas font gagal disimpan: ' . basename($path));
            }
        } finally {
            if (file_exists($temporary)) {
                $this->files->delete($temporary);
            }
        }
    }
}
