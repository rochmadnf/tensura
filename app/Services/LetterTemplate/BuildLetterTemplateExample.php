<?php

namespace App\Services\LetterTemplate;

use App\Enums\LetterTemplateOptionSourceEnum;
use App\Models\MasterData\LetterTemplateVersion;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use RuntimeException;
use Throwable;

class BuildLetterTemplateExample
{
    public function build(LetterTemplateVersion $version): string
    {
        $version->loadMissing(['template', 'letterNumber', 'variables.referenceGroup', 'variables.columns', 'variables.employeeAttributes', 'signerSlots.employeeAttributes']);
        $disk = Storage::disk(config('letter-templates.disk'));
        $disk->makeDirectory(config('letter-templates.paths.temporary'));
        $path = tempnam($disk->path(config('letter-templates.paths.temporary')), 'example-');
        if ($path === false) {
            throw new RuntimeException('Berkas sementara DOCX contoh gagal dibuat.');
        }
        $escaping = Settings::isOutputEscapingEnabled();
        Settings::setOutputEscapingEnabled(true);
        try {
            $word = new PhpWord();
            $word->setDefaultFontName('Calibri');
            $word->setDefaultFontSize(11);
            $section = $word->addSection();
            $section->addText('Panduan Templat Surat', ['bold' => true, 'size' => 18]);
            $section->addText($this->plain($version->template->name), ['bold' => true]);
            $section->addText('Versi ' . $version->version_number . ' — ' . $version->status->label());
            $section->addText('Klasifikasi: ' . $this->plain($version->letterNumber->classification_code . ' — ' . $version->letterNumber->classification_name));
            $section->addText('Berkas ini adalah panduan dan bahan awal. Sesuaikan tata letak sebelum mengunggah sebagai DOCX sumber. Unduhan ini belum menjadi hasil validasi atau hasil uji.');
            $section->addText('Konfigurasi Variabel', ['bold' => true, 'size' => 14]);
            if ($version->variables->isEmpty()) {
                $section->addText('Draft ini belum memiliki variabel. Lengkapi konfigurasi pada halaman kelola, lalu unduh kembali contoh terbaru.');
            }
            foreach ($version->variables as $variable) {
                $section->addText($this->plain($variable->label . ' (' . $variable->name . ')'), ['bold' => true]);
                $section->addText($variable->type->label() . ' — ' . ($variable->is_required ? 'Wajib diisi' : 'Opsional'));
                if ($variable->option_source !== null) {
                    $section->addText('Sumber: ' . $variable->option_source->label() . ($variable->referenceGroup === null ? '' : ' — ' . $this->plain($variable->referenceGroup->name)));
                }
                if (filled($variable->default_text)) {
                    $section->addText('Fallback teks literal: ' . $this->plain($variable->default_text));
                }
                if ($variable->is_multiple) {
                    $section->addText('Contoh tabel berulang: pertahankan satu baris contoh dengan placeholder kolom berikut. Jangan menggabungkan sel secara vertikal atau menaruh tabel ini pada header/footer.');
                    $table = $section->addTable(['borderSize' => 6, 'cellMargin' => 80]);
                    $table->addRow();
                    foreach ($variable->columns as $column) {
                        $label = collect($variable->option_source?->columnOptions() ?? [])->firstWhere('id', $column->source_attribute)['label'] ?? $column->source_attribute;
                        $format = EmployeeAttributeCatalog::isDate($column->source_attribute) ? ' — format ' . ($column->date_format ?? 'd-m-Y') : '';
                        $table->addCell(2200)->addText($this->plain($label . $format), ['bold' => true]);
                    }
                    $table->addRow();
                    foreach ($variable->columns as $column) {
                        $table->addCell(2200)->addText('${' . $column->placeholder . '}');
                    }
                } else {
                    $section->addText('Contoh paragraf: ' . $this->plain($variable->label) . ': ${' . $variable->name . '}');
                    if ($variable->option_source === LetterTemplateOptionSourceEnum::EMPLOYEE) {
                        $this->employeeGuide($section, $variable->name, $variable->employeeAttributes->map->only(['source_attribute', 'default_text', 'date_format'])->all());
                    }
                }
            }
            $section->addText('Penandatangan', ['bold' => true, 'size' => 14]);
            if ($version->signerSlots->isEmpty()) {
                $section->addText('Tidak ada slot aktif. Tambahkan slot beserta kandidat pegawai aktif bila surat memerlukan penandatangan.');
            }
            foreach ($version->signerSlots as $index => $slot) {
                $name = $version->signerSlots->count() === 1 ? 'penandatangan' : 'penandatangan_' . ($index + 1);
                $section->addText('Slot ' . ($index + 1) . ' (alias nama lengkap): ${' . $name . '}');
                $this->employeeGuide($section, $name, $slot->employeeAttributes->map->only(['source_attribute', 'default_text', 'date_format'])->all());
            }
            $section->addText('Langkah Berikutnya', ['bold' => true, 'size' => 14]);
            foreach ([
                '1. Simpan identitas, variabel, pemetaan kolom, dan kandidat penandatangan di web app.',
                '2. Sesuaikan tata letak DOCX. Gunakan placeholder yang dikonfigurasi; variabel dan token penandatangan boleh digunakan sebagian. Slot tanpa token tidak perlu diisi saat generate.',
                'Fallback dipakai sebagai teks literal saat nilai kosong; tidak menggantikan isian wajib. Multiple opsional tanpa pilihan menghapus baris contoh, mempertahankan header/baris statis.',
                'Format tanggal memakai pola PHP dengan bahasa aplikasi. Masa kerja ditampilkan sampai hari tanpa bagian nol, berdasarkan tanggal pemrosesan.',
                '3. Unggah DOCX sumber dan jalankan validasi struktur serta placeholder. Perbaiki pesan galat bila ada.',
                '4. Isi data contoh fiktif (misalnya Pegawai Contoh), generate hasil uji, buka DOCX, lalu konfirmasi pemeriksaan visual.',
                '5. Publikasikan setelah validasi, generate uji, dan konfirmasi terpenuhi. Perubahan konfigurasi atau DOCX mengharuskan pemeriksaan ulang.',
                'Panduan tidak memuat data pribadi kandidat pegawai. Admin berikutnya dapat mengunduh ulang panduan dari versi yang sama.',
            ] as $instruction) {
                $section->addText($instruction);
            }
            IOFactory::createWriter($word, 'Word2007')->save($path);

            return $path;
        } catch (Throwable $exception) {
            if (is_file($path)) {
                unlink($path);
            }
            throw $exception;
        } finally {
            Settings::setOutputEscapingEnabled($escaping);
        }
    }

    /** @param list<array{source_attribute: string, default_text?: ?string, date_format?: ?string}> $settings */
    private function employeeGuide(Section $section, string $prefix, array $settings): void
    {
        $settings = collect(EmployeeAttributeCatalog::settings($settings))->keyBy('source_attribute');
        $section->addText('Satu pegawai untuk semua atribut berikut; token dasar adalah alias nama lengkap. Gunakan hanya token yang diperlukan.');
        foreach (EmployeeAttributeCatalog::options() as $field) {
            $setting = $settings->get($field['id']);
            $description = $field['label'] . ': ${' . $prefix . '_' . $field['suffix'] . '}';
            if ($field['type'] === 'date') {
                $description .= ' — format ' . $this->plain($setting['date_format']);
            }
            if (filled($setting['default_text'])) {
                $description .= ' — fallback ' . $this->plain($setting['default_text']);
            }
            $section->addText($description);
        }
    }

    private function plain(string $text): string
    {
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', str_replace('${', '$ {', $text));
    }
}
