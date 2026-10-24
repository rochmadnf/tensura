<?php

namespace Tests\Feature;

use App\Enums\AsnStatusEnum;
use App\Enums\ContractStatusEnum;
use App\Models\MasterData\Employee;
use App\Models\MasterData\JobTitle;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmployeeEditTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_edit_page_has_form_values_options_and_page_id(): void
    {
        $employee = Employee::factory()->create(['join_date' => '2020-02-29']);
        $this->actingAs(UserFactory::new()->create())
            ->withoutVite()
            ->get(route('empl.edit', $employee->uuid))
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('employee/edit')
                    ->where('page.id', 'a30c21b9-15ef-4dc0-97ef-524ead13be55')
                    ->where('resource.data.id', $employee->uuid)
                    ->where('resource.data.name.raw', $employee->name)
                    ->where('resource.data.job_title.id', $employee->jobTitle->uuid)
                    ->where('resource.data.join_date_input', '2020-02-29')
                    ->where('contractStatuses', ContractStatusEnum::options())
                    ->where('statuses', AsnStatusEnum::options()),
            );
    }

    public function test_update_changes_all_data_fields_and_ignores_system_fields(): void
    {
        $employee = Employee::factory()->create();
        $other = Employee::factory()->create();
        $before = $other->getAttributes();
        $data = $this->payload();
        $this->actingAs(UserFactory::new()->create())
            ->patch(route('empl.update', $employee->uuid), [
                ...$data,
                'id' => $other->id,
                'uuid' => $other->uuid,
                'created_at' => '2000-01-01',
            ])
            ->assertRedirectToRoute('empl.index')
            ->assertSessionHasNoErrors();

        $updated = $employee->fresh();
        $this->assertSame($employee->id, $updated->id);
        $this->assertSame($employee->uuid, $updated->uuid);
        $this->assertTrue($employee->created_at->eq($updated->created_at));
        $this->assertSame(
            collect($before)->sortKeys()->all(),
            collect($other->fresh()->getAttributes())
                ->sortKeys()
                ->all(),
        );
        $this->assertSame($data['job_title_id'], $updated->jobTitle->uuid);
        $this->assertSame($data['join_date'], $updated->join_date->toDateString());
        $this->assertDatabaseHas('employees', [
            ...collect($data)
                ->except(['job_title_id', 'join_date'])
                ->all(),
            'id' => $employee->id,
            'job_title_id' => $updated->job_title_id,
        ]);
    }

    public function test_update_accepts_own_unique_values_and_clears_optional_fields(): void
    {
        $employee = Employee::factory()->create();
        $data = [...$this->payload(), 'nik' => $employee->nik, 'nip' => $employee->nip, 'email' => $employee->email];
        $this->actingAs(UserFactory::new()->create())
            ->patch(route('empl.update', $employee->uuid), $data)
            ->assertSessionHasNoErrors()
            ->assertRedirectToRoute('empl.index');
        $this->patch(route('empl.update', $employee->uuid), [
            ...$data,
            'nip' => '',
            'email' => '',
            'rank' => '',
            'grade' => '',
            'front_title' => '',
            'back_title' => '',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'nip' => null,
            'email' => null,
            'rank' => null,
            'grade' => null,
            'front_title' => null,
            'back_title' => null,
        ]);
    }

    #[DataProvider('uniqueFields')]
    public function test_update_cannot_take_another_employees_unique_value(string $field): void
    {
        $employee = Employee::factory()->create();
        $other = Employee::factory()->create();
        $data = $this->payload();
        $this->actingAs(UserFactory::new()->create())
            ->patch(route('empl.update', $employee->uuid), [
                ...$data,
                $field => $other->$field,
                'id' => $other->id,
                'uuid' => $other->uuid,
            ])
            ->assertInvalid([$field]);
        $this->assertSame(
            collect($employee->getAttributes())->sortKeys()->all(),
            collect($employee->fresh()->getAttributes())
                ->sortKeys()
                ->all(),
        );
    }

    /** @return array<string, array{string}> */
    public static function uniqueFields(): array
    {
        return ['nik' => ['nik'], 'nip' => ['nip'], 'email' => ['email']];
    }

    /** @param array<string, mixed> $invalid */
    #[DataProvider('invalidUpdates')]
    public function test_update_rejects_invalid_data_without_changing_record(array $invalid, string $field): void
    {
        $this->travelTo(now()->setDate(2026, 10, 24)->startOfDay());
        $employee = Employee::factory()->create();
        $data = $this->payload();
        $this->actingAs(UserFactory::new()->create())
            ->patch(route('empl.update', $employee->uuid), [...$data, ...$invalid])
            ->assertInvalid([$field]);
        $this->assertSame(
            collect($employee->getAttributes())->sortKeys()->all(),
            collect($employee->fresh()->getAttributes())
                ->sortKeys()
                ->all(),
        );
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidUpdates(): array
    {
        return EmployeeStoreTest::invalidData();
    }

    public function test_update_requires_new_job_after_old_job_is_deleted(): void
    {
        $employee = Employee::factory()->create();
        $oldJob = $employee->jobTitle;
        $oldJob->delete();
        $data = $this->payload();
        $this->actingAs(UserFactory::new()->create())
            ->patch(route('empl.update', $employee->uuid), [...$data, 'job_title_id' => $oldJob->uuid])
            ->assertInvalid(['job_title_id']);
        $this->assertNull($employee->fresh()->job_title_id);
        $this->patch(route('empl.update', $employee->uuid), $data)->assertSessionHasNoErrors();
        $this->assertSame($data['job_title_id'], $employee->fresh()->jobTitle->uuid);
    }

    public function test_edit_and_update_reject_guests_unknown_uuid_and_numeric_ids(): void
    {
        $employee = Employee::factory()->create();
        $data = $this->payload();
        $this->get(route('empl.edit', $employee->uuid))->assertRedirectToRoute('login.index');
        $this->patch(route('empl.update', $employee->uuid), $data)->assertRedirectToRoute('login.index');
        $this->actingAs(UserFactory::new()->create())->withoutVite();
        foreach ([$employee->id, (string) Str::uuid()] as $identifier) {
            $this->get(route('empl.edit', $identifier))->assertNotFound();
            $this->patch(route('empl.update', $identifier), $data)->assertNotFound();
        }
        $this->assertSame(
            collect($employee->getAttributes())->sortKeys()->all(),
            collect($employee->fresh()->getAttributes())
                ->sortKeys()
                ->all(),
        );
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
            'contract_status' => 2,
            'status' => 2,
        ];
    }
}
