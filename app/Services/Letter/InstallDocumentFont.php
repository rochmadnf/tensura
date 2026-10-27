<?php

namespace App\Services\Letter;

use App\Models\DocumentFont;
use App\Models\MasterData\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class InstallDocumentFont
{
    public function __construct(private DocumentFontRegistry $registry) {}

    public function install(UploadedFile $upload, User $actor): DocumentFont
    {
        $diskName = config('document-fonts.disk');
        $disk = Storage::disk($diskName);
        $directory = config('document-fonts.directory');
        $disk->makeDirectory($directory);
        $disk->makeDirectory(config('document-fonts.runtime_directory'));
        $lock = fopen($disk->path(config('document-fonts.runtime_directory') . '/install.lock'), 'c');
        if ($lock === false) {
            throw ValidationException::withMessages(['font' => 'Penyimpanan font belum dapat ditulis.']);
        }
        $staging = null;
        $installed = null;
        try {
            if (!flock($lock, LOCK_EX)) {
                $this->reject('Instalasi font sedang digunakan. Coba lagi.');
            }
            $signature = file_get_contents($upload->getRealPath(), false, null, 0, 4);
            if (!in_array($signature, ["\x00\x01\x00\x00", 'OTTO', 'true'], true)) {
                $this->reject('Isi berkas bukan font TTF/OTF yang valid.');
            }
            $extension = strtolower($upload->getClientOriginalExtension());
            if (($signature === 'OTTO') !== ($extension === 'otf')) {
                $this->reject('Ekstensi berkas tidak cocok dengan format font.');
            }
            $hash = hash_file('sha256', $upload->getRealPath());
            $existing = DocumentFont::query()->where('sha256', $hash)->first();
            if ($existing !== null && $disk->exists($existing->path) && hash_file('sha256', $disk->path($existing->path)) === $hash) {
                return $existing;
            }
            $staging = config('document-fonts.runtime_directory') . '/' . Str::uuid() . '.' . $extension;
            if (!$disk->put($staging, file_get_contents($upload->getRealPath()))) {
                $this->reject('Font gagal disimpan.');
            }
            try {
                $face = $this->registry->scan($disk->path($staging));
            } catch (Throwable $exception) {
                report($exception);
                $this->reject('Font tidak dapat dibaca. Gunakan satu varian TTF/OTF statis yang valid.');
            }
            $key = $this->registry->faceKey($face['family'], $face['style']);
            $inventory = $this->registry->inventory(true);
            if (!$inventory['available']) {
                $this->reject('Font server belum dapat diperiksa. Coba lagi setelah Fontconfig tersedia.');
            }
            if (DocumentFont::query()->where('face_key', $key)->where('sha256', '!=', $hash)->exists()) {
                $this->reject('Keluarga dan varian font ini sudah memiliki berkas berbeda. Font lama tidak diganti.');
            }
            foreach ($inventory['faces'] as $installedFace) {
                if ($this->registry->faceKey($installedFace['family'], $installedFace['style']) === $key && hash_file('sha256', $installedFace['path']) !== $hash) {
                    $this->reject('Keluarga dan varian ini sudah tersedia dengan berkas berbeda di server. Font lama tidak diganti.');
                }
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
                'disk' => $diskName, 'path' => $installed, 'extension' => $extension, 'size' => $disk->size($installed), 'uploaded_by' => $actor->id,
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
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['font' => $message]);
    }
}
