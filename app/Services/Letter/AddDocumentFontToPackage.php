<?php

namespace App\Services\Letter;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AddDocumentFontToPackage
{
    public function __construct(private DocumentFontPackage $package, private DocumentFontRegistry $registry, private Filesystem $files) {}

    /** @return array{file: string, family: string, style: string, sha256: string, server: bool, webfont: bool} */
    public function add(string $source, bool $webfont): array
    {
        $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));
        if (!is_file($source) || !is_readable($source) || !in_array($extension, ['ttf', 'otf'], true)) {
            throw new RuntimeException('Pilih berkas font TTF/OTF yang dapat dibaca.');
        }
        if (filesize($source) > config('document-fonts.max_upload_kb') * 1024) {
            throw new RuntimeException('Ukuran font melewati batas konfigurasi aplikasi.');
        }
        $contents = $this->files->get($source);
        $signature = substr($contents, 0, 4);
        if (!in_array($signature, ["\x00\x01\x00\x00", 'OTTO', 'true'], true) || ($signature === 'OTTO') !== ($extension === 'otf')) {
            throw new RuntimeException('Isi atau ekstensi font tidak sesuai format TTF/OTF.');
        }
        $disk = Storage::disk(config('document-fonts.disk'));
        $runtime = config('document-fonts.runtime_directory');
        $disk->makeDirectory($runtime);
        $directory = config('document-fonts.package_directory');
        $lockPath = $disk->path($runtime . '/package-' . hash('sha256', realpath($directory) ?: $directory) . '.lock');
        $lock = fopen($lockPath, 'c');
        if ($lock === false) {
            throw new RuntimeException('Kunci paket font belum dapat dibuat.');
        }
        $snapshot = null;
        try {
            if (!flock($lock, LOCK_EX | LOCK_NB)) {
                throw new RuntimeException('Paket font sedang diperbarui. Coba lagi.');
            }
            $manifest = $this->package->manifest(refresh: true);
            $snapshot = $disk->path($runtime . '/' . Str::uuid() . '.' . $extension);
            if ($this->files->put($snapshot, $contents) !== strlen($contents)) {
                throw new RuntimeException('Salinan sementara font gagal disimpan.');
            }
            $face = $this->registry->scan($snapshot);
            if (!in_array($face['style'], ['Regular', 'Bold', 'Italic', 'Bold Italic'], true)) {
                throw new RuntimeException('Varian font belum didukung. Gunakan Regular, Bold, Italic, atau Bold Italic.');
            }
            $hash = hash('sha256', $contents);
            foreach ($manifest['fonts'] as $entry) {
                if ($entry['sha256'] === $hash) {
                    $path = $directory . '/' . $entry['file'];
                    if (!is_file($path) || hash_file('sha256', $path) !== $hash) {
                        throw new RuntimeException('Berkas font dalam paket hilang atau berubah. Pulihkan berkas sebelum menambah ulang.');
                    }

                    return $entry;
                }
                if ($this->registry->faceKey($entry['family'], $entry['style']) === $this->registry->faceKey($face['family'], $face['style'])) {
                    throw new RuntimeException('Keluarga dan varian sudah memiliki berkas berbeda. Font lama tidak diganti.');
                }
            }
            $filename = Str::substr(Str::slug($face['family'] . '-' . $face['style']), 0, 240) . '.' . $extension;
            if (preg_match('/\A[a-zA-Z0-9_-]+\.(ttf|otf)\z/', $filename) !== 1) {
                throw new RuntimeException('Nama keluarga dan varian tidak dapat dijadikan nama berkas yang aman.');
            }
            if (collect($manifest['fonts'])->contains('file', $filename)) {
                throw new RuntimeException('Nama berkas sudah digunakan oleh font lain dalam manifest.');
            }
            $entry = ['file' => $filename, 'family' => $face['family'], 'style' => $face['style'], 'sha256' => $hash, 'server' => true, 'webfont' => $webfont];
            $manifest['fonts'][] = $entry;
            usort($manifest['fonts'], fn(array $a, array $b): int => [$a['family'], $a['style'], $a['file']] <=> [$b['family'], $b['style'], $b['file']]);
            $manifest['missing'] = array_values(array_filter($manifest['missing'], fn(string $missing): bool => $missing !== $face['family'] . ' · ' . $face['style']));
            $path = $directory . '/' . $filename;
            $created = false;
            try {
                if (file_exists($path)) {
                    if (!is_file($path) || hash_file('sha256', $path) !== $hash) {
                        throw new RuntimeException('Nama berkas tujuan sudah memiliki isi berbeda. Berkas lama tidak diganti.');
                    }
                } else {
                    $this->writeAtomic($path, $contents);
                    $created = true;
                }
                $this->writeAtomic($directory . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
                $this->package->manifest(refresh: true);
            } catch (Throwable $exception) {
                if ($created) {
                    $this->files->delete($path);
                }
                throw $exception;
            }

            return $entry;
        } finally {
            if ($snapshot !== null) {
                $this->files->delete($snapshot);
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function writeAtomic(string $path, string $contents): void
    {
        $temporary = tempnam(dirname($path), '.font-');
        if ($temporary === false) {
            throw new RuntimeException('Berkas sementara paket font gagal dibuat.');
        }
        try {
            if ($this->files->put($temporary, $contents) !== strlen($contents) || !chmod($temporary, 0666 & ~umask()) || !$this->files->move($temporary, $path)) {
                throw new RuntimeException('Paket font gagal disimpan. Periksa izin penyimpanan.');
            }
        } finally {
            if (file_exists($temporary)) {
                $this->files->delete($temporary);
            }
        }
    }
}
