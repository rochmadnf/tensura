<?php

namespace App\Services\Letter;

use App\Models\MasterData\LetterTemplateVersion;
use Illuminate\Validation\ValidationException;
use Throwable;

class InspectDocumentFontReferences
{
    public function __construct(private ReadLetterTemplateFonts $fonts, private DocumentFontRegistry $registry) {}

    /**
     * @param  list<array{file: string, family: string, style: string, sha256: string}>  $entries
     * @return array{used: list<array{string, string, string, string}>, incomplete: list<array{string, string}>}
     */
    public function inspect(array $entries): array
    {
        $used = $incomplete = [];
        $aliases = [];
        foreach ($entries as $entry) {
            $aliases[$entry['file']] = [mb_strtolower($entry['family'])];
            $path = config('document-fonts.package_directory') . '/' . $entry['file'];
            if (is_file($path)) {
                try {
                    $aliases[$entry['file']] = array_map(mb_strtolower(...), $this->registry->scan($path)['aliases']);
                } catch (Throwable $exception) {
                    $incomplete[] = ['Font: ' . $entry['file'], $exception->getMessage()];
                }
            }
        }
        foreach (LetterTemplateVersion::query()->select(['id', 'uuid', 'letter_template_id', 'version_number', 'source_disk', 'source_path', 'source_sha256'])->with('template:id,name')->lazyById(100) as $version) {
            if ($version->source_path === null || $version->source_path === '') {
                continue;
            }
            $name = $version->template?->name ?? $version->uuid;
            try {
                $usage = $this->fonts->read($version);
                foreach (array_unique($usage['limitations']) as $limitation) {
                    $incomplete[] = [$name . ' · versi ' . $version->version_number, $limitation];
                }
                foreach ($entries as $entry) {
                    foreach ($usage['requirements'] as $requirement) {
                        if (in_array(mb_strtolower($requirement['family']), $aliases[$entry['file']], true)
                            && $requirement['bold'] === str_contains($entry['style'], 'Bold')
                            && $requirement['italic'] === str_contains($entry['style'], 'Italic')) {
                            $used[] = [$entry['family'], $entry['style'], $name, (string) $version->version_number];
                            break;
                        }
                    }
                }
            } catch (Throwable $exception) {
                $incomplete[] = [$name . ' · versi ' . $version->version_number, $exception instanceof ValidationException ? collect($exception->errors())->flatten()->implode(' ') : $exception->getMessage()];
            }
        }
        sort($used);
        sort($incomplete);

        return ['used' => $used, 'incomplete' => $incomplete];
    }
}
