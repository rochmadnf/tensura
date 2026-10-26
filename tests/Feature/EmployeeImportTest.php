<?php

namespace Tests\Feature;

use App\Models\MasterData\Employee;
use App\Models\MasterData\JobTitle;
use App\Services\Employee\EmployeeImportTemplate;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class EmployeeImportTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guests_cannot_access_import_page_template_or_upload(): void
    {
        $this->get(route('empl.import.create'))->assertRedirectToRoute('login.index');
        $this->get(route('empl.import.template'))->assertRedirectToRoute('login.index');
        $this->post(route('empl.import.store'))->assertRedirectToRoute('login.index');
        $this->assertDatabaseCount('employees', 0);
    }

    public function test_empty_master_disables_page_and_rejects_template_and_import(): void
    {
        $this->actingAs(UserFactory::new()->create())->withoutVite();

        $this->get(route('empl.import.create'))->assertOk()->assertInertia(fn(Assert $page) => $page
            ->component('employee/import')->where('hasJobTitles', false));
        $this->getJson(route('empl.import.template'))->assertUnprocessable()->assertInvalid(['file' => 'Tambahkan minimal satu jabatan']);
        $this->postJson(route('empl.import.store'), ['file' => $this->file([])])
            ->assertUnprocessable()->assertInvalid(['file' => 'Tambahkan minimal satu jabatan']);
        $this->assertDatabaseCount('employees', 0);
    }

    public function test_template_contains_sorted_dropdowns_text_identities_and_instructions(): void
    {
        JobTitle::factory()->create(['name' => 'Perawat']);
        JobTitle::factory()->create(['name' => 'Dokter']);
        $this->actingAs(UserFactory::new()->create())->withoutVite();
        $this->get(route('empl.import.create'))->assertInertia(fn(Assert $page) => $page->where('hasJobTitles', true));

        $response = $this->get(route('empl.import.template'))->assertOk()->assertDownload('template-impor-pegawai.xlsx');
        $workbook = IOFactory::load($response->baseResponse->getFile()->getPathname());

        try {
            $this->assertSame(['Pegawai', 'Petunjuk', 'Referensi'], $workbook->getSheetNames());
            $sheet = $workbook->getSheetByName('Pegawai');
            $this->assertSame('NIK', $sheet->getCell('A1')->getValue());
            $this->assertSame('@', $sheet->getStyle('A2')->getNumberFormat()->getFormatCode());
            $this->assertSame('@', $sheet->getStyle('B1001')->getNumberFormat()->getFormatCode());
            $this->assertSame('list', $sheet->getDataValidation('G2')->getType());
            $this->assertSame('stop', $sheet->getDataValidation('G1001')->getErrorStyle());
            $this->assertTrue($sheet->getDataValidation('G1001')->getShowDropDown());
            $this->assertTrue($sheet->getDataValidation('G2')->getShowErrorMessage());
            $this->assertFalse($sheet->getDataValidation('G2')->getAllowBlank());
            $this->assertSame('pilihan_job_title_id', $sheet->getDataValidation('G2')->getFormula1());
            $range = $workbook->getNamedRange('pilihan_job_title_id');
            $this->assertSame("'Referensi'!\$A\$1:\$A\$2", $range->getRange());
            $this->assertSame('hidden', $workbook->getSheetByName('Referensi')->getSheetState());
            $this->assertSame('Dokter', $range->getWorksheet()->getCell('A1')->getValue());
            $this->assertSame('Perawat', $range->getWorksheet()->getCell('A2')->getValue());
            $this->assertStringContainsString('Wajib:', $workbook->getSheetByName('Petunjuk')->getCell('A3')->getValue());
        } finally {
            $workbook->disconnectWorksheets();
            unlink($response->baseResponse->getFile()->getPathname());
        }
    }

    public function test_downloaded_template_can_be_filled_and_imported(): void
    {
        JobTitle::factory()->create(['name' => 'Dokter']);
        $this->actingAs(UserFactory::new()->create());
        $response = $this->get(route('empl.import.template'))->assertOk();
        $workbook = IOFactory::load($response->baseResponse->getFile()->getPathname());
        try {
            $this->writeRow($workbook, 2, $this->row());
            $file = $this->upload($workbook);
        } finally {
            $workbook->disconnectWorksheets();
            unlink($response->baseResponse->getFile()->getPathname());
        }

        $this->postJson(route('empl.import.store'), ['file' => $file])
            ->assertOk()->assertJsonPath('imported', 1)->assertJsonPath('issues', []);
        $this->assertDatabaseHas('employees', ['nik' => '0000000000000001', 'nip' => '000000000000000001']);
    }

    public function test_mixed_file_preserves_row_numbers_saves_valid_rows_and_can_be_retried(): void
    {
        $job = JobTitle::factory()->create(['name' => 'Dokter']);
        $this->actingAs(UserFactory::new()->create());
        $rows = [
            $this->row(['job_title_id' => ' dokter ']),
            [],
            $this->row(['name' => 'Duplikat']),
            $this->row(['nik' => '0000000000000002', 'nip' => null, 'job_title_id' => 'Tidak tersedia']),
            $this->row(['nik' => '0000000000000003', 'nip' => null]),
        ];

        $this->postJson(route('empl.import.store'), ['file' => $this->file($rows)])
            ->assertOk()->assertJsonPath('total', 4)->assertJsonPath('processed', 4)
            ->assertJsonPath('imported', 2)->assertJsonPath('duplicate', 1)->assertJsonPath('invalid', 1)
            ->assertJsonPath('interrupted', false)->assertJsonPath('issues.0.row', 4)
            ->assertJsonPath('issues.0.errors.nik', 'NIK sudah digunakan. Pegawai dilewati.')
            ->assertJsonPath('issues.1.row', 5)->assertJsonPath('issues.1.errors.job_title_id', 'Jabatan belum tersedia. Tambahkan pada Master Jabatan lalu impor ulang.');
        $this->assertDatabaseCount('employees', 2);
        $this->assertDatabaseHas('employees', ['nik' => '0000000000000001', 'name' => 'Siti Aminah', 'job_title_id' => $job->id, 'gender' => 2, 'join_date' => '2024-02-29']);

        $this->postJson(route('empl.import.store'), ['file' => $this->file($rows)])
            ->assertOk()->assertJsonPath('imported', 0)->assertJsonPath('duplicate', 3);
        $this->assertDatabaseCount('employees', 2);
        $this->assertDatabaseMissing('employees', ['name' => 'Duplikat']);
    }

    /** @return array<string, array{string}> */
    public static function uniqueFields(): array
    {
        return ['NIK' => ['nik'], 'NIP' => ['nip'], 'email' => ['email'], 'STR' => ['str_number'], 'SIP' => ['sip_number']];
    }

    #[DataProvider('uniqueFields')]
    public function test_existing_unique_value_skips_employee_without_updating_it(string $field): void
    {
        $existing = Employee::factory()->withStr()->withSip()->create();
        $this->actingAs(UserFactory::new()->create());
        $row = $this->row(['job_title_id' => $existing->jobTitle->name, $field => $existing->$field]);

        $this->postJson(route('empl.import.store'), ['file' => $this->file([$row])])
            ->assertOk()->assertJsonPath('imported', 0)->assertJsonPath('duplicate', 1)
            ->assertJsonPath('issues.0.errors.' . $field, EmployeeImportTemplate::columns()[$field] . ' sudah digunakan. Pegawai dilewati.');
        $this->assertDatabaseCount('employees', 1);
        $this->assertSame($existing->name, $existing->fresh()->name);
    }

    #[DataProvider('uniqueFields')]
    public function test_duplicate_within_file_skips_later_row(string $field): void
    {
        JobTitle::factory()->create(['name' => 'Dokter']);
        $this->actingAs(UserFactory::new()->create());
        $first = $this->row(['email' => 'siti@example.test', 'str_number' => 'STR0000000000001', 'str_status' => 'Seumur Hidup', 'str_published_date' => '2024-02-29', 'sip_number' => 'SIP/001', 'sip_published_date' => '2024-02-29', 'sip_expired_date' => '2029-02-28']);
        $second = [...$first, 'nik' => '0000000000000002', 'nip' => '000000000000000002', 'email' => 'budi@example.test', 'str_number' => 'STR0000000000002', 'sip_number' => 'SIP/002', $field => $first[$field]];

        $this->postJson(route('empl.import.store'), ['file' => $this->file([$first, $second])])
            ->assertOk()->assertJsonPath('imported', 1)->assertJsonPath('duplicate', 1)
            ->assertJsonPath('issues.0.row', 3)->assertJsonStructure(['issues' => [['errors' => [$field]]]]);
        $this->assertDatabaseCount('employees', 1);
    }

    /** @return array<string, array{array<string, mixed>, string, string}> */
    public static function invalidRows(): array
    {
        return [
            'numeric NIK' => [['nik' => 1234567890123456], 'nik', 'harus berupa teks'],
            'numeric NIP' => [['nip' => 123456789012345678], 'nip', 'harus berupa teks'],
            'numeric STR' => [['str_number' => 1234567890123456], 'str_number', 'harus berupa teks'],
            'numeric SIP' => [['sip_number' => 1234567890123456], 'sip_number', 'harus berupa teks'],
            'short NIK' => [['nik' => '123'], 'nik', '16 digit'],
            'formula' => [['name' => '=1+1'], 'name', 'tidak boleh berisi formula'],
            'gender' => [['gender' => 'Lainnya'], 'gender', 'Pilih nilai dari dropdown'],
            'contract' => [['contract_status' => 'Tidak dikenal'], 'contract_status', 'Pilih nilai dari dropdown'],
            'status' => [['status' => 'Tidak dikenal'], 'status', 'Pilih nilai dari dropdown'],
            'future join date' => [['join_date' => '2099-01-01'], 'join_date', 'maksimal tanggal hari ini'],
            'invalid date' => [['join_date' => '2024-02-30'], 'join_date', 'tanggal yang valid'],
            'incomplete STR' => [['str_number' => 'STR0000000000001'], 'str_status', 'wajib diisi bersama'],
            'incomplete SIP' => [['sip_number' => 'SIP/001'], 'sip_expired_date', 'wajib diisi bersama'],
            'SIP expiry' => [['sip_number' => 'SIP/001', 'sip_published_date' => '2024-02-29', 'sip_expired_date' => '2024-02-28'], 'sip_expired_date', 'setelah Tanggal Terbit SIP'],
        ];
    }

    #[DataProvider('invalidRows')]
    public function test_invalid_row_is_reported_and_valid_row_still_saves(array $changes, string $field, string $message): void
    {
        JobTitle::factory()->create(['name' => 'Dokter']);
        $this->actingAs(UserFactory::new()->create());
        $this->freezeTime();

        $this->postJson(route('empl.import.store'), ['file' => $this->file([
            $this->row($changes), $this->row(['nik' => '0000000000000002', 'nip' => null]),
        ])])->assertOk()->assertJsonPath('invalid', 1)->assertJsonPath('imported', 1)
            ->assertJsonPath('issues.0.errors.' . $field, fn(string $value): bool => str_contains($value, $message));
        $this->assertDatabaseCount('employees', 1);
        $this->assertDatabaseHas('employees', ['nik' => '0000000000000002']);
    }

    public function test_ambiguous_job_name_is_not_guessed(): void
    {
        JobTitle::factory()->create(['name' => 'Dokter']);
        JobTitle::factory()->create(['name' => 'DOKTER']);
        $this->actingAs(UserFactory::new()->create());

        $this->postJson(route('empl.import.store'), ['file' => $this->file([$this->row()])])
            ->assertOk()->assertJsonPath('invalid', 1)->assertJsonPath('issues.0.errors.job_title_id', 'Nama jabatan cocok dengan beberapa master. Perbaiki nama pada Master Jabatan lalu impor ulang.');
        $this->assertDatabaseCount('employees', 0);
    }

    public function test_excel_dates_and_complete_registrations_are_saved(): void
    {
        JobTitle::factory()->create(['name' => 'Dokter']);
        $this->actingAs(UserFactory::new()->create());
        $row = $this->row(['join_date' => Date::stringToExcel('2024-02-29'), 'str_number' => 'STR0000000000001', 'str_status' => 'Seumur Hidup', 'str_published_date' => '2024-02-29', 'sip_number' => 'SIP/001', 'sip_published_date' => '2024-02-29', 'sip_expired_date' => '2029-02-28']);

        $this->postJson(route('empl.import.store'), ['file' => $this->file([$row])])
            ->assertOk()->assertJsonPath('imported', 1);
        $this->assertDatabaseHas('employees', ['join_date' => '2024-02-29', 'str_status' => 1, 'str_number' => 'STR0000000000001', 'sip_expired_date' => '2029-02-28']);
    }

    public function test_excel_dates_use_the_calendar_stored_in_the_workbook(): void
    {
        JobTitle::factory()->create(['name' => 'Dokter']);
        $this->actingAs(UserFactory::new()->create());
        $workbook = $this->workbook([$this->row(['join_date' => 43889])]);
        $workbook->setExcelCalendar(Date::CALENDAR_MAC_1904);
        try {
            $file = $this->upload($workbook);
        } finally {
            $workbook->disconnectWorksheets();
        }

        $this->postJson(route('empl.import.store'), ['file' => $file])
            ->assertOk()->assertJsonPath('imported', 1);
        $this->assertDatabaseHas('employees', ['join_date' => '2024-02-29']);
    }

    /** @return array<string, array{string}> */
    public static function invalidStructures(): array
    {
        return ['header' => ['header'], 'missing sheet' => ['missing'], 'too many rows' => ['rows'], 'extra column' => ['columns'], 'empty' => ['empty']];
    }

    #[DataProvider('invalidStructures')]
    public function test_invalid_structure_returns_422_before_saving_any_row(string $case): void
    {
        JobTitle::factory()->create(['name' => 'Dokter']);
        $this->actingAs(UserFactory::new()->create());
        $workbook = $this->workbook($case === 'empty' ? [] : [$this->row()]);
        $sheet = $workbook->getActiveSheet();
        match ($case) {
            'header' => $sheet->setCellValue('A1', 'Salah'),
            'missing' => $sheet->setTitle('Sheet1'),
            'rows' => $sheet->setCellValue('A1002', 'kelebihan'),
            'columns' => $sheet->setCellValue('T1', 'tambahan'),
            default => null,
        };
        try {
            $file = $this->upload($workbook);
        } finally {
            $workbook->disconnectWorksheets();
        }

        $this->postJson(route('empl.import.store'), ['file' => $file])
            ->assertUnprocessable()->assertInvalid(['file']);
        $this->assertDatabaseCount('employees', 0);
    }

    public function test_accepts_last_template_row_and_ignores_empty_optional_values(): void
    {
        JobTitle::factory()->create(['name' => 'Dokter']);
        $this->actingAs(UserFactory::new()->create());
        $workbook = $this->workbook([$this->row(['nip' => null])]);
        $this->writeRow($workbook, 1001, $this->row(['nik' => '0000000000000002', 'nip' => '  ']));
        try {
            $file = $this->upload($workbook);
        } finally {
            $workbook->disconnectWorksheets();
        }

        $this->postJson(route('empl.import.store'), ['file' => $file])
            ->assertOk()->assertJsonPath('total', 2)->assertJsonPath('imported', 2);
        $this->assertDatabaseCount('employees', 2);
        $this->assertSame(2, Employee::query()->whereNull('nip')->count());
    }

    public function test_accepts_one_thousand_valid_employees_in_one_file(): void
    {
        JobTitle::factory()->create(['name' => 'Dokter']);
        $this->actingAs(UserFactory::new()->create());
        $rows = [];
        for ($index = 1; $index <= 1000; $index++) {
            $rows[] = $this->row(['nik' => str_pad((string) $index, 16, '0', STR_PAD_LEFT), 'nip' => null]);
        }

        $this->postJson(route('empl.import.store'), ['file' => $this->file($rows)])
            ->assertOk()->assertJsonPath('total', 1000)->assertJsonPath('processed', 1000)
            ->assertJsonPath('imported', 1000)->assertJsonPath('issues', [])->assertJsonPath('interrupted', false);
        $this->assertDatabaseCount('employees', 1000);
    }

    public function test_upload_requires_xlsx_and_enforces_size_limit(): void
    {
        JobTitle::factory()->create();
        $this->actingAs(UserFactory::new()->create());

        $this->postJson(route('empl.import.store'))->assertUnprocessable()->assertInvalid(['file' => 'Pilih file Excel']);
        $this->postJson(route('empl.import.store'), ['file' => UploadedFile::fake()->createWithContent('pegawai.xlsx', 'not an xlsx')])
            ->assertUnprocessable()->assertInvalid(['file' => 'File Excel tidak dapat dibaca']);
        $this->postJson(route('empl.import.store'), ['file' => UploadedFile::fake()->createWithContent('pegawai.csv', 'nik,nama')])
            ->assertUnprocessable()->assertInvalid(['file' => 'berformat .xlsx']);
        $this->postJson(route('empl.import.store'), ['file' => $this->file([$this->row()])->size(2049)])
            ->assertUnprocessable()->assertInvalid(['file' => 'maksimal 2 MB']);
        $this->assertDatabaseCount('employees', 0);
    }

    public function test_server_failure_preserves_completed_rows_and_reports_interruption(): void
    {
        JobTitle::factory()->create(['name' => 'Dokter']);
        $this->actingAs(UserFactory::new()->create());
        Employee::creating(function (Employee $employee): void {
            if ($employee->name === 'Gangguan') {
                throw new RuntimeException('Simulated persistence failure');
            }
        });
        try {
            $this->postJson(route('empl.import.store'), ['file' => $this->file([
                $this->row(), $this->row(['nik' => '0000000000000002', 'nip' => null, 'name' => 'Gangguan']),
                $this->row(['nik' => '0000000000000003', 'nip' => null]),
            ])])->assertOk()->assertJsonPath('total', 3)->assertJsonPath('processed', 1)
                ->assertJsonPath('imported', 1)->assertJsonPath('interrupted', true);
        } finally {
            Event::forget('eloquent.creating: ' . Employee::class);
        }
        $this->assertDatabaseCount('employees', 1);
        $this->assertDatabaseHas('employees', ['nik' => '0000000000000001']);
    }

    /** @param array<string, mixed> $changes
     * @return array<string, mixed>
     */
    private function row(array $changes = []): array
    {
        return [...array_fill_keys(array_keys(EmployeeImportTemplate::columns()), null),
            'nik' => '0000000000000001', 'nip' => '000000000000000001', 'name' => 'Siti Aminah',
            'gender' => 'Perempuan', 'job_title_id' => 'Dokter', 'join_date' => '2024-02-29',
            'contract_status' => 'PNS', 'status' => 'Aktif', ...$changes];
    }

    /** @param list<array<string, mixed>> $rows */
    private function file(array $rows): UploadedFile
    {
        $workbook = $this->workbook($rows);
        try {
            return $this->upload($workbook);
        } finally {
            $workbook->disconnectWorksheets();
        }
    }

    /** @param list<array<string, mixed>> $rows */
    private function workbook(array $rows): Spreadsheet
    {
        $workbook = new Spreadsheet();
        $workbook->getActiveSheet()->setTitle('Pegawai')->fromArray([array_values(EmployeeImportTemplate::columns())]);
        foreach ($rows as $index => $row) {
            $this->writeRow($workbook, $index + 2, $row);
        }

        return $workbook;
    }

    /** @param array<string, mixed> $data */
    private function writeRow(Spreadsheet $workbook, int $row, array $data): void
    {
        foreach (array_keys(EmployeeImportTemplate::columns()) as $index => $field) {
            $value = $data[$field] ?? null;
            $type = is_string($value) ? (str_starts_with($value, '=') ? DataType::TYPE_FORMULA : DataType::TYPE_STRING) : ($value === null ? DataType::TYPE_NULL : DataType::TYPE_NUMERIC);
            $workbook->getSheetByName('Pegawai')->setCellValueExplicit([$index + 1, $row], $value, $type);
        }
    }

    private function upload(Spreadsheet $workbook): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'employee-import-');
        try {
            (new Xlsx($workbook))->save($path);

            return UploadedFile::fake()->createWithContent('pegawai.xlsx', file_get_contents($path));
        } finally {
            unlink($path);
        }
    }
}
