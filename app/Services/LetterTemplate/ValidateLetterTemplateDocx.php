<?php

namespace App\Services\LetterTemplate;

use App\Enums\LetterTemplateOptionSourceEnum;
use App\Enums\LetterTemplateVersionStatusEnum;
use App\Models\MasterData\LetterTemplate;
use App\Models\MasterData\LetterTemplateVersion;
use App\Models\MasterData\User;
use App\Services\Letter\DocumentFontRegistry;
use App\Services\Letter\InspectDocumentFonts;
use App\Services\Letter\LetterSystemFields;
use App\Services\Letter\ResolveLetterQuestion;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ValidateLetterTemplateDocx
{
    public function __construct(private InspectLetterTemplateDocx $inspector, private LetterTemplateConfigurationHash $configurationHash, private InvalidateLetterTemplateEvidence $evidence, private RecordLetterTemplateAudit $audit, private InspectDocumentFonts $fonts, private DocumentFontRegistry $fontRegistry, private ReadRepeatedLetterBlocks $blocks) {}

    /** @return array{valid: bool, errors: list<string>, placeholders: list<string>, fonts?: array} */
    public function validate(LetterTemplate $template, LetterTemplateVersion $version, User $actor): array
    {
        return DB::transaction(function () use ($template, $version, $actor): array {
            $template = LetterTemplate::query()->whereKey($template->id)->lockForUpdate()->firstOrFail();
            $version = $template->versions()->whereKey($version->id)->lockForUpdate()->firstOrFail();
            $before = $this->audit->snapshot($template, $version);
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
                $result['errors'][] = 'Ukuran dokumen melebihi batas unggahan. Kurangi ukuran gambar atau isi dokumen, lalu unggah kembali.';
            } elseif (hash_file('sha256', $disk->path($version->source_path)) !== $version->source_sha256) {
                $result['errors'][] = 'Berkas yang tersimpan sudah berubah. Unggah kembali dokumen yang ingin digunakan, lalu jalankan validasi.';
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
            $this->audit->record($template, $version, $actor, 'source.validated', $result['valid'] ? 'Struktur dokumen dan variabel DOCX sesuai konfigurasi.' : 'Validasi DOCX gagal; periksa hasil validasi.', $before, validationDetails: ['source_name' => $version->source_original_name, ...$result]);

            return $result;
        }, 5);
    }

    /** @return array{valid: bool, errors: list<string>, placeholders: list<string>, fonts?: array} */
    public function inspectPlaceholders(string $path, LetterTemplateVersion $version): array
    {
        $parts = $this->inspector->read($path);
        $version->loadMissing(['variables.radioOptions', 'variables.columns', 'signerSlots']);
        $known = LetterSystemFields::NAMES;
        $multiple = [];
        foreach ($version->variables as $variable) {
            if ($variable->is_multiple) {
                foreach ($variable->columns as $column) {
                    $known[] = $column->placeholder;
                    $multiple[$column->placeholder] = $variable->name;
                }
            } else {
                $known = [...$known, ...($variable->type->value >= 5 ? app(ResolveLetterQuestion::class)->names($variable) : ($variable->option_source === LetterTemplateOptionSourceEnum::EMPLOYEE ? array_keys(EmployeeAttributeCatalog::tokens($variable->name)) : [$variable->name]))];
            }
        }
        $blocks = $this->blocks->read($parts, $multiple);
        $signers = [];
        foreach ($version->signerSlots as $index => $slot) {
            $prefix = $version->signerSlots->count() === 1 ? 'penandatangan' : 'penandatangan_' . ($index + 1);
            $signers = [...$signers, ...array_keys(EmployeeAttributeCatalog::tokens($prefix))];
        }
        $known = [...$known, ...$signers];
        $found = [];
        $errors = count($known) === count(array_unique($known)) ? [] : ['Ada nama variabel yang digunakan lebih dari sekali dalam konfigurasi. Bedakan Nama Variabel atau Nama Variabel Kolom, lalu periksa ulang dokumen.'];
        $rows = [];
        $rowOwners = [];
        $occurrences = 0;
        foreach ($parts as $part => $document) {
            $supported = preg_match('~^word/(?:document|header[1-9][0-9]*|footer[1-9][0-9]*)\.xml$~D', $part);
            if (!$supported) {
                if (str_contains($document->textContent, '${')) {
                    $location = match ($part) {
                        'word/footnotes.xml' => 'catatan kaki',
                        'word/endnotes.xml' => 'catatan akhir',
                        'word/comments.xml' => 'komentar',
                        default => 'bagian dokumen yang tidak didukung',
                    };
                    $errors[] = "Ada variabel pada {$location}. Pindahkan variabel ke isi surat, kepala halaman, atau kaki halaman.";
                }

                continue;
            }
            $xpath = new DOMXPath($document);
            $xpath->registerNamespace('w', InspectLetterTemplateDocx::WORD_NAMESPACE);
            foreach ($xpath->query('//@*') as $attribute) {
                if (str_contains($attribute->nodeValue, '${')) {
                    $errors[] = 'Ada variabel pada pengaturan elemen dokumen. Hapus variabel tersebut dan tulis ulang sebagai teks biasa di isi surat.';
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
                    $errors[] = 'Penulisan variabel belum lengkap atau terpotong oleh pergantian paragraf. Tulis ulang dengan format ${nama_variabel} dalam satu paragraf.';
                }
                foreach ($matches[1] as $name) {
                    $occurrences++;
                    if ($occurrences > config('letter-templates.placeholders.max_occurrences')) {
                        throw ValidationException::withMessages(['docx' => 'Jumlah penggunaan variabel dalam dokumen terlalu banyak. Kurangi variabel yang berulang, lalu unggah kembali.']);
                    }
                    $found[] = $name;
                    if (preg_match('/\A\/?blok:[a-z][a-z0-9_]*\z/', $name)) {
                        continue;
                    }
                    if (strlen($name) > config('letter-templates.placeholders.max_length') || !preg_match('/\A[a-z][a-z0-9_]*(?::[a-z][a-z0-9_]*)?\z/D', $name)) {
                        $errors[] = 'Nama variabel tidak sesuai format. Salin variabel dari Konfigurasi Formulir Surat dan tempelkan kembali ke dokumen.';
                    } elseif (!in_array($name, $known, true)) {
                        $errors[] = "Variabel {$name} belum terdaftar. Tambahkan pada Konfigurasi Formulir Surat atau sesuaikan nama variabel di dokumen.";
                    }
                    if (isset($multiple[$name])) {
                        $row = $this->ancestor($paragraph, 'tr');
                        if ($part !== 'word/document.xml' || $row === null || $xpath->query('.//w:vMerge | .//w:tbl', $row)->length > 0) {
                            $errors[] = "Variabel tabel {$name} harus berada dalam satu baris tabel di isi surat. Pindahkan dari kepala/kaki halaman dan gunakan baris tanpa tabel di dalamnya atau sel yang digabung ke bawah.";

                            continue;
                        }
                        if (isset($blocks[$multiple[$name]])) {
                            continue;
                        }
                        $rowKey = $row->getNodePath();
                        $variable = $multiple[$name];
                        if ((isset($rows[$variable]) && $rows[$variable] !== $rowKey) || (isset($rowOwners[$rowKey]) && $rowOwners[$rowKey] !== $variable)) {
                            $errors[] = 'Gunakan satu baris contoh untuk tabel horizontal, atau pasang penanda awal dan akhir blok untuk tabel vertikal. Semua variabel kolom kelompok harus berada dalam baris atau blok yang sama.';
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
        foreach ($version->variables as $variable) {
            if ($variable->type->value === 5) {
                foreach (app(ResolveLetterQuestion::class)->names($variable) as $token) {
                    if (!in_array($token, $found, true)) {
                        $errors[] = "Placeholder opsi {$token} belum dipasang pada DOCX.";
                    }
                }
            }
        }
        $names = array_values(array_unique($found));
        if (array_diff($phpWordNames, $names) !== [] || array_diff($names, $phpWordNames) !== []) {
            $errors[] = 'Ada variabel yang tidak dapat diproses pada tempat atau format penulisannya. Hapus lalu tempel ulang variabel sebagai teks biasa dalam satu paragraf.';
        }
        $errors = array_values(array_unique($errors));
        sort($names);

        $fonts = $this->fonts->inspect($parts);

        return ['valid' => $errors === [], 'errors' => $errors, 'placeholders' => $names, 'fonts' => [...$fonts, ...$this->fontRegistry->report($fonts['requirements'])]];
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
