<?php

namespace App\Services\Employee;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Events\BeforeImport;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class ReadEmployeeImport implements Import, WithEvents, WithMultipleSheets
{
    /** @var list<array{row: int, data: array<string, mixed>, errors: array<string, string>}> */
    private array $rows = [];

    /** @return list<array{row: int, data: array<string, mixed>, errors: array<string, string>}> */
    public function read(UploadedFile $file): array
    {
        $this->rows = [];
        try {
            $info = collect((new Xlsx())->listWorksheetInfo($file->getRealPath()))->firstWhere('worksheetName', 'Pegawai');
            if ($info === null) {
                throw ValidationException::withMessages(['file' => 'Sheet Pegawai tidak ditemukan. Gunakan template aplikasi.']);
            }
            if ($info['totalRows'] > EmployeeImportTemplate::MAX_ROWS + 1) {
                throw ValidationException::withMessages(['file' => 'Maksimal 1.000 baris pengisian pada sheet Pegawai (baris 2–1001).']);
            }
            if ($info['totalColumns'] > count(EmployeeImportTemplate::columns())) {
                throw ValidationException::withMessages(['file' => 'Kolom file tidak sesuai. Gunakan template aplikasi tanpa menambahkan kolom.']);
            }
            Excel::import($this, $file, readerType: ExcelFormat::XLSX);
        } catch (ReaderException $exception) {
            throw ValidationException::withMessages(['file' => 'File Excel tidak dapat dibaca. Gunakan file .xlsx yang valid dan tidak diproteksi kata sandi.']);
        }

        if ($this->rows === []) {
            throw ValidationException::withMessages(['file' => 'Sheet Pegawai belum berisi data. Isi minimal satu pegawai.']);
        }

        return $this->rows;
    }

    /** @return array<string, Import> */
    public function sheets(): array
    {
        return ['Pegawai' => $this];
    }

    /** @return array<class-string, callable> */
    public function registerEvents(): array
    {
        return [BeforeImport::class => function (BeforeImport $event): void {
            $workbook = $event->reader->getDelegate();
            $sheet = $workbook->getSheetByName('Pegawai');
            $columns = EmployeeImportTemplate::columns();
            foreach (array_values($columns) as $index => $label) {
                $cell = $sheet->getCell([$index + 1, 1]);
                if ($cell->getDataType() === DataType::TYPE_FORMULA || $cell->getValue() !== $label) {
                    throw ValidationException::withMessages(['file' => 'Header tidak sesuai. Gunakan template aplikasi tanpa mengubah urutan atau nama kolom.']);
                }
            }

            $previousCalendar = Date::getExcelCalendar();
            Date::setExcelCalendar($workbook->getExcelCalendar());
            try {
                for ($row = 2; $row <= $sheet->getHighestDataRow(); $row++) {
                    $data = [];
                    $errors = [];
                    foreach (array_keys($columns) as $index => $field) {
                        $cell = $sheet->getCell([$index + 1, $row]);
                        $value = $cell->getValue();
                        if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                            $errors[$field] = $columns[$field] . ' tidak boleh berisi formula. Isi nilai langsung.';
                            $data[$field] = null;

                            continue;
                        }
                        if ($cell->getDataType() === DataType::TYPE_ERROR || is_bool($value)) {
                            $errors[$field] = $columns[$field] . ' berisi nilai sel yang tidak valid.';
                            $data[$field] = null;

                            continue;
                        }
                        if (in_array($field, ['nik', 'nip', 'str_number', 'sip_number'], true) && is_numeric($value) && !is_string($value)) {
                            $errors[$field] = $columns[$field] . ' harus berupa teks. Format kolom sebagai Teks lalu salin ulang dari sumber asli agar digit tidak berubah.';
                        }
                        if (str_ends_with($field, '_date') && (is_int($value) || is_float($value))) {
                            if ($value >= 1 && $value < 2958466) {
                                $value = Date::excelToDateTimeObject($value)->format('Y-m-d');
                            } else {
                                $errors[$field] = $columns[$field] . ' harus berupa tanggal yang valid.';
                                $value = null;
                            }
                        }
                        $value = $value === null ? null : trim((string) $value);
                        $data[$field] = $value === '' ? null : $value;
                    }
                    if ($errors !== [] || array_filter($data, fn(mixed $value): bool => $value !== null) !== []) {
                        $this->rows[] = ['row' => $row, 'data' => $data, 'errors' => $errors];
                    }
                }
            } finally {
                Date::setExcelCalendar($previousCalendar);
            }
        }];
    }
}
