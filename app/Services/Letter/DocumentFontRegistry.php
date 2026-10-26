<?php

namespace App\Services\Letter;

use App\Models\DocumentFont;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class DocumentFontRegistry
{
    private const string FORMAT = "%{family}\t%{style}\t%{weight}\t%{slant}\t%{file}\t%{index}\n";

    private ?array $inventory = null;

    private array $hashes = [];

    private ?Collection $managed = null;

    private ?array $fallbacks = null;

    private ?string $previewRenderer = null;

    public function freeze(): self
    {
        $copy = clone $this;
        $copy->inventory(true);
        $copy->managed = DocumentFont::query()->where('status', 'ready')->get()->keyBy('sha256');
        $copy->fallbacks = config('document-fonts.fallbacks');
        $copy->previewRenderer = config('document-fonts.preview_renderer');

        return $copy;
    }

    /**
     * Reused by Fontconfig inspection and the future PDF worker. No global/system installation.
     *
     * @return array<string, string>
     */
    public function environment(array $substitutions = []): array
    {
        $disk = Storage::disk(config('document-fonts.disk'));
        $runtime = config('document-fonts.runtime_directory');
        $disk->makeDirectory($runtime . '/cache');
        $disk->makeDirectory(config('document-fonts.directory'));
        $escape = fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $xml = '<?xml version="1.0"?><fontconfig><include ignore_missing="no">' . $escape(config('document-fonts.system_config')) . '</include><dir>' . $escape($disk->path(config('document-fonts.directory'))) . '</dir><cachedir>' . $escape($disk->path($runtime . '/cache')) . '</cachedir>';
        foreach ($substitutions as $original => $replacement) {
            $xml .= '<alias binding="strong"><family>' . $escape($original) . '</family><prefer><family>' . $escape($replacement) . '</family></prefer></alias>';
        }
        $xml .= '</fontconfig>';
        $path = $runtime . '/' . hash('sha256', $xml) . '.conf';
        if (!$disk->exists($path)) {
            $temporary = $runtime . '/' . bin2hex(random_bytes(16)) . '.tmp';
            try {
                if (!$disk->put($temporary, $xml) || !$disk->move($temporary, $path)) {
                    throw new RuntimeException('Konfigurasi font aplikasi gagal disimpan.');
                }
            } finally {
                $disk->delete($temporary);
            }
        }

        return ['FONTCONFIG_FILE' => $disk->path($path), 'XDG_CACHE_HOME' => $disk->path($runtime . '/cache')];
    }

    /** @return array{available: bool, faces: list<array>, warning: ?string} */
    public function inventory(bool $refresh = false): array
    {
        if (!$refresh && $this->inventory !== null) {
            return $this->inventory;
        }
        $this->hashes = [];
        try {
            $result = Process::env($this->environment())->timeout(config('document-fonts.timeout_seconds'))->run([config('document-fonts.executables.list'), '--format', self::FORMAT]);
            if ($result->failed()) {
                throw new RuntimeException('Fontconfig inventory failed.');
            }

            return $this->inventory = ['available' => true, 'faces' => $this->parse($result->output()), 'warning' => null];
        } catch (Throwable $exception) {
            report($exception);

            return $this->inventory = ['available' => false, 'faces' => [], 'warning' => 'Daftar font server belum dapat diperiksa. Generate dan unduh DOCX tetap tersedia.'];
        }
    }

    /** @return array{family: string, style: string, aliases: list<string>, bold: bool, italic: bool, path: string, index: int} */
    public function scan(string $path): array
    {
        $result = Process::env($this->environment())->timeout(config('document-fonts.timeout_seconds'))->run([config('document-fonts.executables.scan'), '--format', self::FORMAT, '--', $path]);
        $faces = $this->parse($result->output());
        if ($result->failed() || count($faces) !== 1 || $faces[0]['index'] !== 0) {
            throw new RuntimeException('Font tidak terbaca sebagai satu varian TTF/OTF. Gunakan berkas font statis yang valid.');
        }

        return $faces[0];
    }

    public function rebuild(): void
    {
        $result = Process::env($this->environment())->timeout(config('document-fonts.timeout_seconds'))->run([config('document-fonts.executables.cache'), '-f', Storage::disk(config('document-fonts.disk'))->path(config('document-fonts.directory'))]);
        if ($result->failed()) {
            throw new RuntimeException('Cache font gagal diperbarui.');
        }
        $this->inventory(true);
    }

    /** @return list<array> */
    private function parse(string $output): array
    {
        $faces = [];
        foreach (explode("\n", trim($output)) as $line) {
            $parts = explode("\t", $line);
            if (count($parts) !== 6 || !is_numeric($parts[2]) || !is_numeric($parts[3]) || !ctype_digit($parts[5])) {
                continue;
            }
            $families = array_values(array_filter(array_map('trim', explode(',', $parts[0]))));
            $style = trim(explode(',', $parts[1])[0]);
            if ($families === [] || $style === '' || strlen($families[0]) > 255 || strlen($style) > 255 || !is_file($parts[4])) {
                continue;
            }
            $faces[] = ['family' => $families[0], 'aliases' => $families, 'style' => $style, 'weight' => (float) $parts[2], 'bold' => (float) $parts[2] >= 180, 'italic' => (float) $parts[3] > 0, 'path' => $parts[4], 'index' => (int) $parts[5]];
        }
        usort($faces, fn(array $left, array $right): int => [$left['family'], $left['style'], $left['path'], $left['index']] <=> [$right['family'], $right['style'], $right['path'], $right['index']]);

        return $faces;
    }

    public function faceKey(string $family, string $style): string
    {
        return hash('sha256', mb_strtolower(trim($family)) . '|' . mb_strtolower(trim($style)));
    }

    /** @param array{family: string, bold: bool, italic: bool} $requirement */
    private function match(array $requirement, array $faces): ?array
    {
        $candidates = array_filter($faces, fn(array $face): bool => $face['bold'] === $requirement['bold'] && $face['italic'] === $requirement['italic'] && in_array(mb_strtolower($requirement['family']), array_map(mb_strtolower(...), $face['aliases']), true));
        $weight = $requirement['bold'] ? 200 : 80;
        usort($candidates, fn(array $a, array $b): int => abs(($a['weight'] ?? $weight) - $weight) <=> abs(($b['weight'] ?? $weight) - $weight));

        return $candidates[0] ?? null;
    }

    /** @param list<array{family: string, bold: bool, italic: bool}> $requirements */
    public function report(array $requirements): array
    {
        $inventory = $this->inventory();
        $managed = $this->managed ?? DocumentFont::query()->where('status', 'ready')->get()->keyBy('sha256');
        $fallback = null;
        foreach ($this->fallbacks ?? config('document-fonts.fallbacks') as $family) {
            if ($this->match(['family' => $family, 'bold' => false, 'italic' => false], $inventory['faces']) !== null) {
                $fallback = $family;
                break;
            }
        }
        $entries = [];
        foreach ($requirements as $requirement) {
            $face = $this->match($requirement, $inventory['faces']);
            $hash = $face === null ? null : $this->fileHash($face['path']);
            $font = $hash === null ? null : $managed->get($hash);
            $entries[] = [...$requirement, 'style' => ($requirement['bold'] ? 'Bold' : 'Regular') . ($requirement['italic'] ? ' Italic' : ''), 'available' => $hash !== null,
                'browser_url' => $font === null ? null : route('document-fonts.file', $font->uuid, false), 'hash' => $hash,
                'fallback' => $hash === null ? $fallback : null];
        }

        return ['available' => $inventory['available'], 'entries' => $entries, 'warning' => $inventory['warning'] ?? ($fallback === null && collect($entries)->contains(fn(array $entry): bool => !$entry['available']) ? 'Font fallback PDF (Arial, Calibri, atau Liberation Sans) belum tersedia. DOCX tetap dapat diunduh.' : null), 'fallback' => $fallback];
    }

    public function fingerprint(array $requirements): string
    {
        $report = $this->report($requirements);
        $entries = array_map(fn(array $entry): array => ['family' => $entry['family'], 'bold' => $entry['bold'], 'italic' => $entry['italic'], 'hash' => $entry['hash'], 'fallback' => $entry['fallback'], 'web' => $entry['browser_url'] !== null], $report['entries']);
        usort($entries, fn(array $a, array $b): int => json_encode($a) <=> json_encode($b));
        $needsFallback = $entries === [] || collect($report['entries'])->contains(fn(array $entry): bool => $entry['hash'] === null);
        $fallbackFace = !$needsFallback || $report['fallback'] === null ? null : $this->match(['family' => $report['fallback'], 'bold' => false, 'italic' => false], $this->inventory()['faces']);

        return hash('sha256', json_encode(['schema' => 1, 'renderer' => $this->previewRenderer ?? config('document-fonts.preview_renderer'), 'available' => $report['available'], 'entries' => $entries, 'fallback_hash' => $fallbackFace === null ? null : $this->fileHash($fallbackFace['path'])], JSON_THROW_ON_ERROR));
    }

    public function conversionProfile(array $requirements): array
    {
        $report = $this->report($requirements);
        if (!$report['available'] || $report['fallback'] === null) {
            throw new RuntimeException('Profil konversi belum siap: periksa Fontconfig dan font fallback.');
        }
        $substitutions = [];
        foreach ($report['entries'] as $entry) {
            if (!$entry['available']) {
                $substitutions[$entry['family']] = $report['fallback'];
            }
        }
        $version = Process::timeout(10)->run([config('document-fonts.executables.version'), '--version']);
        if ($version->failed()) {
            throw new RuntimeException('LibreOffice belum tersedia.');
        }
        $converter = trim($version->output());

        return ['environment' => $this->environment($substitutions), 'substitutions' => $substitutions, 'converter' => $converter, 'fingerprint' => hash('sha256', $this->fingerprint($requirements) . '|' . $converter)];
    }

    private function fileHash(string $path): ?string
    {
        return $this->hashes[$path] ??= is_readable($path) ? (hash_file('sha256', $path) ?: null) : null;
    }
}
