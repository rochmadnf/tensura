<?php

namespace App\Services\Employee;

use App\Enums\AsnStatusEnum;
use App\Enums\ContractStatusEnum;
use App\Enums\StrStatusEnum;
use App\Models\MasterData\Employee;
use App\Models\MasterData\JobTitle;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\NamedRange;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EmployeeImportTemplate implements FromArray, WithEvents
{
    public const int MAX_ROWS = 1000;

    public const string JOB_TITLE_REQUIRED = 'Tambahkan minimal satu jabatan sebelum mengunduh template dan mengimpor pegawai.';

    /** @return array<string, string> */
    public static function columns(): array
    {
        return [
            'nik' => 'NIK', 'nip' => 'NIP', 'name' => 'Nama', 'gender' => 'Jenis Kelamin',
            'front_title' => 'Gelar Depan', 'back_title' => 'Gelar Belakang', 'job_title_id' => 'Jabatan',
            'rank' => 'Pangkat', 'grade' => 'Golongan', 'join_date' => 'Tanggal Bergabung',
            'email' => 'Email', 'contract_status' => 'Status Kontrak', 'status' => 'Status Pegawai',
            'str_number' => 'Nomor STR', 'str_status' => 'Status STR', 'str_published_date' => 'Tanggal Terbit STR',
            'sip_number' => 'Nomor SIP', 'sip_published_date' => 'Tanggal Terbit SIP', 'sip_expired_date' => 'Tanggal Berakhir SIP',
        ];
    }

    /** @return array<string, list<array{id: int, label: string}>> */
    public static function options(): array
    {
        return [
            'gender' => Employee::genderOptions(),
            'contract_status' => ContractStatusEnum::options(),
            'status' => AsnStatusEnum::options(),
            'str_status' => StrStatusEnum::options(),
        ];
    }

    /** @return list<string> */
    public static function instructions(): array
    {
        return [
            'Gunakan sheet Pegawai, jangan mengubah header. Maksimal 1.000 baris pengisian (baris 2–1001), ukuran file 2 MB.',
            'Wajib: NIK, Nama, Jenis Kelamin, Jabatan, Tanggal Bergabung, Status Kontrak, Status Pegawai. Kolom lain boleh kosong.',
            'Pilih Jabatan, Jenis Kelamin, dan Status menggunakan dropdown. Jabatan harus tersedia di aplikasi saat impor.',
            'Unduh ulang template setelah master jabatan berubah; daftar dalam file tidak diperbarui otomatis.',
            'NIK 16 digit dan NIP 18 digit harus berupa teks. Salin dari sumber asli agar digit tidak hilang.',
            'NIK, NIP, Nomor STR, dan Nomor SIP menggunakan format teks, bukan angka.',
            'Tanggal: gunakan tanggal Excel atau teks YYYY-MM-DD. Tanggal bergabung/terbit maksimal hari ini.',
            'STR: isi Nomor STR (16 karakter), Status STR, dan Tanggal Terbit STR bersama-sama atau kosongkan semuanya.',
            'SIP: isi Nomor SIP, Tanggal Terbit SIP, dan Tanggal Berakhir SIP bersama-sama atau kosongkan semuanya.',
            'Tanggal Berakhir SIP harus setelah Tanggal Terbit SIP.',
            'Baris valid disimpan. Duplikat NIK/NIP/email/STR/SIP dan baris tidak valid dilewati dengan laporan.',
            'Data lama tidak diperbarui. Unggah ulang aman; baris yang sudah tersimpan akan dilewati.',
            'Baris kosong diabaikan. Formula pada data pegawai tidak diperbolehkan.',
        ];
    }

    /** @return list<list<string>> */
    public function array(): array
    {
        return [array_values(self::columns())];
    }

    /** @return array<class-string, callable> */
    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event): void {
            $sheet = $event->sheet->getDelegate();
            $sheet->setTitle('Pegawai');
            $sheet->freezePane('D2');
            $sheet->getStyle('A1:S1')->getFont()->setBold(true);
            $lastRow = max(self::MAX_ROWS + 1, $sheet->getHighestDataRow());
            $sheet->setAutoFilter('A1:S' . $lastRow);

            $workbook = $sheet->getParent();
            $instructions = $workbook->createSheet()->setTitle('Petunjuk');
            $reference = $workbook->createSheet()->setTitle('Referensi');
            $reference->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
            $lists = ['job_title_id' => JobTitle::query()->orderBy('name')->pluck('name')->all()];
            foreach (self::options() as $field => $options) {
                $lists[$field] = array_column($options, 'label');
            }

            foreach (array_keys(self::columns()) as $index => $field) {
                $column = Coordinate::stringFromColumnIndex($index + 1);
                $sheet->getColumnDimension($column)->setWidth(25);
                $format = str_ends_with($field, '_date') ? NumberFormat::FORMAT_DATE_YYYYMMDD : NumberFormat::FORMAT_TEXT;
                $sheet->getStyle($column . '2:' . $column . $lastRow)->getNumberFormat()->setFormatCode($format);
            }

            foreach ($lists as $field => $labels) {
                if ($labels === []) {
                    continue;
                }
                $referenceColumn = Coordinate::stringFromColumnIndex(array_search($field, array_keys($lists), true) + 1);
                foreach ($labels as $index => $label) {
                    $reference->setCellValueExplicit($referenceColumn . ($index + 1), $label, DataType::TYPE_STRING);
                }
                $range = 'pilihan_' . $field;
                $workbook->addNamedRange(new NamedRange($range, $reference, '$' . $referenceColumn . '$1:$' . $referenceColumn . '$' . count($labels)));
                $column = Coordinate::stringFromColumnIndex(array_search($field, array_keys(self::columns()), true) + 1);
                $validation = (new DataValidation())
                    ->setType(DataValidation::TYPE_LIST)
                    ->setErrorStyle(DataValidation::STYLE_STOP)
                    ->setAllowBlank($field === 'str_status')
                    ->setShowDropDown(true)
                    ->setShowInputMessage(true)
                    ->setShowErrorMessage(true)
                    ->setPromptTitle(self::columns()[$field])
                    ->setPrompt('Pilih salah satu nilai dari dropdown.')
                    ->setErrorTitle('Pilihan tidak valid')
                    ->setError('Pilih nilai yang tersedia pada dropdown.')
                    ->setFormula1($range);
                $sheet->setDataValidation($column . '2:' . $column . $lastRow, $validation);
            }

            $instructions->fromArray(array_map(fn(string $line): array => [$line], ['Petunjuk Impor Pegawai', ...self::instructions()]));
            $instructionRow = 16;
            foreach ($lists as $field => $labels) {
                $instructions->setCellValueExplicit('A' . $instructionRow++, self::columns()[$field], DataType::TYPE_STRING);
                foreach ($labels as $label) {
                    $instructions->setCellValueExplicit('A' . $instructionRow++, $label, DataType::TYPE_STRING);
                }
                $instructionRow++;
            }
            $instructions->getColumnDimension('A')->setWidth(110);
            $instructions->getStyle('A1:A' . $instructionRow)->getAlignment()->setWrapText(true);
            $workbook->setActiveSheetIndex(0);
        }];
    }
}
