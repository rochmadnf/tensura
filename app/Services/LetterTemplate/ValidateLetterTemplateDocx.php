<?php

namespace App\Services\LetterTemplate;

use App\Enums\LetterTemplateOptionSourceEnum;
use App\Enums\LetterTemplateVersionStatusEnum;
use App\Models\MasterData\LetterTemplate;
use App\Models\MasterData\LetterTemplateVersion;
use App\Models\MasterData\User;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ValidateLetterTemplateDocx
{
    public function __construct(private InspectLetterTemplateDocx $inspector, private LetterTemplateConfigurationHash $configurationHash, private InvalidateLetterTemplateEvidence $evidence, private RecordLetterTemplateAudit $audit) {}

    /** @return array{valid: bool, errors: list<string>, placeholders: list<string>} */
    public function validate(LetterTemplate $template, LetterTemplateVersion $version, User $actor): array
    {
        return DB::transaction(function () use ($template, $version, $actor): array {
            $template = LetterTemplate::query()->whereKey($template->id)->lockForUpdate()->firstOrFail();
            $version = $template->versions()->whereKey($version->id)->lockForUpdate()->firstOrFail();
            if ($version->status !== LetterTemplateVersionStatusEnum::DRAFT || $version->first_used_at !== null) {
                throw ValidationException::withMessages(['docx' => 'Validasi hanya dapat dijalankan pada draft yang belum digunakan.']);
            }
            if ($version->source_path === null) {
                throw ValidationException::withMessages(['docx' => 'Unggah DOCX sumber terlebih dahulu.']);
            }
            $this->evidence->invalidate($version);
            $result = ['valid' => false, 'errors' => [], 'placeholders' => []];
            $disk = Storage::disk($version->source_disk);
            if (!$disk->exists($version->source_path)) {
                $result['errors'][] = 'DOCX sumber tidak ditemukan. Unggah ulang berkas.';
            } elseif ($disk->size($version->source_path) > config('letter-templates.max_upload_kb') * 1024) {
                $result['errors'][] = 'Ukuran DOCX tersimpan melebihi batas upload. Unggah ulang berkas.';
            } elseif (hash_file('sha256', $disk->path($version->source_path)) !== $version->source_sha256) {
                $result['errors'][] = 'Isi DOCX sumber berubah di penyimpanan. Unggah ulang berkas.';
            } else {
                try {
                    $result = $this->inspectPlaceholders($disk->path($version->source_path), $version);
                } catch (ValidationException $exception) {
                    $result['errors'] = array_values(array_merge(...array_values($exception->errors())));
                }
            }
            if ($result['valid']) {
                $version->fill(['validated_at' => now(), 'validated_source_sha256' => $version->source_sha256, 'validated_configuration_sha256' => $this->configurationHash->hash($version)]);
            }
            $version->validation_result = $result;
            $version->save();
            $this->audit->record($template, $version, $actor, 'source.validated', $result['valid'] ? 'Struktur dan placeholder DOCX valid.' : 'Validasi DOCX gagal; periksa hasil validasi.');

            return $result;
        }, 5);
    }

    /** @return array{valid: bool, errors: list<string>, placeholders: list<string>} */
    public function inspectPlaceholders(string $path, LetterTemplateVersion $version): array
    {
        $parts = $this->inspector->read($path);
        $version->loadMissing(['variables.columns', 'signerSlots']);
        $known = [];
        $multiple = [];
        foreach ($version->variables as $variable) {
            if ($variable->is_multiple) {
                foreach ($variable->columns as $column) {
                    $known[] = $column->placeholder;
                    $multiple[$column->placeholder] = $variable->name;
                }
            } else {
                $known = [...$known, ...($variable->option_source === LetterTemplateOptionSourceEnum::EMPLOYEE ? array_keys(EmployeeAttributeCatalog::tokens($variable->name)) : [$variable->name])];
            }
        }
        $signers = [];
        foreach ($version->signerSlots as $index => $slot) {
            $prefix = $version->signerSlots->count() === 1 ? 'penandatangan' : 'penandatangan_' . ($index + 1);
            $signers = [...$signers, ...array_keys(EmployeeAttributeCatalog::tokens($prefix))];
        }
        $known = [...$known, ...$signers];
        $found = [];
        $errors = count($known) === count(array_unique($known)) ? [] : ['Konfigurasi memiliki placeholder yang bertabrakan. Perbaiki nama variabel atau kolom sebelum validasi.'];
        $rows = [];
        $rowOwners = [];
        $occurrences = 0;
        foreach ($parts as $part => $document) {
            $supported = preg_match('~^word/(?:document|header[1-9][0-9]*|footer[1-9][0-9]*)\.xml$~D', $part);
            if (!$supported) {
                if (str_contains($document->textContent, '${')) {
                    $errors[] = "Placeholder pada komponen {$part} tidak didukung. Gunakan isi dokumen, header, atau footer.";
                }

                continue;
            }
            $xpath = new DOMXPath($document);
            $xpath->registerNamespace('w', InspectLetterTemplateDocx::WORD_NAMESPACE);
            foreach ($xpath->query('//@*') as $attribute) {
                if (str_contains($attribute->nodeValue, '${')) {
                    $errors[] = 'Placeholder dalam atribut XML tidak didukung. Gunakan teks paragraf.';
                }
            }
            foreach ($xpath->query('//w:p') as $paragraph) {
                $text = '';
                foreach ($xpath->query('.//w:t | .//w:tab | .//w:br | .//w:cr', $paragraph) as $node) {
                    if ($this->ancestor($node, 'p')?->isSameNode($paragraph)) {
                        $text .= $node->localName === 't' ? $node->textContent : "\n";
                    }
                }
                preg_match_all('/\$\{([^}]*)\}/u', $text, $matches);
                if (substr_count($text, '${') !== count($matches[0])) {
                    $errors[] = 'Placeholder tidak lengkap atau terpisah antarparagraf. Tulis ulang dalam satu paragraf.';
                }
                foreach ($matches[1] as $name) {
                    $occurrences++;
                    if ($occurrences > config('letter-templates.placeholders.max_occurrences')) {
                        throw ValidationException::withMessages(['docx' => 'Jumlah placeholder DOCX melewati batas.']);
                    }
                    $found[] = $name;
                    if (strlen($name) > config('letter-templates.placeholders.max_length') || !preg_match('/\A[a-z][a-z0-9_]*\z/D', $name)) {
                        $errors[] = 'Nama placeholder tidak valid. Gunakan nama dari konfigurasi templat.';
                    } elseif (!in_array($name, $known, true)) {
                        $errors[] = "Placeholder {$name} belum dikonfigurasi; perbaiki DOCX atau konfigurasi variabel.";
                    }
                    if (isset($multiple[$name])) {
                        $row = $this->ancestor($paragraph, 'tr');
                        if ($part !== 'word/document.xml' || $row === null || $xpath->query('.//w:vMerge | .//w:tbl', $row)->length > 0) {
                            $errors[] = "Placeholder tabel {$name} harus berada pada baris tabel biasa di isi dokumen, tanpa tabel bersarang atau penggabungan vertikal.";

                            continue;
                        }
                        $rowKey = $row->getNodePath();
                        $variable = $multiple[$name];
                        if ((isset($rows[$variable]) && $rows[$variable] !== $rowKey) || (isset($rowOwners[$rowKey]) && $rowOwners[$rowKey] !== $variable)) {
                            $errors[] = 'Setiap variabel multiple harus memakai satu baris tabel tersendiri.';
                        }
                        $rows[$variable] = $rowKey;
                        $rowOwners[$rowKey] = $variable;
                    }
                }
            }
        }
        $processor = new LetterTemplateProcessor($path);
        try {
            $phpWordNames = $processor->getVariables();
        } finally {
            unset($processor);
        }
        $names = array_values(array_unique($found));
        if (array_diff($phpWordNames, $names) !== [] || array_diff($names, $phpWordNames) !== []) {
            $errors[] = 'Ada placeholder pada lokasi atau text run yang tidak didukung PHPWord. Tulis ulang placeholder pada teks paragraf biasa.';
        }
        $errors = array_values(array_unique($errors));
        sort($names);

        return ['valid' => $errors === [], 'errors' => $errors, 'placeholders' => $names];
    }

    private function ancestor(DOMNode $node, string $name): ?DOMElement
    {
        for ($parent = $node->parentNode; $parent !== null; $parent = $parent->parentNode) {
            if ($parent instanceof DOMElement && $parent->namespaceURI === InspectLetterTemplateDocx::WORD_NAMESPACE && $parent->localName === $name) {
                return $parent;
            }
        }

        return null;
    }
}
