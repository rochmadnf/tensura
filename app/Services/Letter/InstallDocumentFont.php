<?php

namespace App\Services\Letter;

use App\Models\DocumentFont;
use App\Models\MasterData\User;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class InstallDocumentFont
{
    private bool $locked = false;

    public function __construct(private DocumentFontRegistry $registry) {}

    public function install(UploadedFile $upload, User $actor): DocumentFont
    {
        return $this->installFile($upload->getRealPath(), $upload->getClientOriginalName(), $actor);
    }

    /** @param list<array{path: string, filename: string}> $files */
    public function atomic(array $files, User $actor, Closure $save): mixed
    {
        $disk = Storage::disk(config('document-fonts.disk'));
        $disk->makeDirectory(config('document-fonts.runtime_directory'));
        $lock = fopen($disk->path(config('document-fonts.runtime_directory') . '/install.lock'), 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            $this->reject('Instalasi font sedang digunakan. Coba lagi.');
        }
        $created = [];
        try {
            return DB::transaction(function () use ($files, $actor, $save, &$created): mixed {
                foreach ($files as $file) {
                    $hash = hash_file('sha256', $file['path']);
                    $existing = DocumentFont::query()->where('sha256', $hash)->first();
                    if ($existing !== null && ($existing->status !== 'ready' || $existing->disk !== config('document-fonts.disk') || !Storage::disk($existing->disk)->exists($existing->path) || hash_file('sha256', Storage::disk($existing->disk)->path($existing->path)) !== $hash)) {
                        $this->reject('Font identik yang terdaftar perlu diperbaiki sebelum impor.');
                    }
                    $font = $this->installUnlocked($file['path'], $file['filename'], $actor);
                    if ($existing === null) {
                        $created[] = ['disk' => $font->disk, 'path' => $font->path];
                    }
                }

                return $save();
            });
        } catch (Throwable $exception) {
            foreach ($created as $file) {
                Storage::disk($file['disk'])->delete($file['path']);
            }
            $this->registry->inventory(true);
            throw $exception;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @return array{family: string, style: string, aliases: list<string>, bold: bool, italic: bool, path: string, index: int} */
    public function inspectFile(string $source, string $filename): array
    {
        if (!is_file($source) || filesize($source) > config('document-fonts.max_upload_kb') * 1024) {
            $this->reject('Ukuran font melewati batas unggahan.');
        }
        $signature = file_get_contents($source, false, null, 0, 4);
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($signature, ["\x00\x01\x00\x00", 'OTTO', 'true'], true) || !in_array($extension, ['ttf', 'otf'], true) || ($signature === 'OTTO') !== ($extension === 'otf')) {
            $this->reject('Isi atau ekstensi berkas font TTF/OTF tidak valid.');
        }
        try {
            return $this->registry->scan($source);
        } catch (Throwable $exception) {
            report($exception);
            $this->reject('Font tidak dapat dibaca. Gunakan satu varian TTF/OTF statis yang valid.');
        }
    }

    public function synchronized(Closure $operation): mixed
    {
        if ($this->locked) {
            return $operation();
        }
        $disk = Storage::disk(config('document-fonts.disk'));
        $disk->makeDirectory(config('document-fonts.runtime_directory'));
        $lock = fopen($disk->path(config('document-fonts.runtime_directory') . '/install.lock'), 'c');
        if ($lock === false) {
            $this->reject('Kunci instalasi font belum dapat dibuat.');
        }
        try {
            if (!flock($lock, LOCK_EX)) {
                $this->reject('Instalasi font sedang digunakan. Coba lagi.');
            }
            $this->locked = true;

            return $operation();
        } finally {
            $this->locked = false;
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function installFile(string $source, string $filename, ?User $actor = null, bool $bundled = false): DocumentFont
    {
        return $this->synchronized(fn(): DocumentFont => $this->installUnlocked($source, $filename, $actor, $bundled));
    }

    private function installUnlocked(string $source, string $filename, ?User $actor = null, bool $bundled = false): DocumentFont
    {
        $diskName = config('document-fonts.disk');
        $disk = Storage::disk($diskName);
        $directory = config($bundled ? 'document-fonts.bundled_directory' : 'document-fonts.directory');
        $disk->makeDirectory($directory);
        $disk->makeDirectory(config('document-fonts.runtime_directory'));
        $staging = null;
        $installed = null;
        try {
            $face = $this->inspectFile($source, $filename);
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $hash = hash_file('sha256', $source);
            $existing = DocumentFont::query()->where('sha256', $hash)->first();
            if ($existing !== null && $existing->status === 'ready' && $existing->disk === $diskName && (!$bundled || str_starts_with($existing->path, $directory . '/')) && $disk->exists($existing->path) && hash_file('sha256', $disk->path($existing->path)) === $hash) {
                return $existing;
            }
            $staging = config('document-fonts.runtime_directory') . '/' . Str::uuid() . '.' . $extension;
            if (!$disk->put($staging, file_get_contents($source))) {
                $this->reject('Font gagal disimpan.');
            }
            $key = $this->registry->faceKey($face['family'], $face['style']);
            $inventory = $this->registry->inventory(true);
            if (!$inventory['available']) {
                $this->reject('Font server belum dapat diperiksa. Coba lagi setelah Fontconfig tersedia.');
            }
            if (DocumentFont::query()->where('face_key', $key)->where('sha256', '!=', $hash)->exists()) {
                $this->reject('Keluarga dan varian font ini sudah memiliki berkas berbeda. Font lama tidak diganti.');
            }
            $installed = $directory . '/' . $hash . '.' . $extension;
            if (!$disk->move($staging, $installed)) {
                $this->reject('Font gagal dipasang.');
            }
            $staging = null;
            $this->registry->rebuild();
            $visible = collect($this->registry->inventory()['faces'])->contains(fn(array $candidate): bool => realpath($candidate['path']) === realpath($disk->path($installed)) && $candidate['index'] === 0);
            if (!$visible) {
                $this->reject('Font belum terbaca oleh lingkungan konversi. Instalasi dibatalkan.');
            }
            $font = DB::transaction(fn(): DocumentFont => DocumentFont::query()->updateOrCreate(['sha256' => $hash], [
                'family' => $face['family'], 'style' => $face['style'], 'face_key' => $key,
                'is_bold' => $face['bold'], 'is_italic' => $face['italic'], 'status' => 'ready',
                'disk' => $diskName, 'path' => $installed, 'extension' => $extension, 'size' => $disk->size($installed), 'uploaded_by' => $actor?->id,
            ]), 5);
            $installed = null;

            return $font;
        } catch (Throwable $exception) {
            if ($exception instanceof ValidationException) {
                throw $exception;
            }
            report($exception);
            $this->reject('Font belum dapat dipasang atau diverifikasi oleh server. Coba lagi.');
        } finally {
            foreach ([$staging, $installed] as $path) {
                if ($path !== null) {
                    $disk->delete($path);
                }
            }
            if ($installed !== null) {
                $this->registry->inventory(true);
            }
        }
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['font' => $message]);
    }
}
