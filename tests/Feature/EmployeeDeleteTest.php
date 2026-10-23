<?php

namespace Tests\Feature;

use App\Models\MasterData\Employee;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmployeeDeleteTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_delete_removes_only_selected_employee_and_keeps_job_title(): void
    {
        $employee = Employee::factory()->create();
        $other = Employee::factory()->create();
        $this->actingAs(UserFactory::new()->create())->delete(route('empl.destroy', $employee->uuid))->assertRedirectToRoute('empl.index');
        $this->assertDatabaseMissing('employees', ['id' => $employee->id]);
        $this->assertDatabaseHas('employees', ['id' => $other->id]);
        $this->assertDatabaseHas('job_titles', ['id' => $employee->job_title_id]);
        $this->assertDatabaseCount('employees', 1);
    }

    public function test_delete_rejects_guest_unknown_uuid_and_numeric_identifier(): void
    {
        $employee = Employee::factory()->create();
        $this->delete(route('empl.destroy', $employee->uuid))->assertRedirectToRoute('login.index');
        $this->actingAs(UserFactory::new()->create());
        $this->delete(route('empl.destroy', $employee->id))->assertNotFound();
        $this->delete(route('empl.destroy', (string) Str::uuid()))->assertNotFound();
        $this->assertDatabaseHas('employees', ['id' => $employee->id]);
    }
}
