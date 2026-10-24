<?php

namespace Tests\Feature;

use App\Enums\AsnStatusEnum;
use App\Enums\ContractStatusEnum;
use App\Models\MasterData\Employee;
use App\Models\MasterData\JobTitle;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmployeeStoreTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_create_page_provides_uuid_job_options_and_existing_enums(): void
    {
        $jobTitle = JobTitle::factory()->create();
        $this->actingAs(UserFactory::new()->create())
            ->withoutVite()
            ->get(route('empl.create'))
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('employee/create')
                    ->where('page.id', 'a30c21b9-15ef-4dc0-97ef-524ead13be55')
                    ->where('jobTitles', [['id' => $jobTitle->uuid, 'label' => $jobTitle->name]])
                    ->where('contractStatuses', ContractStatusEnum::options())
                    ->where('statuses', AsnStatusEnum::options()),
            );
    }

    public function test_store_preserves_identity_strings_maps_job_uuid_and_ignores_system_fields(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 24)->startOfDay());
        $data = $this->payload();
        $uuid = (string) Str::uuid();
        $this->actingAs(UserFactory::new()->create())
            ->post(route('empl.store'), [...$data, 'id' => 999, 'uuid' => $uuid, 'created_at' => '2000-01-01'])
            ->assertRedirectToRoute('empl.index')
            ->assertSessionHasNoErrors();

        $employee = Employee::query()->sole();
        $this->assertSame($data['nik'], $employee->nik);
        $this->assertSame($data['nip'], $employee->nip);
        $this->assertSame($data['name'], $employee->name);
        $this->assertSame($data['join_date'], $employee->join_date->toDateString());
        $this->assertSame($data['job_title_id'], $employee->jobTitle->uuid);
        $this->assertIsInt($employee->job_title_id);
        $this->assertIsInt($employee->id);
        $this->assertNotSame(999, $employee->id);
        $this->assertTrue(Str::isUuid($employee->uuid));
        $this->assertNotSame($uuid, $employee->uuid);
        $this->assertSame('2026-10-24', $employee->created_at->toDateString());
    }

    public function test_optional_empty_fields_are_saved_as_null_and_can_be_repeated(): void
    {
        $data = [
            ...$this->payload(),
            'nip' => '',
            'email' => '',
            'front_title' => '',
            'back_title' => '',
            'rank' => '',
            'grade' => '',
        ];
        $this->actingAs(UserFactory::new()->create());
        $this->post(route('empl.store'), $data)->assertSessionHasNoErrors();
        $this->post(route('empl.store'), [...$data, 'nik' => '0000000000000002'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('employees', 2);
        $this->assertDatabaseHas('employees', [
            'nik' => $data['nik'],
            'nip' => null,
            'email' => null,
            'front_title' => null,
            'back_title' => null,
            'rank' => null,
            'grade' => null,
        ]);
    }

    public function test_join_date_uses_the_application_day_at_the_utc_day_boundary(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-23 16:30:00', 'UTC'));
        $data = [...$this->payload(), 'join_date' => '2026-10-24'];
        $this->actingAs(UserFactory::new()->create())
            ->post(route('empl.store'), $data)
            ->assertRedirectToRoute('empl.index')
            ->assertSessionHasNoErrors();
        $this->post(route('empl.store'), [
            ...$data,
            'nik' => '0000000000000002',
            'nip' => null,
            'email' => null,
            'join_date' => '2026-10-25',
        ])->assertInvalid(['join_date' => 'Tanggal Bergabung maksimal tanggal hari ini.']);
        $this->assertDatabaseCount('employees', 1);
    }

    /** @param array<string, mixed> $invalid */
    #[DataProvider('invalidData')]
    public function test_store_rejects_invalid_fields_without_writing(array $invalid, string $field): void
    {
        $this->travelTo(now()->setDate(2026, 10, 24)->startOfDay());
        $data = $this->payload();
        $this->actingAs(UserFactory::new()->create())
            ->post(route('empl.store'), [...$data, ...$invalid])
            ->assertInvalid([$field]);
        $this->assertDatabaseCount('employees', 0);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidData(): array
    {
        return [
            'missing nik' => [['nik' => ''], 'nik'],
            'short nik' => [['nik' => '123'], 'nik'],
            'long nik' => [['nik' => str_repeat('1', 17)], 'nik'],
            'numeric nik' => [['nik' => 1234567890123456], 'nik'],
            'non digit nik' => [['nik' => str_repeat('A', 16)], 'nik'],
            'short nip' => [['nip' => '123'], 'nip'],
            'long nip' => [['nip' => str_repeat('1', 19)], 'nip'],
            'non digit nip' => [['nip' => str_repeat('A', 18)], 'nip'],
            'numeric nip' => [['nip' => 123456789012345678], 'nip'],
            'missing name' => [['name' => ''], 'name'],
            'long name' => [['name' => str_repeat('A', 101)], 'name'],
            'non string name' => [['name' => ['A']], 'name'],
            'long front title' => [['front_title' => str_repeat('A', 51)], 'front_title'],
            'long back title' => [['back_title' => str_repeat('A', 51)], 'back_title'],
            'long rank' => [['rank' => str_repeat('A', 51)], 'rank'],
            'long grade' => [['grade' => str_repeat('A', 51)], 'grade'],
            'non string front title' => [['front_title' => 123], 'front_title'],
            'non string back title' => [['back_title' => []], 'back_title'],
            'non string rank' => [['rank' => 1], 'rank'],
            'non string grade' => [['grade' => []], 'grade'],
            'missing job' => [['job_title_id' => null], 'job_title_id'],
            'unknown job' => [['job_title_id' => '00000000-0000-4000-8000-000000000000'], 'job_title_id'],
            'numeric job' => [['job_title_id' => 1], 'job_title_id'],
            'malformed job' => [['job_title_id' => 'not-a-uuid'], 'job_title_id'],
            'missing date' => [['join_date' => null], 'join_date'],
            'invalid date' => [['join_date' => '2026-02-30'], 'join_date'],
            'wrong date format' => [['join_date' => '24-10-2026'], 'join_date'],
            'future date' => [['join_date' => '2026-10-25'], 'join_date'],
            'invalid email' => [['email' => 'invalid'], 'email'],
            'long email' => [['email' => str_repeat('a', 95) . '@test.com'], 'email'],
            'non string email' => [['email' => []], 'email'],
            'missing contract' => [['contract_status' => null], 'contract_status'],
            'invalid contract' => [['contract_status' => 99], 'contract_status'],
            'non scalar contract' => [['contract_status' => [1]], 'contract_status'],
            'missing status' => [['status' => null], 'status'],
            'invalid status' => [['status' => 99], 'status'],
            'non scalar status' => [['status' => [1]], 'status'],
        ];
    }

    #[DataProvider('uniqueFields')]
    public function test_store_rejects_duplicate_identities_and_email(string $field): void
    {
        $other = Employee::factory()->create();
        $data = $this->payload();
        $this->actingAs(UserFactory::new()->create())
            ->post(route('empl.store'), [...$data, $field => $other->$field])
            ->assertInvalid([$field]);
        $this->assertDatabaseCount('employees', 1);
    }

    /** @return array<string, array{string}> */
    public static function uniqueFields(): array
    {
        return ['nik' => ['nik'], 'nip' => ['nip'], 'email' => ['email']];
    }

    public function test_required_messages_use_indonesian_labels(): void
    {
        $this->actingAs(UserFactory::new()->create())
            ->post(route('empl.store'), [])
            ->assertInvalid([
                'nik' => 'NIK wajib diisi.',
                'name' => 'Nama wajib diisi.',
                'job_title_id' => 'Jabatan wajib diisi.',
                'join_date' => 'Tanggal Bergabung wajib diisi.',
                'contract_status' => 'Status Kontrak wajib diisi.',
                'status' => 'Status Pegawai wajib diisi.',
            ]);
    }

    public function test_guests_cannot_create_employees(): void
    {
        $this->get(route('empl.create'))->assertRedirectToRoute('login.index');
        $this->post(route('empl.store'), $this->payload())->assertRedirectToRoute('login.index');
        $this->assertDatabaseCount('employees', 0);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'nik' => '0000000000000001',
            'nip' => '000000000000000001',
            'name' => 'Siti Aminah',
            'front_title' => 'dr.',
            'back_title' => 'M.Kes.',
            'job_title_id' => JobTitle::factory()->create()->uuid,
            'rank' => 'Penata',
            'grade' => 'III/c',
            'join_date' => today()->toDateString(),
            'email' => 'siti@example.test',
            'contract_status' => 1,
            'status' => 1,
        ];
    }
}
