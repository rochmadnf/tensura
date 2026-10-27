<?php

namespace Tests\Feature;

use App\Enums\AsnStatusEnum;
use App\Enums\ContractStatusEnum;
use App\Enums\JobTitleGroupEnum;
use App\Enums\StrStatusEnum;
use App\Models\MasterData\Employee;
use App\Models\MasterData\JobTitle;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmployeeExportTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guests_cannot_export_employee_data(): void
    {
        $this->get(route('empl.export'))->assertRedirectToRoute('login.index');
    }

    public function test_export_preserves_identifiers_labels_dates_and_formula_like_text(): void
    {
        $employee = Employee::factory()->withStr()->withSip()->create([
            'nik' => '0000000000000001', 'nip' => '000000000000000001', 'name' => '=1+1',
            'gender' => 2, 'contract_status' => ContractStatusEnum::PPPK, 'status' => AsnStatusEnum::AKTIF,
            'join_date' => '2024-02-29', 'str_number' => '0000000000000002', 'str_status' => StrStatusEnum::SEUMUR_HIDUP,
            'str_published_date' => '2024-02-29', 'sip_number' => 'SIP/001', 'sip_published_date' => '2024-02-29', 'sip_expired_date' => '2029-02-28',
        ]);
        $this->actingAs(UserFactory::new()->create());

        $workbook = $this->download();

        try {
            $this->assertSame(['Pegawai', 'Petunjuk', 'Referensi'], $workbook->getSheetNames());
            $sheet = $workbook->getSheetByName('Pegawai');
            $this->assertSame('0000000000000001', $sheet->getCell('A2')->getValue());
            $this->assertSame('000000000000000001', $sheet->getCell('B2')->getValue());
            $this->assertSame('=1+1', $sheet->getCell('C2')->getValue());
            $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('C2')->getDataType());
            $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('N2')->getDataType());
            $this->assertSame('Perempuan', $sheet->getCell('D2')->getValue());
            $this->assertSame($employee->jobTitle->name, $sheet->getCell('G2')->getValue());
            $this->assertSame('2024-02-29', $sheet->getCell('J2')->getValue());
            $this->assertSame('PPPK', $sheet->getCell('L2')->getValue());
            $this->assertSame('Aktif', $sheet->getCell('M2')->getValue());
            $this->assertSame('Seumur Hidup', $sheet->getCell('O2')->getValue());
            $this->assertSame('2029-02-28', $sheet->getCell('S2')->getValue());
            $this->assertSame('pilihan_job_title_id', $sheet->getDataValidation('G2')->getFormula1());
        } finally {
            $workbook->disconnectWorksheets();
        }
    }

    /** @return array<string, array{array<string, string>}> */
    public static function allScopes(): array
    {
        return ['default' => [[]], 'explicit all' => [['scope' => 'all']]];
    }

    #[DataProvider('allScopes')]
    public function test_all_scope_ignores_filters_keyword_and_pagination(array $scope): void
    {
        $job = JobTitle::factory()->create();
        Employee::factory()->for($job, 'jobTitle')->count(7)->create();
        $this->actingAs(UserFactory::new()->create());

        $workbook = $this->download([...$scope, 'filter' => 'status:9', 'keyword' => 'Tidak cocok', 'per_page' => 5, 'page' => 2]);
        try {
            $this->assertCount(7, $this->rows($workbook));
        } finally {
            $workbook->disconnectWorksheets();
        }
    }

    public function test_filtered_export_matches_listing_and_contains_all_matching_pages(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 26)->startOfDay());
        $job = JobTitle::factory()->create(['group' => JobTitleGroupEnum::DOKTER]);
        $matching = Employee::factory()->for($job, 'jobTitle')->count(7)->create([
            'name' => 'Needle', 'status' => AsnStatusEnum::AKTIF, 'gender' => 2,
            'contract_status' => ContractStatusEnum::PNS, 'join_date' => '2020-01-01',
        ]);
        Employee::factory()->for($job, 'jobTitle')->create(['name' => 'Needle', 'status' => AsnStatusEnum::PENSIUN]);
        Employee::factory()->create(['name' => 'Needle']);
        $this->actingAs(UserFactory::new()->create())->withoutVite();
        $query = ['scope' => 'filtered', 'keyword' => 'Needle', 'filter' => "job_title:{$job->uuid}|job_group:{$job->group->value}|gender:2|contract_status:1|status:1|tenure_min:5|tenure_max:10", 'per_page' => 5, 'page' => 2];

        $workbook = $this->download($query);
        try {
            $exportedNiks = array_column($this->rows($workbook), 0);
            $this->assertSame($matching->pluck('nik')->all(), $exportedNiks);
            $this->get(route('empl.index', $query))->assertInertia(fn(Assert $page) => $page
                ->where('resources.meta.total', 7)->where('resources.data.0.nik', $exportedNiks[5]));
        } finally {
            $workbook->disconnectWorksheets();
        }
    }

    public function test_empty_export_has_headers_and_no_invalid_empty_job_range(): void
    {
        $this->actingAs(UserFactory::new()->create());

        $workbook = $this->download();
        try {
            $this->assertSame('NIK', $workbook->getSheetByName('Pegawai')->getCell('A1')->getValue());
            $this->assertSame([], $this->rows($workbook));
            $this->assertNull($workbook->getNamedRange('pilihan_job_title_id'));
            $this->assertSame('Petunjuk Impor Pegawai', $workbook->getSheetByName('Petunjuk')->getCell('A1')->getValue());
        } finally {
            $workbook->disconnectWorksheets();
        }
    }

    public function test_export_includes_employee_without_job_title(): void
    {
        $employee = Employee::factory()->create(['job_title_id' => null]);
        $this->actingAs(UserFactory::new()->create());

        $workbook = $this->download();
        try {
            $this->assertSame($employee->nik, $workbook->getSheetByName('Pegawai')->getCell('A2')->getValue());
            $this->assertContains($workbook->getSheetByName('Pegawai')->getCell('G2')->getValue(), [null, '']);
        } finally {
            $workbook->disconnectWorksheets();
        }
    }

    public function test_filtered_empty_result_does_not_fall_back_to_all_employees(): void
    {
        Employee::factory()->create(['status' => AsnStatusEnum::AKTIF]);
        $this->actingAs(UserFactory::new()->create());

        $workbook = $this->download(['scope' => 'filtered', 'filter' => 'status:9']);
        try {
            $this->assertSame([], $this->rows($workbook));
        } finally {
            $workbook->disconnectWorksheets();
        }
    }

    public function test_export_can_be_imported_again_after_employees_are_removed(): void
    {
        $employee = Employee::factory()->withStr()->withSip()->create(['nik' => '0000000000000001', 'name' => 'Siti Aminah']);
        $this->actingAs(UserFactory::new()->create());
        $response = $this->get(route('empl.export'))->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();
        try {
            $file = UploadedFile::fake()->createWithContent('pegawai.xlsx', file_get_contents($path));
        } finally {
            unlink($path);
        }
        $employee->delete();

        $this->postJson(route('empl.import.store'), ['file' => $file])->assertOk()
            ->assertJsonPath('imported', 1)->assertJsonPath('issues', []);
        $this->assertDatabaseHas('employees', ['nik' => '0000000000000001', 'name' => 'Siti Aminah', 'job_title_id' => $employee->job_title_id]);
    }

    public function test_export_over_one_thousand_rows_is_complete(): void
    {
        $job = JobTitle::factory()->create();
        Employee::factory()->for($job, 'jobTitle')->count(1001)->create();
        $this->actingAs(UserFactory::new()->create());

        $workbook = $this->download();
        try {
            $rows = $this->rows($workbook);
            $this->assertCount(1001, $rows);
            $this->assertCount(1001, array_unique(array_column($rows, 0)));
            $this->assertSame('list', $workbook->getSheetByName('Pegawai')->getDataValidation('G1002')->getType());
        } finally {
            $workbook->disconnectWorksheets();
        }
    }

    public function test_unknown_scope_returns_422(): void
    {
        $this->actingAs(UserFactory::new()->create());

        $this->getJson(route('empl.export', ['scope' => 'other']))->assertUnprocessable()
            ->assertInvalid(['scope' => 'Pilih seluruh pegawai atau hasil pencarian/filter aktif.']);
    }

    /** @param array<string, mixed> $query */
    private function download(array $query = []): Spreadsheet
    {
        $response = $this->get(route('empl.export', $query))->assertOk()->assertDownload('data-pegawai.xlsx');
        $path = $response->baseResponse->getFile()->getPathname();
        try {
            return IOFactory::load($path);
        } finally {
            unlink($path);
        }
    }

    /** @return list<list<mixed>> */
    private function rows(Spreadsheet $workbook): array
    {
        $rows = $workbook->getSheetByName('Pegawai')->toArray(null, false, false);

        return array_values(array_filter(array_slice($rows, 1), fn(array $row): bool => array_filter($row, fn(mixed $value): bool => $value !== null && $value !== '') !== []));
    }
}
