<?php

namespace Tests\Feature;

use App\Enums\AsnStatusEnum;
use App\Enums\LetterTemplateVersionStatusEnum;
use App\Models\MasterData\Employee;
use App\Models\MasterData\LetterTemplateSignerCandidate;
use App\Models\MasterData\LetterTemplateSignerSlot;
use App\Models\MasterData\LetterTemplateVariable;
use App\Models\MasterData\LetterTemplateVariableColumn;
use App\Models\MasterData\LetterTemplateVersion;
use App\Models\MasterData\ReferenceGroup;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class LetterTemplateConfigurationItemTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_variable_save_changes_only_target_and_invalidates_evidence_despite_unrelated_inactive_candidate(): void
    {
        $version = LetterTemplateVersion::factory()->create(['source_path' => 'sources/original.docx', 'validated_at' => now(), 'tested_at' => now(), 'visually_confirmed_at' => now()]);
        $first = LetterTemplateVariable::factory()->for($version, 'version')->create();
        $other = LetterTemplateVariable::factory()->for($version, 'version')->create(['sort_order' => 1]);
        $slot = LetterTemplateSignerSlot::factory()->for($version, 'version')->create();
        $employee = Employee::factory()->create(['status' => AsnStatusEnum::PENSIUN]);
        $candidate = LetterTemplateSignerCandidate::factory()->for($slot, 'slot')->create(['employee_id' => $employee->id]);
        $unchanged = $other->fresh()->getAttributes();
        $this->actingAs(UserFactory::new()->create())->put($this->url($version, 'variables.update', $first->uuid), ['item' => $this->variable(['id' => $first->uuid, 'name' => $first->name, 'label' => 'Tujuan baru'])])->assertSessionHasNoErrors()->assertRedirectToRoute('lt.edit', $version->template->uuid);
        $this->assertSame('Tujuan baru', $first->fresh()->label);
        $this->assertSame($unchanged, $other->fresh()->getAttributes());
        $this->assertModelExists($candidate);
        $this->assertModelExists($slot);
        $this->assertSame('sources/original.docx', $version->fresh()->source_path);
        $this->assertNull($version->fresh()->validated_at);
        $this->assertNull($version->fresh()->tested_at);
        $this->assertNull($version->fresh()->visually_confirmed_at);
        $this->assertDatabaseCount('letter_template_audits', 1);
    }

    public function test_variable_create_move_and_delete_persist_order_without_recreating_siblings(): void
    {
        $version = LetterTemplateVersion::factory()->create();
        $this->actingAs(UserFactory::new()->create());
        foreach (['pertama', 'kedua'] as $name) {
            $this->post($this->url($version, 'variables.store'), ['item' => $this->variable(['name' => $name])])->assertSessionHasNoErrors();
        }
        [$first, $second] = $version->fresh()->variables->all();
        $this->patch($this->url($version, 'variables.move', $second->uuid), ['direction' => 'up'])->assertSessionHasNoErrors();
        $this->assertSame([$second->id, $first->id], $version->fresh()->variables->modelKeys());
        $this->patch($this->url($version, 'variables.move', $second->uuid), ['direction' => 'up'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('letter_template_audits', 3);
        $this->delete($this->url($version, 'variables.destroy', $second->uuid))->assertSessionHasNoErrors();
        $this->assertModelMissing($second);
        $this->assertSame([$first->id], $version->fresh()->variables->modelKeys());
        $this->assertSame(0, $first->fresh()->sort_order);
    }

    public function test_column_mapping_preserves_public_uuid_and_rejects_foreign_columns(): void
    {
        $version = LetterTemplateVersion::factory()->create();
        $variable = LetterTemplateVariable::factory()->for($version, 'version')->create(['type' => 4, 'option_source' => 2, 'is_multiple' => true]);
        $column = LetterTemplateVariableColumn::factory()->for($variable, 'variable')->create(['placeholder' => 'nama_pegawai']);
        $payload = ['item' => $this->variable(['id' => $variable->uuid, 'name' => $variable->name, 'type' => 4, 'option_source' => 2, 'is_multiple' => true, 'columns' => [['id' => $column->uuid, 'source_attribute' => 'raw_name', 'placeholder' => 'pegawai_nama']]])];
        $this->actingAs(UserFactory::new()->create())->put($this->url($version, 'variables.update', $variable->uuid), $payload)->assertSessionHasNoErrors();
        $this->assertSame($column->uuid, $variable->fresh()->columns->sole()->uuid);
        $this->assertSame('raw_name', $variable->fresh()->columns->sole()->source_attribute);
        $foreign = LetterTemplateVariableColumn::factory()->create();
        $payload['item']['columns'][0]['id'] = $foreign->uuid;
        $this->put($this->url($version, 'variables.update', $variable->uuid), $payload)->assertInvalid('item.columns.0.id');
        $this->assertModelExists($foreign);
    }

    public function test_global_namespace_checks_employee_suffixes_and_slot_renumbering_atomically(): void
    {
        $version = LetterTemplateVersion::factory()->create(['validated_at' => now()]);
        LetterTemplateVariable::factory()->for($version, 'version')->create(['name' => 'orang_nama']);
        $this->actingAs(UserFactory::new()->create())->post($this->url($version, 'variables.store'), ['item' => $this->variable(['name' => 'orang', 'type' => 4, 'option_source' => 2])])->assertInvalid('item.name');
        $slot = LetterTemplateSignerSlot::factory()->for($version, 'version')->create();
        $employee = Employee::factory()->create(['status' => AsnStatusEnum::AKTIF]);
        LetterTemplateSignerCandidate::factory()->for($slot, 'slot')->create(['employee_id' => $employee->id]);
        LetterTemplateVariable::factory()->for($version, 'version')->create(['name' => 'penandatangan_1_nama']);
        $this->post($this->url($version, 'slots.store'), ['item' => ['candidates' => [$employee->uuid]]])->assertInvalid('configuration');
        $this->assertDatabaseCount('letter_template_signer_slots', 1);
        $this->assertNotNull($version->fresh()->validated_at);
    }

    public function test_slot_saves_only_its_candidates_and_deletion_renumbers_existing_slots(): void
    {
        $version = LetterTemplateVersion::factory()->create();
        $this->actingAs(UserFactory::new()->create());
        $firstEmployee = Employee::factory()->create(['status' => AsnStatusEnum::AKTIF]);
        $secondEmployee = Employee::factory()->create(['status' => AsnStatusEnum::AKTIF]);
        foreach ([$firstEmployee, $secondEmployee] as $employee) {
            $this->post($this->url($version, 'slots.store'), ['item' => ['candidates' => [$employee->uuid]]])->assertSessionHasNoErrors();
        }
        [$first, $second] = $version->fresh()->signerSlots->all();
        $firstEmployee->update(['status' => AsnStatusEnum::PENSIUN]);
        $this->put($this->url($version, 'slots.update', $first->uuid), ['item' => ['candidates' => [$firstEmployee->uuid]]])->assertInvalid('item.candidates.0');
        $this->put($this->url($version, 'slots.update', $second->uuid), ['item' => ['candidates' => [$secondEmployee->uuid, $secondEmployee->uuid]]])->assertInvalid('item.candidates');
        $this->delete($this->url($version, 'slots.destroy', $first->uuid))->assertSessionHasNoErrors();
        $this->assertSame([$second->id], $version->fresh()->signerSlots->modelKeys());
        $this->assertSame(1, $second->fresh()->sort_order);
    }

    #[DataProvider('attributeOwners')]
    public function test_attribute_updates_are_isolated_and_default_values_remove_sparse_settings(string $kind): void
    {
        $version = LetterTemplateVersion::factory()->create();
        $owner = $kind === 'variables' ? LetterTemplateVariable::factory()->for($version, 'version')->create(['type' => 4, 'option_source' => 2]) : LetterTemplateSignerSlot::factory()->for($version, 'version')->create();
        if ($kind === 'slots') {
            $employee = Employee::factory()->create(['status' => AsnStatusEnum::PENSIUN]);
            LetterTemplateSignerCandidate::factory()->for($owner, 'slot')->create(['employee_id' => $employee->id]);
        }
        $other = $owner->employeeAttributes()->create(['source_attribute' => 'nip', 'default_text' => 'NIP belum tersedia']);
        $this->actingAs(UserFactory::new()->create())->patch($this->url($version, $kind . '.attribute', $owner->uuid, 'join_date'), ['default_text' => 'Tanggal belum tersedia', 'date_format' => 'd F Y'])->assertSessionHasNoErrors();
        $this->assertSame('d F Y', $owner->employeeAttributes()->where('source_attribute', 'join_date')->sole()->date_format);
        $this->assertModelExists($other);
        $this->patch($this->url($version, $kind . '.attribute', $owner->uuid, 'join_date'), ['default_text' => null, 'date_format' => 'd-m-Y'])->assertSessionHasNoErrors();
        $this->assertSame([$other->id], $owner->employeeAttributes()->get()->modelKeys());
        $this->patch($this->url($version, $kind . '.attribute', $owner->uuid, 'nip'), ['default_text' => null, 'date_format' => 'Y'])->assertInvalid('date_format');
        $this->assertSame('NIP belum tersedia', $other->fresh()->default_text);
    }

    #[DataProvider('attributeOwners')]
    public function test_parent_edits_preserve_latest_attribute_values_and_identity(string $kind): void
    {
        $version = LetterTemplateVersion::factory()->create();
        $owner = $kind === 'variables' ? LetterTemplateVariable::factory()->for($version, 'version')->create(['type' => 4, 'option_source' => 2]) : LetterTemplateSignerSlot::factory()->for($version, 'version')->create();
        $setting = $owner->employeeAttributes()->create(['source_attribute' => 'nip', 'default_text' => 'Nilai terbaru']);
        if ($kind === 'variables') {
            $item = $this->variable(['id' => $owner->uuid, 'name' => $owner->name, 'type' => 4, 'option_source' => 2, 'label' => 'Label baru']);
        } else {
            $employee = Employee::factory()->create(['status' => AsnStatusEnum::AKTIF]);
            LetterTemplateSignerCandidate::factory()->for($owner, 'slot')->create(['employee_id' => $employee->id]);
            $item = ['id' => $owner->uuid, 'candidates' => [$employee->uuid]];
        }
        $item['employee_attributes'] = [['source_attribute' => 'nip', 'default_text' => 'Nilai lama', 'date_format' => null]];
        $this->actingAs(UserFactory::new()->create())->put($this->url($version, $kind . '.update', $owner->uuid), ['item' => $item])->assertSessionHasNoErrors();
        $this->assertModelExists($setting);
        $this->assertSame('Nilai terbaru', $setting->fresh()->default_text);
    }

    public static function attributeOwners(): array
    {
        return [['variables'], ['slots']];
    }

    public function test_attribute_owner_and_catalog_are_validated_and_unknown_input_cannot_change_placeholder(): void
    {
        $version = LetterTemplateVersion::factory()->create();
        $variable = LetterTemplateVariable::factory()->for($version, 'version')->create();
        $this->actingAs(UserFactory::new()->create())->patch($this->url($version, 'variables.attribute', $variable->uuid, 'nip'), ['default_text' => null, 'date_format' => null])->assertInvalid('configuration');
        $this->patch($this->url($version, 'variables.attribute', $variable->uuid, 'uuid'), ['default_text' => null, 'date_format' => null])->assertNotFound();
        $variable->update(['type' => 4, 'option_source' => 2]);
        $this->patch($this->url($version, 'variables.attribute', $variable->uuid, 'nip'), ['default_text' => '-', 'date_format' => null, 'name' => 'untrusted'])->assertSessionHasNoErrors();
        $this->assertSame($variable->name, $variable->fresh()->name);
    }

    #[DataProvider('uneditableVersions')]
    public function test_every_item_mutation_rejects_uneditable_versions(LetterTemplateVersionStatusEnum $status, bool $used): void
    {
        $version = LetterTemplateVersion::factory()->create(['status' => $status, 'first_used_at' => $used ? now() : null]);
        $variable = LetterTemplateVariable::factory()->for($version, 'version')->create();
        $this->actingAs(UserFactory::new()->create());
        $this->post($this->url($version, 'variables.store'), ['item' => $this->variable()])->assertInvalid('configuration');
        $this->put($this->url($version, 'variables.update', $variable->uuid), ['item' => $this->variable()])->assertInvalid('configuration');
        $this->delete($this->url($version, 'variables.destroy', $variable->uuid))->assertInvalid('configuration');
        $this->patch($this->url($version, 'variables.move', $variable->uuid), ['direction' => 'down'])->assertInvalid('configuration');
        $this->assertModelExists($variable);
        $this->assertDatabaseCount('letter_template_audits', 0);
    }

    public static function uneditableVersions(): array
    {
        return [[LetterTemplateVersionStatusEnum::PUBLISHED, false], [LetterTemplateVersionStatusEnum::ARCHIVED, false], [LetterTemplateVersionStatusEnum::DRAFT, true]];
    }

    public function test_child_and_version_scopes_and_authentication_are_enforced(): void
    {
        $version = LetterTemplateVersion::factory()->create();
        $foreign = LetterTemplateVariable::factory()->create();
        $this->post($this->url($version, 'variables.store'), ['item' => $this->variable()])->assertRedirectToRoute('login.index');
        $this->actingAs(UserFactory::new()->create())->put($this->url($version, 'variables.update', $foreign->uuid), ['item' => $this->variable()])->assertNotFound();
        $this->post(route('lt.configuration.variables.store', [$version->template->uuid, $foreign->version->uuid]), ['item' => $this->variable()])->assertNotFound();
        $this->assertModelExists($foreign);
        $this->assertDatabaseCount('letter_template_audits', 0);
    }

    public function test_failed_save_rolls_back_target_children_and_evidence(): void
    {
        $version = LetterTemplateVersion::factory()->create(['validated_at' => now()]);
        $variable = LetterTemplateVariable::factory()->for($version, 'version')->create(['type' => 4, 'option_source' => 2, 'is_multiple' => true]);
        $column = LetterTemplateVariableColumn::factory()->for($variable, 'variable')->create();
        Exceptions::fake();
        LetterTemplateVariableColumn::creating(function (): void { throw new RuntimeException('Simulasi kegagalan kolom.'); });
        try {
            $this->actingAs(UserFactory::new()->create())->put($this->url($version, 'variables.update', $variable->uuid), ['item' => $this->variable(['type' => 4, 'option_source' => 2, 'is_multiple' => true, 'columns' => [['source_attribute' => 'nip', 'placeholder' => 'nip_baru']]])])->assertInternalServerError();
        } finally {
            LetterTemplateVariableColumn::getEventDispatcher()->forget('eloquent.creating: ' . LetterTemplateVariableColumn::class);
        }
        Exceptions::assertReported(RuntimeException::class);
        $this->assertModelExists($column);
        $this->assertNotNull($version->fresh()->validated_at);
        $this->assertDatabaseCount('letter_template_audits', 0);
    }

    public function test_source_changes_clear_incompatible_parent_fallback_and_employee_settings(): void
    {
        $version = LetterTemplateVersion::factory()->create();
        $variable = LetterTemplateVariable::factory()->for($version, 'version')->create(['default_text' => 'Fallback lama']);
        $item = $this->variable(['name' => $variable->name, 'type' => 4, 'option_source' => 2]);
        unset($item['default_text']);
        $this->actingAs(UserFactory::new()->create())->put($this->url($version, 'variables.update', $variable->uuid), ['item' => $item])->assertSessionHasNoErrors();
        $this->assertNull($variable->fresh()->default_text);
        $setting = $variable->employeeAttributes()->create(['source_attribute' => 'nip', 'default_text' => '-']);
        $this->put($this->url($version, 'variables.update', $variable->uuid), ['item' => $this->variable(['name' => $variable->name])])->assertSessionHasNoErrors();
        $this->assertModelMissing($setting);
    }

    public function test_reference_group_and_variable_shape_are_validated(): void
    {
        $version = LetterTemplateVersion::factory()->create();
        $group = ReferenceGroup::factory()->create();
        $this->actingAs(UserFactory::new()->create())->post($this->url($version, 'variables.store'), ['item' => $this->variable(['type' => 4, 'option_source' => 1, 'reference_group' => $group->uuid])])->assertSessionHasNoErrors();
        $this->assertSame($group->id, $version->fresh()->variables->sole()->reference_group_id);
        $this->post($this->url($version, 'variables.store'), ['item' => $this->variable(['name' => 'lain', 'is_multiple' => true])])->assertInvalid('item.type');
        $this->assertDatabaseCount('letter_template_variables', 1);
    }

    private function url(LetterTemplateVersion $version, string $operation, ?string $item = null, ?string $attribute = null): string
    {
        return route('lt.configuration.' . $operation, array_filter(['letterTemplate' => $version->template->uuid, 'version' => $version->uuid, 'item' => $item, 'attribute' => $attribute]));
    }

    private function variable(array $changes = []): array
    {
        return [...['name' => 'tujuan', 'label' => 'Tujuan', 'type' => 1, 'is_required' => false, 'option_source' => null, 'reference_group' => null, 'is_multiple' => false, 'default_text' => null, 'employee_attributes' => [], 'columns' => []], ...$changes];
    }
}
