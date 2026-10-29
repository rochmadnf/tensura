<?php

namespace App\Services\Letter;

use App\Models\MasterData\LetterTemplateVersion;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class InspectMissingDocumentFonts
{
    /** @var list<array{string, string, string, string}> */
    private array $packageRows = [];

    /** @var list<array{string, string, string, string, string}> */
    private array $templateRows = [];

    /** @var list<array{string, string}> */
    private array $incompleteRows = [];

    public function __construct(private DocumentFontPackage $package, private DocumentFontRegistry $registry, private ReadLetterTemplateFonts $fonts) {}

    /**
     * @return array{package: list<array{string, string, string, string}>, templates: list<array{string, string, string, string, string}>, incomplete: list<array{string, string}>, summary: array{package_checked: int, versions_checked: int, versions_skipped: int, versions_with_findings: int}, exit_code: int}
     */
    public function inspect(): array
    {
        $this->packageRows = $this->templateRows = $this->incompleteRows = [];
        $summary = ['package_checked' => $this->inspectPackage(), 'versions_checked' => 0, 'versions_skipped' => 0, 'versions_with_findings' => 0];
        $runtimeAvailable = false;
        try {
            $runtimeAvailable = $this->registry->inventory(true)['available'];
            if (!$runtimeAvailable) {
                throw new RuntimeException('Inventaris Fontconfig belum dapat diperiksa. Ketersediaan font template belum diketahui.');
            }
        } catch (Throwable $exception) {
            $this->incompleteRows[] = ['Runtime font', $this->message($exception)];
        }
        try {
            foreach (LetterTemplateVersion::query()->select(['id', 'uuid', 'letter_template_id', 'version_number', 'source_disk', 'source_path', 'source_sha256'])->with('template:id,name')->lazyById(100) as $version) {
                if ($version->source_path === null || $version->source_path === '') {
                    $summary['versions_skipped']++;

                    continue;
                }
                $summary['versions_checked']++;
                $before = count($this->templateRows) + count($this->incompleteRows);
                $this->inspectVersion($version, $runtimeAvailable);
                if (count($this->templateRows) + count($this->incompleteRows) > $before) {
                    $summary['versions_with_findings']++;
                }
            }
        } catch (Throwable $exception) {
            $this->incompleteRows[] = ['Daftar versi templat', $this->message($exception)];
        }
        sort($this->packageRows);
        sort($this->templateRows);
        sort($this->incompleteRows);

        return [
            'package' => $this->packageRows,
            'templates' => $this->templateRows,
            'incomplete' => $this->incompleteRows,
            'summary' => $summary,
            'exit_code' => $this->incompleteRows !== [] ? 2 : ($this->packageRows !== [] || $this->templateRows !== [] ? 1 : 0),
        ];
    }

    private function inspectPackage(): int
    {
        try {
            $manifest = $this->package->manifest(refresh: true);
        } catch (Throwable $exception) {
            $this->incompleteRows[] = ['Manifest paket', $this->message($exception)];

            return 0;
        }
        foreach (array_unique($manifest['missing']) as $missing) {
            [$family, $style] = array_pad(explode(' · ', $missing, 2), 2, '—');
            $this->packageRows[] = [$family, $style, '—', 'Belum ada dalam paket'];
        }
        foreach ($manifest['fonts'] as $entry) {
            $path = config('document-fonts.package_directory') . '/' . $entry['file'];
            $problem = null;
            try {
                if (!is_file($path)) {
                    $problem = 'Berkas font hilang';
                } elseif (!is_readable($path)) {
                    $problem = 'Berkas font tidak dapat dibaca';
                } elseif (hash_file('sha256', $path) !== $entry['sha256']) {
                    $problem = 'Checksum font tidak sesuai';
                } else {
                    $face = $this->registry->scan($path);
                    if ($face['family'] !== $entry['family'] || $face['style'] !== $entry['style']) {
                        $problem = 'Metadata font tidak sesuai';
                    }
                }
            } catch (Throwable $exception) {
                $this->incompleteRows[] = ['Paket: ' . $entry['file'], $this->message($exception)];
            }
            if ($problem !== null) {
                $this->packageRows[] = [$entry['family'], $entry['style'], $entry['file'], $problem];
            }
        }

        return count($manifest['fonts']);
    }

    private function inspectVersion(LetterTemplateVersion $version, bool $runtimeAvailable): void
    {
        $name = $version->template?->name ?? $version->uuid;
        $origin = $name . ' · versi ' . $version->version_number;
        try {
            $usage = $this->fonts->read($version);
            foreach (array_unique($usage['limitations']) as $limitation) {
                $this->incompleteRows[] = [$origin, $limitation];
            }
            if (!$runtimeAvailable) {
                return;
            }
            $report = $this->registry->report($usage['requirements']);
            if (!$report['available']) {
                throw new RuntimeException('Ketersediaan font template belum dapat diperiksa.');
            }
            $seen = [];
            foreach ($report['entries'] as $entry) {
                $key = mb_strtolower($entry['family']) . '|' . (int) $entry['bold'] . '|' . (int) $entry['italic'];
                if (!$entry['available'] && !isset($seen[$key])) {
                    $this->templateRows[] = [$name, (string) $version->version_number, $entry['family'], $entry['style'], $entry['fallback'] ?? '—'];
                    $seen[$key] = true;
                }
            }
        } catch (Throwable $exception) {
            $this->incompleteRows[] = [$origin, $this->message($exception)];
        }
    }

    private function message(Throwable $exception): string
    {
        return $exception instanceof ValidationException ? collect($exception->errors())->flatten()->implode(' ') : $exception->getMessage();
    }
}
