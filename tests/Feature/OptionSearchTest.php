<?php

namespace Tests\Feature;

use App\Enums\AsnStatusEnum;
use App\Enums\JobTitleGroupEnum;
use App\Enums\LetterTemplateOptionSourceEnum;
use App\Enums\LetterTemplateVariableTypeEnum;
use App\Models\MasterData\Employee;
use App\Models\MasterData\JobTitle;
use App\Models\MasterData\LetterTemplateSignerCandidate;
use App\Models\MasterData\LetterTemplateSignerSlot;
use App\Models\MasterData\LetterTemplateVariable;
use App\Models\MasterData\ReferenceGroup;
use App\Models\MasterData\ReferenceItem;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class OptionSearchTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_options_require_authentication_and_an_allowed_source(): void
    {
        $this->getJson(route('options.index', ['source' => 'employees']))->assertUnauthorized();
        $this->actingAs(UserFactory::new()->create())->getJson(route('options.index', ['source' => 'users']))->assertUnprocessable()->assertJsonValidationErrors('source');
        $this->getJson(route('options.index', ['source' => 'variable-options']))->assertUnprocessable()->assertJsonValidationErrors('context');
    }

    public function test_results_are_limited_searchable_and_hydrate_selected_labels_separately(): void
    {
        Employee::factory()->count(13)->create(['status' => AsnStatusEnum::AKTIF]);
        $selected = Employee::factory()->create(['name' => 'Zzz Pegawai Terpilih', 'status' => AsnStatusEnum::AKTIF]);
        $inactive = Employee::factory()->create(['name' => 'Tidak Aktif', 'status' => AsnStatusEnum::PENSIUN]);
        $this->actingAs(UserFactory::new()->create())
            ->getJson(route('options.index', ['source' => 'employees', 'selected' => [$selected->uuid]]))
            ->assertOk()->assertJsonCount(10, 'items')->assertJsonPath('selected.0.value', $selected->uuid)->assertJsonMissing(['value' => $inactive->uuid]);
        $this->getJson(route('options.index', ['source' => 'employees', 'q' => '  ZZZ PEGAWAI  ']))
            ->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.value', $selected->uuid);
    }

    public function test_reopening_can_find_a_new_employee_without_reloading_the_editor(): void
    {
        $this->actingAs(UserFactory::new()->create())->getJson(route('options.index', ['source' => 'employees']))->assertJsonCount(0, 'items');
        $employee = Employee::factory()->create(['status' => AsnStatusEnum::AKTIF]);
        $this->getJson(route('options.index', ['source' => 'employees']))->assertOk()->assertJsonPath('items.0.value', $employee->uuid)->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_reference_search_treats_wildcards_literally(): void
    {
        $match = ReferenceGroup::factory()->create(['name' => 'Biaya 10%_khusus']);
        ReferenceGroup::factory()->create(['name' => 'Biaya 100 biasa']);
        $this->actingAs(UserFactory::new()->create())->getJson(route('options.index', ['source' => 'reference-groups', 'q' => '10%_']))
            ->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.value', $match->uuid);
    }

    public function test_job_options_keep_group_context_and_metadata_for_dependent_filters(): void
    {
        $job = JobTitle::factory()->create(['group' => JobTitleGroupEnum::DOKTER]);
        $foreign = JobTitle::factory()->create(['group' => JobTitleGroupEnum::PERAWAT]);
        $this->actingAs(UserFactory::new()->create())->getJson(route('options.index', ['source' => 'job-titles', 'groups' => [1], 'selected' => [$job->uuid, $foreign->uuid]]))
            ->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.value', $job->uuid)->assertJsonPath('items.0.group', 1)->assertJsonCount(1, 'selected')->assertJsonPath('selected.0.group', 1);
    }

    public function test_variable_options_are_confined_to_its_reference_group(): void
    {
        $group = ReferenceGroup::factory()->create();
        $variable = LetterTemplateVariable::factory()->create(['type' => LetterTemplateVariableTypeEnum::COMBOBOX, 'option_source' => LetterTemplateOptionSourceEnum::REFERENCE, 'reference_group_id' => $group->id]);
        $item = ReferenceItem::factory()->for($group, 'group')->create();
        $foreign = ReferenceItem::factory()->create();
        $this->actingAs(UserFactory::new()->create())->getJson(route('options.index', ['source' => 'variable-options', 'context' => $variable->uuid, 'selected' => [$foreign->uuid]]))
            ->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.value', $item->uuid)->assertJsonCount(0, 'selected');
    }

    public function test_signer_options_include_only_active_candidates_for_the_slot(): void
    {
        $slot = LetterTemplateSignerSlot::factory()->create();
        $employee = Employee::factory()->create(['status' => AsnStatusEnum::AKTIF]);
        LetterTemplateSignerCandidate::factory()->for($slot, 'slot')->for($employee, 'employee')->create();
        Employee::factory()->create(['status' => AsnStatusEnum::AKTIF]);
        $inactive = Employee::factory()->create(['status' => AsnStatusEnum::PENSIUN]);
        LetterTemplateSignerCandidate::factory()->for($slot, 'slot')->for($inactive, 'employee')->create();
        $this->actingAs(UserFactory::new()->create())->getJson(route('options.index', ['source' => 'signer-candidates', 'context' => $slot->uuid]))
            ->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.value', $employee->uuid);
    }
}
