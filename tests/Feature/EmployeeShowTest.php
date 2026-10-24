<?php

namespace Tests\Feature;

use App\Models\MasterData\Employee;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmployeeShowTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_detail_exposes_all_fields_with_uuid_relationship_and_complete_years(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 24)->startOfDay());
        $employee = Employee::factory()->create([
            'name' => 'Siti',
            'front_title' => 'dr.',
            'back_title' => 'M.Kes.',
            'join_date' => '2020-10-25',
            'gender' => 2,
        ]);
        $this->actingAs(UserFactory::new()->create())
            ->withoutVite()
            ->get(route('empl.show', $employee->uuid))
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('employee/show')
                    ->where('page.id', 'a30c21b9-15ef-4dc0-97ef-524ead13be55')
                    ->where('resource.data', [
                        'id' => $employee->uuid,
                        'nik' => $employee->nik,
                        'nip' => $employee->nip,
                        'name' => ['first' => 'dr.', 'raw' => 'Siti', 'full' => 'dr. Siti M.Kes.', 'last' => 'M.Kes.'],
                        'gender' => ['id' => 2, 'label' => 'Perempuan'],
                        'job_title' => [
                            'id' => $employee->jobTitle->uuid,
                            'name' => $employee->jobTitle->name,
                            'group' => [
                                'id' => $employee->jobTitle->group?->value,
                                'label' => $employee->jobTitle->group?->label(),
                            ],
                        ],
                        'str' => null,
                        'sip' => null,
                        'rank' => null,
                        'grade' => null,
                        'email' => $employee->email,
                        'join_date' => '25-10-2020',
                        'join_date_input' => '2020-10-25',
                        'tenure_years' => 5,
                        'contract_status' => ['id' => 1, 'label' => 'PNS'],
                        'status' => ['id' => 1, 'label' => 'Aktif'],
                    ]),
            );
    }

    public function test_detail_and_edit_handle_a_deleted_job_title(): void
    {
        $employee = Employee::factory()->create(['front_title' => null, 'back_title' => null]);
        $employee->jobTitle->delete();
        $this->assertNull($employee->fresh()->job_title_id);
        $this->actingAs(UserFactory::new()->create())->withoutVite();
        foreach (['empl.show', 'empl.edit'] as $route) {
            $this->get(route($route, $employee->uuid))->assertOk()->assertInertia(
                fn(Assert $page) => $page
                    ->where('resource.data.job_title', [
                        'id' => null,
                        'name' => null,
                        'group' => ['id' => null, 'label' => null],
                    ])
                    ->where('resource.data.name.full', $employee->name),
            );
        }
    }

    public function test_detail_exposes_registration_labels_and_both_date_formats(): void
    {
        $employee = Employee::factory()
            ->withStr()
            ->withSip()
            ->create([
                'gender' => 1,
                'str_status' => 2,
                'str_published_date' => '2024-02-29',
                'sip_published_date' => '2024-03-01',
                'sip_expired_date' => '2029-03-01',
            ]);
        $this->actingAs(UserFactory::new()->create())
            ->withoutVite()
            ->get(route('empl.show', $employee->uuid))
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->where('resource.data.gender', ['id' => 1, 'label' => 'Laki-laki'])
                    ->where('resource.data.str', [
                        'number' => $employee->str_number,
                        'status' => ['id' => 2, 'label' => 'Definitif'],
                        'published_date' => '29-02-2024',
                        'published_date_input' => '2024-02-29',
                    ])
                    ->where('resource.data.sip', [
                        'number' => $employee->sip_number,
                        'published_date' => '01-03-2024',
                        'published_date_input' => '2024-03-01',
                        'expired_date' => '01-03-2029',
                        'expired_date_input' => '2029-03-01',
                    ]),
            );
    }

    public function test_detail_rejects_guest_unknown_uuid_and_numeric_identifier(): void
    {
        $employee = Employee::factory()->create();
        $this->get(route('empl.show', $employee->uuid))->assertRedirectToRoute('login.index');
        $this->actingAs(UserFactory::new()->create())->withoutVite();
        $this->get(route('empl.show', (string) Str::uuid()))->assertNotFound();
        $this->get(route('empl.show', $employee->id))->assertNotFound();
    }
}
