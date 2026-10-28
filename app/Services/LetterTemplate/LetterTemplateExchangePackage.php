<?php

namespace App\Services\LetterTemplate;

use App\Models\MasterData\LetterTemplate;
use App\Models\MasterData\LetterTemplateVersion;
use App\Services\Letter\DocumentFontRegistry;
use App\Services\Letter\InspectDocumentFonts;
use App\Services\Letter\InstallDocumentFont;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;
use ZipArchive;

/**
 * @phpstan-import-type ExchangeConfiguration from LetterTemplateExchangeConfiguration
 * @phpstan-import-type EmployeeContract from LetterTemplateExchangeConfiguration
 *
 * @phpstan-type FontRequirement array{family: string, bold: bool, italic: bool}
 * @phpstan-type ExchangeFile array{size: int, sha256: string, family?: string, style?: string, bold?: bool, italic?: bool}
 * @phpstan-type ExchangeManifest array{format: string, version: int, template: array{name: string, version_number: int, letter_date_format: string}, configuration: ExchangeConfiguration, employee_contract: EmployeeContract, font_requirements: list<FontRequirement>, font_limitations: list<string>, includes_fonts: bool, files: array<string, ExchangeFile>}
 * @phpstan-type PreparedExchange array{manifest: ExchangeManifest, files: array<string, string>, missing_fonts: list<string>, limitations: list<string>}
 */
class LetterTemplateExchangePackage
{
    public function __construct(
        private TnexCipher $cipher,
        private LetterTemplateExchangeConfiguration $configuration,
        private InspectLetterTemplateDocx $docx,
        private InspectDocumentFonts $fonts,
        private DocumentFontRegistry $registry,
        private InstallDocumentFont $installer,
    ) {}

    /** @return PreparedExchange */
    public function prepare(LetterTemplate $template, LetterTemplateVersion $version, bool $includeDocx, bool $includeFonts): array
    {
        return DB::transaction(function () use ($template, $version, $includeDocx, $includeFonts): array {
            $template = LetterTemplate::query()->whereKey($template->id)->lockForUpdate()->firstOrFail();
            $version = $template->versions()->whereKey($version->id)->lockForUpdate()->firstOrFail();

            return $this->prepareLocked($template, $version, $includeDocx, $includeFonts);
        });
    }

    /** @return PreparedExchange */
    private function prepareLocked(LetterTemplate $template, LetterTemplateVersion $version, bool $includeDocx, bool $includeFonts): array
    {
        if ($version->letter_template_id !== $template->id || $version->source_path === null || $version->source_disk === null || $version->source_sha256 === null) {
            $this->reject('Versi harus memiliki DOCX sumber yang valid untuk diekspor.');
        }
        $disk = Storage::disk($version->source_disk);
        if (!$disk->exists($version->source_path) || $disk->size($version->source_path) > config('letter-templates.max_upload_kb') * 1024 || hash_file('sha256', $disk->path($version->source_path)) !== $version->source_sha256) {
            $this->reject('DOCX sumber hilang, berubah, atau melewati batas ukuran.');
        }
        $parts = $this->docx->read($disk->path($version->source_path));
        $configuration = $this->configuration->export($version);
        $this->configuration->validate($configuration);
        $requirements = $this->fonts->inspect($parts);
        $files = $includeDocx ? ['source.docx' => $disk->get($version->source_path)] : [];
        $fontFiles = [];
        $missing = [];
        if ($includeFonts) {
            $inventory = $this->registry->inventory(true);
            $report = $this->registry->report($requirements['requirements']);
            foreach ($report['entries'] as $entry) {
                if (!$entry['available']) {
                    $missing[] = $entry['family'] . ' ' . $entry['style'];

                    continue;
                }
                $face = collect($inventory['faces'])->first(fn(array $face): bool => hash_file('sha256', $face['path']) === $entry['hash']);
                if ($face === null || $face['index'] !== 0) {
                    $missing[] = $entry['family'] . ' ' . $entry['style'];

                    continue;
                }
                $signature = file_get_contents($face['path'], false, null, 0, 4);
                $name = 'fonts/' . $entry['hash'] . ($signature === 'OTTO' ? '.otf' : '.ttf');
                $fontFace = $this->installer->inspectFile($face['path'], $name);
                $files[$name] = file_get_contents($face['path']);
                $fontFiles[$name] = ['family' => $fontFace['family'], 'style' => $fontFace['style'], 'bold' => $fontFace['bold'], 'italic' => $fontFace['italic']];
            }
        }
        $manifest = [
            'format' => 'tensura-exchange', 'version' => 1,
            'template' => ['name' => $template->fresh()->name, 'version_number' => $version->version_number, 'letter_date_format' => $version->letter_date_format ?? 'd-m-Y'],
            'configuration' => $configuration, 'employee_contract' => $this->configuration->contract($configuration),
            'font_requirements' => $requirements['requirements'], 'font_limitations' => $requirements['limitations'],
            'includes_fonts' => $includeFonts, 'files' => [],
        ];
        foreach ($files as $name => $bytes) {
            $manifest['files'][$name] = ['size' => strlen($bytes), 'sha256' => hash('sha256', $bytes), ...($fontFiles[$name] ?? [])];
        }
        if (count($files) + 1 > config('letter-templates.exchange.max_entries') || array_sum(array_map(strlen(...), $files)) + strlen(json_encode($manifest, JSON_THROW_ON_ERROR)) > config('letter-templates.exchange.max_bytes') - 65536) {
            $this->reject('Isi paket melewati batas ukuran atau jumlah berkas.');
        }

        return ['manifest' => $manifest, 'files' => $files, 'missing_fonts' => array_values(array_unique($missing)), 'limitations' => $requirements['limitations']];
    }

    /** @param PreparedExchange $prepared */
    public function encode(array $prepared, #[SensitiveParameter] string $password): string
    {
        if ($prepared['missing_fonts'] !== []) {
            $this->reject('Lengkapi font berikut atau nonaktifkan penyertaan font: ' . implode(', ', $prepared['missing_fonts']));
        }
        $temporary = tmpfile();
        try {
            $path = stream_get_meta_data($temporary)['uri'];
            $zip = new ZipArchive();
            if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                $this->reject('Paket gagal dibuat.');
            }
            try {
                $files = ['manifest.json' => json_encode($prepared['manifest'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), ...$prepared['files']];
                foreach ($files as $name => $bytes) {
                    if (!$zip->addFromString($name, $bytes) || !$zip->setCompressionName($name, ZipArchive::CM_STORE)) {
                        $this->reject('Paket gagal dibuat.');
                    }
                }
            } finally {
                $zip->close();
            }

            return $this->cipher->encrypt(file_get_contents($path), $password);
        } finally {
            fclose($temporary);
        }
    }

    /** @return array{manifest: ExchangeManifest, files: array<string, string>, incompatibilities: list<string>} */
    public function decode(string $encrypted, #[SensitiveParameter] string $password): array
    {
        $payload = $this->cipher->decrypt($encrypted, $password);
        $temporary = tmpfile();
        try {
            fwrite($temporary, $payload);
            $path = stream_get_meta_data($temporary)['uri'];
            $zip = new ZipArchive();
            if ($zip->open($path, ZipArchive::RDONLY | ZipArchive::CHECKCONS) !== true) {
                $this->reject('Isi paket tidak dapat dibaca.');
            }
            try {
                $files = $this->readEntries($zip);
            } finally {
                $zip->close();
            }
        } finally {
            fclose($temporary);
        }
        if (!isset($files['manifest.json']) || strlen($files['manifest.json']) > 1024 * 1024) {
            $this->reject('Manifest paket tidak tersedia atau terlalu besar.');
        }
        try {
            $manifest = json_decode($files['manifest.json'], true, 40, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->reject('Manifest paket tidak valid.');
        }
        unset($files['manifest.json']);
        $this->validateManifest($manifest);
        if (array_diff_key($files, $manifest['files']) !== [] || array_diff_key($manifest['files'], $files) !== []) {
            $this->reject('Daftar berkas tidak sesuai manifest.');
        }
        $fontFaces = [];
        foreach ($files as $name => $bytes) {
            $metadata = $manifest['files'][$name];
            if (strlen($bytes) !== $metadata['size'] || hash('sha256', $bytes) !== $metadata['sha256']) {
                $this->reject('Integritas berkas paket tidak sesuai manifest.');
            }
            $file = tmpfile();
            try {
                fwrite($file, $bytes);
                $path = stream_get_meta_data($file)['uri'];
                if ($name === 'source.docx') {
                    if (strlen($bytes) > config('letter-templates.max_upload_kb') * 1024) {
                        $this->reject('Ukuran DOCX melewati batas unggahan.');
                    }
                    $requirements = $this->fonts->inspect($this->docx->read($path));
                    if ($requirements['requirements'] !== $manifest['font_requirements']) {
                        $this->reject('Kebutuhan font tidak sesuai DOCX sumber.');
                    }
                } else {
                    $face = $this->installer->inspectFile($path, $name);
                    $fontFaces[] = $face;
                    foreach (['family', 'style', 'bold', 'italic'] as $key) {
                        if ($face[$key] !== ($metadata[$key] ?? null)) {
                            $this->reject('Identitas font tidak sesuai manifest.');
                        }
                    }
                    $needed = collect($manifest['font_requirements'])->contains(fn(array $requirement): bool => $requirement['bold'] === $face['bold'] && $requirement['italic'] === $face['italic'] && in_array(mb_strtolower($requirement['family']), array_map(mb_strtolower(...), $face['aliases']), true));
                    if (!$manifest['includes_fonts'] || !$needed) {
                        $this->reject('Paket membawa font yang tidak digunakan dokumen.');
                    }
                }
            } finally {
                fclose($file);
            }
        }
        if ($manifest['includes_fonts']) {
            foreach ($manifest['font_requirements'] as $requirement) {
                if (!collect($fontFaces)->contains(fn(array $face): bool => in_array(mb_strtolower($requirement['family']), array_map(mb_strtolower(...), $face['aliases']), true) && $face['bold'] === $requirement['bold'] && $face['italic'] === $requirement['italic'])) {
                    $this->reject('Font yang dijanjikan paket tidak lengkap.');
                }
            }
        }
        $this->configuration->validate($manifest['configuration']);

        return ['manifest' => $manifest, 'files' => $files, 'incompatibilities' => $this->configuration->incompatibilities($manifest['configuration'], $manifest['employee_contract'])];
    }

    /** @return array<string, string> */
    private function readEntries(ZipArchive $zip): array
    {
        if ($zip->numFiles < 1 || $zip->numFiles > config('letter-templates.exchange.max_entries')) {
            $this->reject('Jumlah berkas dalam paket melewati batas.');
        }
        $files = [];
        $total = 0;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entry = $zip->statIndex($index);
            if ($entry === false) {
                $this->reject('Berkas paket rusak.');
            }
            $name = $entry['name'];
            $system = $attributes = 0;
            $zip->getExternalAttributesIndex($index, $system, $attributes);
            $total += $entry['size'];
            if (!preg_match('~\A(?:manifest\.json|source\.docx|fonts/[a-f0-9]{64}\.(?:ttf|otf))\z~D', $name) || isset($files[$name]) || ($attributes >> 16 & 0170000) === 0120000 || ($entry['encryption_method'] ?? 0) !== 0 || $total > config('letter-templates.exchange.max_bytes') || $entry['size'] / max(1, $entry['comp_size']) > 200) {
                $this->reject('Struktur, ukuran, atau jalur berkas paket tidak aman.');
            }
            if (($name === 'manifest.json' && $entry['size'] > 1024 * 1024) || ($name !== 'manifest.json' && $entry['size'] > config($name === 'source.docx' ? 'letter-templates.max_upload_kb' : 'document-fonts.max_upload_kb') * 1024)) {
                $this->reject('Ukuran berkas dalam paket melewati batas.');
            }
            $bytes = $zip->getFromIndex($index);
            if ($bytes === false || strlen($bytes) !== $entry['size']) {
                $this->reject('Berkas paket rusak atau terpotong.');
            }
            $files[$name] = $bytes;
        }

        return $files;
    }

    private function validateManifest(mixed $manifest): void
    {
        if (!is_array($manifest) || ($manifest['version'] ?? null) !== 1 || array_diff(array_keys($manifest), ['format', 'version', 'template', 'configuration', 'employee_contract', 'font_requirements', 'font_limitations', 'includes_fonts', 'files']) !== []) {
            $this->reject('Manifest paket tidak dikenal.');
        }
        Validator::make($manifest, [
            'format' => ['required', 'in:tensura-exchange'], 'version' => ['required', 'integer', 'in:1'],
            'template' => ['required', 'array:name,version_number,letter_date_format'],
            'template.name' => ['required', 'string', 'min:3', 'max:100'], 'template.version_number' => ['required', 'integer', 'min:1'],
            'template.letter_date_format' => ['required', 'string', 'max:100', 'not_regex:/[\x00-\x1F]/'],
            'configuration' => ['required', 'array:variables,signer_slots'], 'employee_contract' => ['present', 'array'],
            'font_requirements' => ['present', 'array', 'list', 'max:64'], 'font_requirements.*' => ['array:family,bold,italic'],
            'font_requirements.*.family' => ['required', 'string', 'max:255'], 'font_requirements.*.bold' => ['required', 'boolean'], 'font_requirements.*.italic' => ['required', 'boolean'],
            'font_limitations' => ['present', 'array', 'list', 'max:50'], 'font_limitations.*' => ['required', 'string', 'max:1000'],
            'includes_fonts' => ['required', 'boolean'], 'files' => ['present', 'array'], 'files.*' => ['array:size,sha256,family,style,bold,italic'],
            'files.*.size' => ['required', 'integer', 'min:1'], 'files.*.sha256' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/D'],
        ])->validate();
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['package' => $message]);
    }
}
