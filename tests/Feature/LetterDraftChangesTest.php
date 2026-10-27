<?php

namespace Tests\Feature;

use App\Enums\LetterTemplateVersionStatusEnum;
use App\Models\Letter;
use App\Models\MasterData\Employee;
use App\Models\MasterData\LetterTemplate;
use App\Models\MasterData\LetterTemplateSignerSlot;
use App\Models\MasterData\LetterTemplateVariable;
use App\Models\MasterData\LetterTemplateVersion;
use Database\Factories\LetterNumberFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LetterDraftChangesTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_template_adjustment_retains_semantic_matches_and_frozen_master_values_across_new_item_uuids(): void
    {
        $source = $this->version();
        $source->update(['validation_result' => ['valid' => true, 'placeholders' => ['nama', 'pegawai', 'pegawai_nip']]]);
        $name = LetterTemplateVariable::factory()->for($source, 'version')->create(['name' => 'nama', 'is_required' => true]);
        $person = LetterTemplateVariable::factory()->for($source, 'version')->create(['name' => 'pegawai', 'type' => 4, 'option_source' => 2]);
        $employee = Employee::factory()->create(['name' => 'Nama Lama', 'front_title' => null, 'back_title' => null, 'nip' => '111111111111111111', 'status' => 1]);
        $this->actingAs(UserFactory::new()->create())->post(route('ltr.store'), $this->payload($source, [$name->uuid => 'Nilai literal', $person->uuid => $employee->uuid]))->assertSessionHasNoErrors();
        $letter = Letter::sole();
        $employee->update(['name' => 'Nama Baru', 'nip' => '222222222222222222']);
        $target = $this->version($source);
        $target->update(['validation_result' => $source->validation_result, 'letter_date_format' => 'd F Y']);
        $newName = LetterTemplateVariable::factory()->for($target, 'version')->create(['name' => 'nama', 'is_required' => true]);
        $newPerson = LetterTemplateVariable::factory()->for($target, 'version')->create(['name' => 'pegawai', 'type' => 4, 'option_source' => 2]);
        $this->get(route('ltr.show', $letter->uuid))->assertInertia(fn(Assert $page) => $page->where('resource.data.draft.current_template', false)->where('resource.data.draft.can_adjust', true)->where('resource.data.draft.version_number', 1));
        $plan = $this->preview($letter);
        $this->assertSame(1, $letter->draft->revision);
        $this->apply($letter, $plan)->assertSessionHasNoErrors();
        $draft = $letter->draft->fresh();
        $this->assertSame($target->id, $draft->template_version_id);
        $this->assertSame('Nilai literal', $draft->form_data['values'][$newName->uuid]);
        $this->assertSame($employee->uuid, $draft->form_data['values'][$newPerson->uuid]);
        $this->assertSame('Nama Lama', $draft->snapshot['values']['pegawai']);
        $this->assertSame('111111111111111111', $draft->snapshot['values']['pegawai_nip']);
        $this->assertSame('d F Y', $draft->snapshot['date_format']);
        $this->assertArrayNotHasKey($name->uuid, $draft->form_data['values']);
        $this->assertSame(2, $draft->revision);
        $this->assertDatabaseHas('letter_audits', ['letter_id' => $letter->id, 'event' => 'template.adjusted']);
    }

    public function test_changed_types_new_required_fields_and_removed_variables_produce_incomplete_draft_without_old_tokens(): void
    {
        $source = $this->version();
        $source->update(['validation_result' => ['valid' => true, 'placeholders' => ['nama', 'dihapus']]]);
        $name = LetterTemplateVariable::factory()->for($source, 'version')->create(['name' => 'nama', 'is_required' => true]);
        $removed = LetterTemplateVariable::factory()->for($source, 'version')->create(['name' => 'dihapus']);
        $this->actingAs(UserFactory::new()->create())->post(route('ltr.store'), $this->payload($source, [$name->uuid => 'Old text', $removed->uuid => 'Do not render']))->assertSessionHasNoErrors();
        $letter = Letter::sole();
        $target = $this->version($source);
        $target->update(['validation_result' => ['valid' => true, 'placeholders' => ['nama', 'baru']]]);
        $changed = LetterTemplateVariable::factory()->for($target, 'version')->create(['name' => 'nama', 'type' => 3, 'is_required' => true]);
        $added = LetterTemplateVariable::factory()->for($target, 'version')->create(['name' => 'baru', 'is_required' => true]);
        $plan = $this->preview($letter);
        $this->assertFalse($plan['complete']);
        $this->assertCount(4, $plan['changes']);
        $this->apply($letter, $plan)->assertSessionHasNoErrors();
        $draft = $letter->draft->fresh();
        $this->assertFalse($draft->snapshot['complete']);
        $this->assertSame([], $draft->snapshot['values']);
        $this->assertNull($draft->form_data['values'][$changed->uuid]);
        $this->assertArrayNotHasKey($removed->uuid, $draft->form_data['values']);
        $this->put(route('ltr.update', $letter->uuid), [...$this->payload($target), 'revision' => 1])->assertSessionHasErrors('revision');
        $this->put(route('ltr.update', $letter->uuid), [...$this->payload($target, [$changed->uuid => '2026-10-01', $added->uuid => 'New text']), 'revision' => 2])->assertSessionHasNoErrors();
        $this->assertTrue($letter->draft->fresh()->snapshot['complete']);
    }

    public function test_ambiguous_signer_mapping_is_cleared_and_a_unique_valid_slot_is_preserved(): void
    {
        $source = $this->version();
        $source->update(['validation_result' => ['valid' => true, 'placeholders' => ['penandatangan_1', 'penandatangan_2']]]);
        $a = Employee::factory()->create(['status' => 1]);
        $b = Employee::factory()->create(['status' => 1]);
        $one = $this->slot($source, 1, [$a, $b]);
        $two = $this->slot($source, 2, [$a, $b]);
        $this->actingAs(UserFactory::new()->create())->post(route('ltr.store'), $this->payload($source, [], [$one->uuid => $a->uuid, $two->uuid => $b->uuid]))->assertSessionHasNoErrors();
        $letter = Letter::sole();
        $target = $this->version($source);
        $target->update(['validation_result' => $source->validation_result]);
        $newOne = $this->slot($target, 1, [$a, $b]);
        $newTwo = $this->slot($target, 2, [$a, $b]);
        $this->apply($letter, $this->preview($letter))->assertSessionHasNoErrors();
        $this->assertSame([$newOne->uuid => null, $newTwo->uuid => null], $letter->draft->fresh()->form_data['signers']);
        $this->assertFalse($letter->draft->fresh()->snapshot['complete']);
        $this->assertSame([], $letter->draft->fresh()->snapshot['masters']['employees']);
        $a->update(['name' => 'Nama dipilih ulang']);
        $this->put(route('ltr.update', $letter->uuid), [...$this->payload($target, [], [$newOne->uuid => $a->uuid, $newTwo->uuid => $b->uuid]), 'revision' => 2])->assertSessionHasNoErrors();
        $this->assertSame('Nama dipilih ulang', $letter->draft->fresh()->snapshot['masters']['employees'][$a->uuid]['attributes']['name']);
        $next = $this->version($target);
        $next->update(['validation_result' => ['valid' => true, 'placeholders' => ['penandatangan']]]);
        $slot = $this->slot($next, 1, [$a]);
        $this->apply($letter, $this->preview($letter, 3))->assertSessionHasNoErrors();
        $this->assertSame($a->uuid, $letter->draft->fresh()->form_data['signers'][$slot->uuid]);
    }

    public function test_change_confirmation_is_scoped_to_letter_revision_current_template_and_examined_master_values(): void
    {
        $source = $this->version();
        $person = LetterTemplateVariable::factory()->for($source, 'version')->create(['name' => 'nama', 'type' => 4, 'option_source' => 2]);
        $employee = Employee::factory()->create(['name' => 'Original', 'status' => 1]);
        $this->actingAs(UserFactory::new()->create())->post(route('ltr.store'), $this->payload($source, [$person->uuid => $employee->uuid]))->assertSessionHasNoErrors();
        $first = Letter::sole();
        $this->post(route('ltr.store'), $this->payload($source, [$person->uuid => $employee->uuid]))->assertSessionHasNoErrors();
        $other = Letter::where('id', '!=', $first->id)->sole();
        $plan = $this->getJson(route('ltr.masters.preview', [$first->uuid, 'revision' => 1]))->assertOk()->json();
        $this->post(route('ltr.masters.apply', $first->uuid), ['revision' => 1, 'change_token' => $plan['change_token']])->assertSessionHasErrors('confirmed');
        $this->post(route('ltr.masters.apply', $other->uuid), ['revision' => 1, 'change_token' => $plan['change_token'], 'confirmed' => true])->assertSessionHasErrors('change_token');
        $employee->update(['name' => 'Changed after review']);
        $this->post(route('ltr.masters.apply', $first->uuid), ['revision' => 1, 'change_token' => $plan['change_token'], 'confirmed' => true])->assertSessionHasErrors('change_token');
        $this->assertSame(1, $first->draft->revision);
        $this->version($source);
        $plan = $this->preview($first);
        $this->version($source, 3);
        $this->apply($first, $plan)->assertSessionHasErrors('change_token');
        $this->assertSame(1, $first->draft->fresh()->revision);
    }

    public function test_template_options_exclude_inactive_and_unpublished_templates_and_refresh_rejects_inactive_choices(): void
    {
        $source = $this->version();
        $variable = LetterTemplateVariable::factory()->for($source, 'version')->create(['name' => 'nama', 'type' => 4, 'option_source' => 2]);
        $employee = Employee::factory()->create(['status' => 1]);
        $this->actingAs(UserFactory::new()->create())->post(route('ltr.store'), $this->payload($source, [$variable->uuid => $employee->uuid]))->assertSessionHasNoErrors();
        $letter = Letter::sole();
        $unpublished = LetterTemplateVersion::factory()->create();
        $this->getJson(route('options.index', ['source' => 'letter-templates']))->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.value', $source->template->uuid);
        $this->get(route('ltr.create', ['template' => $unpublished->template->uuid]))->assertNotFound();
        $employee->update(['status' => 2]);
        $this->getJson(route('ltr.masters.preview', [$letter->uuid, 'revision' => 1]))->assertUnprocessable()->assertJsonValidationErrors('values.' . $variable->uuid);
        $this->assertSame(1, $letter->draft->revision);
        $source->template->update(['is_active' => false]);
        $this->getJson(route('options.index', ['source' => 'letter-templates']))->assertJsonCount(0, 'items');
        $this->getJson(route('ltr.adjustment.preview', [$letter->uuid, 'revision' => 1]))->assertUnprocessable();
    }

    private function version(?LetterTemplateVersion $source = null, ?int $number = null): LetterTemplateVersion
    {
        $version = LetterTemplateVersion::factory()->create(['letter_template_id' => $source?->letter_template_id ?? LetterTemplate::factory(), 'letter_number_id' => $source?->letter_number_id ?? LetterNumberFactory::new(), 'version_number' => $number ?? ($source === null ? 1 : $source->version_number + 1), 'status' => LetterTemplateVersionStatusEnum::PUBLISHED, 'validation_result' => ['valid' => true, 'placeholders' => ['nama']]]);
        $version->template->update(['is_active' => true, 'active_version_id' => $version->id]);

        return $version;
    }

    private function payload(LetterTemplateVersion $version, array $values = [], array $signers = []): array
    {
        return ['template' => $version->template->uuid, 'template_version' => $version->uuid, 'subject' => 'Surat tugas', 'letter_date' => '2026-10-26', 'numbering_mode' => 1, 'number_year' => 2026, 'number_month' => 10, 'values' => $values, 'signers' => $signers];
    }

    private function preview(Letter $letter, int $revision = 1): array
    {
        return $this->getJson(route('ltr.adjustment.preview', [$letter->uuid, 'revision' => $revision]))->assertOk()->json();
    }

    private function apply(Letter $letter, array $preview): TestResponse
    {
        return $this->post(route('ltr.adjustment.apply', $letter->uuid), ['revision' => $preview['revision'], 'change_token' => $preview['change_token'], 'confirmed' => true]);
    }

    private function slot(LetterTemplateVersion $version, int $order, array $employees): LetterTemplateSignerSlot
    {
        $slot = LetterTemplateSignerSlot::factory()->for($version, 'version')->create(['sort_order' => $order]);
        foreach ($employees as $employee) {
            $slot->candidates()->create(['employee_id' => $employee->id]);
        }

        return $slot;
    }
}
