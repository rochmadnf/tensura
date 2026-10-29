<?php

namespace App\Services\Letter;

use App\Models\DocumentFont;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ChangeBundledDocumentFonts
{
    public function __construct(private DocumentFontPackage $package, private DocumentFontRegistry $registry, private InstallDocumentFont $installer, private InspectDocumentFontReferences $references, private DocumentFontFileTransaction $files) {}

    /**
     * @param  list<string>  $filenames
     * @return list<array{file: string, family: string, style: string, sha256: string, server: bool, webfont: bool}>
     */
    public function select(array $filenames): array
    {
        return $this->selectFrom($filenames, $this->package->manifest(refresh: true));
    }

    /**
     * @param  list<array{file: string, family: string, style: string, sha256: string, server: bool, webfont: bool}>  $expected
     */
    public function delete(array $expected, bool $force): int
    {
        return $this->package->synchronized(function () use ($expected, $force): int {
            $manifest = $this->package->manifest(refresh: true);
            $entries = $this->selectFrom(array_column($expected, 'file'), $manifest);
            if ($entries != $expected) {
                throw new RuntimeException('Paket font berubah sejak dipilih. Pilih dan konfirmasikan kembali.');
            }
            $usage = $this->references->inspect($entries);
            if (!$force && ($usage['used'] !== [] || $usage['incomplete'] !== [])) {
                throw new RuntimeException('Penghapusan diblokir: font digunakan atau pemeriksaan tidak lengkap. Gunakan --force setelah memeriksa dampaknya. ' . implode('; ', array_map(fn(array $row): string => implode(' · ', $row), [...$usage['used'], ...$usage['incomplete']])));
            }

            return $this->atomic(function () use ($entries, $manifest): int {
                $hashes = array_column($entries, 'sha256');
                $names = array_column($entries, 'file');
                $removed = array_map(fn(array $entry): string => $entry['family'] . ' · ' . $entry['style'], $entries);
                $manifest['fonts'] = array_values(array_filter($manifest['fonts'], fn(array $entry): bool => !in_array($entry['file'], $names, true)));
                $manifest['missing'] = array_values(array_filter($manifest['missing'], fn(string $missing): bool => !in_array($missing, $removed, true)));
                foreach ($entries as $entry) {
                    $this->files->remove(config('document-fonts.package_directory') . '/' . $entry['file']);
                    $this->removeActiveFile($entry['sha256'], pathinfo($entry['file'], PATHINFO_EXTENSION));
                }
                foreach ($this->bundledRows() as $font) {
                    if (in_array($font->sha256, $hashes, true)) {
                        $this->removeRow($font);
                    }
                }
                $this->files->write(config('document-fonts.package_directory') . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
                $this->package->manifest(refresh: true);

                return count($entries);
            });
        });
    }

    /** @return array{count: int, missing: list<string>, removed: list<string>} */
    public function sync(): array
    {
        return $this->package->synchronized(function (): array {
            $manifest = $this->package->manifest(refresh: true);
            $keys = [];
            $wanted = array_column($manifest['fonts'], 'sha256');
            foreach ($manifest['fonts'] as $entry) {
                $path = config('document-fonts.package_directory') . '/' . $entry['file'];
                if (!$entry['server'] || !is_file($path) || hash_file('sha256', $path) !== $entry['sha256']) {
                    throw new RuntimeException('Izin server, berkas, atau checksum font tidak valid: ' . $entry['file']);
                }
                $face = $this->installer->inspectFile($path, $entry['file']);
                if ($face['family'] !== $entry['family'] || $face['style'] !== $entry['style']) {
                    throw new RuntimeException('Metadata font tidak sesuai manifest: ' . $entry['file']);
                }
                $key = $this->registry->faceKey($face['family'], $face['style']);
                if (isset($keys[$key])) {
                    throw new RuntimeException('Konflik keluarga dan varian font: ' . $entry['family'] . ' · ' . $entry['style']);
                }
                $keys[$key] = true;
                foreach (DocumentFont::query()->where('face_key', $key)->where('sha256', '!=', $entry['sha256'])->get() as $conflict) {
                    if (!$this->package->isBundled($conflict) || in_array($conflict->sha256, $wanted, true)) {
                        throw new RuntimeException('Konflik keluarga dan varian font: ' . $entry['family'] . ' · ' . $entry['style']);
                    }
                }
            }

            return $this->atomic(function () use ($manifest, $wanted): array {
                $removed = [];
                foreach ($this->bundledRows() as $font) {
                    if (!in_array($font->sha256, $wanted, true)) {
                        $removed[] = $font->family . ' · ' . $font->style;
                        $this->removeRow($font);
                    }
                }
                $disk = Storage::disk(config('document-fonts.disk'));
                foreach ($disk->files(config('document-fonts.bundled_directory')) as $path) {
                    if (preg_match('/\A([a-f0-9]{64})\.(ttf|otf)\z/', basename($path), $match) === 1 && !in_array($match[1], $wanted, true)) {
                        $this->files->remove($disk->path($path));
                        $removed[] = basename($path);
                    }
                }
                foreach ($manifest['fonts'] as $entry) {
                    $source = config('document-fonts.package_directory') . '/' . $entry['file'];
                    $contents = file_get_contents($source);
                    if ($contents === false || hash('sha256', $contents) !== $entry['sha256']) {
                        throw new RuntimeException('Font paket berubah selama sinkronisasi: ' . $entry['file']);
                    }
                    $target = $this->activePath($entry['sha256'], pathinfo($entry['file'], PATHINFO_EXTENSION));
                    $this->files->capture($target);
                    $existing = DocumentFont::query()->where('sha256', $entry['sha256'])->first();
                    if ($existing !== null && !$this->package->isBundled($existing)) {
                        $uploadDisk = Storage::disk($existing->disk);
                        if ($existing->status !== 'ready' || !$uploadDisk->exists($existing->path) || hash_file('sha256', $uploadDisk->path($existing->path)) !== $existing->sha256) {
                            throw new RuntimeException('Font unggahan identik perlu diperbaiki sebelum sinkronisasi.');
                        }
                        $this->files->write($target, $contents);
                    } else {
                        $snapshot = config('document-fonts.runtime_directory') . '/sync-' . Str::uuid();
                        try {
                            if (!$disk->put($snapshot, $contents) || hash_file('sha256', $disk->path($snapshot)) !== $entry['sha256']) {
                                throw new RuntimeException('Salinan sinkronisasi font gagal disimpan.');
                            }
                            $this->installer->installFile($disk->path($snapshot), $entry['file'], bundled: true);
                        } finally {
                            $disk->delete($snapshot);
                        }
                    }
                }

                return ['count' => count($manifest['fonts']), 'missing' => $manifest['missing'], 'removed' => array_values(array_unique($removed))];
            });
        });
    }

    private function atomic(Closure $operation): mixed
    {
        return $this->installer->synchronized(function () use ($operation): mixed {
            try {
                $result = DB::transaction(function () use ($operation): mixed {
                    $result = $operation();
                    $this->registry->rebuild();
                    if (!$this->registry->inventory()['available']) {
                        throw new RuntimeException('Inventaris font belum dapat diverifikasi setelah perubahan.');
                    }

                    return $result;
                });
            } catch (Throwable $exception) {
                $errors = [];
                try {
                    $this->files->rollback();
                } catch (Throwable $recovery) {
                    $errors[] = $recovery->getMessage();
                }
                try {
                    $this->package->manifest(refresh: true);
                    $this->registry->rebuild();
                } catch (Throwable $recovery) {
                    $errors[] = 'Pemulihan cache/manifest gagal: ' . $recovery->getMessage();
                }
                if ($errors !== []) {
                    throw new RuntimeException($exception->getMessage() . ' ' . implode(' ', $errors), previous: $exception);
                }
                $this->files->cleanup();
                throw $exception;
            }
            $this->files->cleanup();

            return $result;
        });
    }

    /** @param list<string> $filenames
     * @param  array{fonts: list<array<string, mixed>>}  $manifest
     * @return list<array<string, mixed>>
     */
    private function selectFrom(array $filenames, array $manifest): array
    {
        $entries = [];
        foreach (array_unique($filenames) as $filename) {
            $entry = collect($manifest['fonts'])->firstWhere('file', $filename);
            if ($entry === null) {
                throw new RuntimeException('Font tidak tercantum dalam paket: ' . $filename);
            }
            $entries[] = $entry;
        }
        if ($entries === []) {
            throw new RuntimeException('Pilih setidaknya satu font paket.');
        }

        return $entries;
    }

    /** @return Collection<int, DocumentFont> */
    private function bundledRows(): Collection
    {
        return DocumentFont::query()->where('disk', config('document-fonts.disk'))->where('path', 'like', config('document-fonts.bundled_directory') . '/%')->get()->filter(fn(DocumentFont $font): bool => $this->package->isBundled($font));
    }

    private function removeRow(DocumentFont $font): void
    {
        $expected = config('document-fonts.bundled_directory') . '/' . $font->sha256 . '.' . $font->extension;
        if ($font->path !== $expected || !in_array($font->extension, ['ttf', 'otf'], true)) {
            throw new RuntimeException('Lokasi salinan font paket tidak dikenal: ' . $font->uuid);
        }
        $this->removeActiveFile($font->sha256, $font->extension);
        $font->delete();
    }

    private function activePath(string $hash, string $extension): string
    {
        if (preg_match('/\A[a-f0-9]{64}\z/', $hash) !== 1 || !in_array($extension, ['ttf', 'otf'], true)) {
            throw new RuntimeException('Identitas salinan font paket tidak valid.');
        }
        $disk = Storage::disk(config('document-fonts.disk'));
        $disk->makeDirectory(config('document-fonts.bundled_directory'));

        return $disk->path(config('document-fonts.bundled_directory') . '/' . $hash . '.' . $extension);
    }

    private function removeActiveFile(string $hash, string $extension): void
    {
        $this->files->remove($this->activePath($hash, $extension));
    }
}
