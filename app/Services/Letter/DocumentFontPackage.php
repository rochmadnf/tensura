<?php

namespace App\Services\Letter;

use App\Models\DocumentFont;
use Closure;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class DocumentFontPackage
{
    private ?array $manifest = null;

    /** @return array{schema_version: int, missing: list<string>, fonts: list<array{file: string, family: string, style: string, sha256: string, server: bool, webfont: bool}>} */
    public function manifest(bool $refresh = false): array
    {
        if (!$refresh && $this->manifest !== null) {
            return $this->manifest;
        }
        $path = config('document-fonts.package_directory') . '/manifest.json';
        if (!is_file($path)) {
            throw new RuntimeException('Manifest paket font belum tersedia.');
        }
        $manifest = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        Validator::make(is_array($manifest) ? $manifest : [], [
            'schema_version' => ['required', 'integer', 'in:1'],
            'missing' => ['present', 'array'], 'missing.*' => ['string'],
            'fonts' => ['present', 'array'],
            'fonts.*.file' => ['required', 'string', 'distinct', 'regex:/\A[a-zA-Z0-9_-]+\.(ttf|otf)\z/'],
            'fonts.*.family' => ['required', 'string', 'max:255'],
            'fonts.*.style' => ['required', 'string', 'in:Regular,Bold,Italic,Bold Italic'],
            'fonts.*.sha256' => ['required', 'string', 'distinct', 'regex:/\A[a-f0-9]{64}\z/'],
            'fonts.*.server' => ['required', 'boolean'],
            'fonts.*.webfont' => ['required', 'boolean'],
        ])->validate();

        return $this->manifest = $manifest;
    }

    public function synchronized(Closure $operation): mixed
    {
        $disk = Storage::disk(config('document-fonts.disk'));
        $runtime = config('document-fonts.runtime_directory');
        $disk->makeDirectory($runtime);
        $directory = config('document-fonts.package_directory');
        $lock = fopen($disk->path($runtime . '/package-' . hash('sha256', realpath($directory) ?: $directory) . '.lock'), 'c');
        if ($lock === false) {
            throw new RuntimeException('Kunci paket font belum dapat dibuat.');
        }
        try {
            if (!flock($lock, LOCK_EX | LOCK_NB)) {
                throw new RuntimeException('Paket font sedang diperbarui. Coba lagi.');
            }

            return $operation();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @return array<string, mixed>|null */
    public function entry(DocumentFont $font): ?array
    {
        if (!$this->isBundled($font)) {
            return null;
        }
        foreach ($this->manifest()['fonts'] as $entry) {
            if ($entry['sha256'] === $font->sha256) {
                return $entry;
            }
        }

        return null;
    }

    public function isBundled(DocumentFont $font): bool
    {
        return $font->disk === config('document-fonts.disk') && str_starts_with($font->path, config('document-fonts.bundled_directory') . '/');
    }

    public function browserAllowed(DocumentFont $font): bool
    {
        return !$this->isBundled($font) || (($this->entry($font)['webfont'] ?? false) && ($this->entry($font)['server'] ?? false));
    }
}
