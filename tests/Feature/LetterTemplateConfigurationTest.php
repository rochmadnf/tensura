<?php

namespace Tests\Feature;

use App\Enums\AsnStatusEnum;
use App\Enums\LetterTemplateOptionSourceEnum;
use App\Enums\LetterTemplateVariableTypeEnum;
use App\Enums\LetterTemplateVersionStatusEnum;
use App\Models\MasterData\Employee;
use App\Models\MasterData\LetterTemplateSignerCandidate;
use App\Models\MasterData\LetterTemplateSignerSlot;
use App\Models\MasterData\LetterTemplateVariable;
use App\Models\MasterData\LetterTemplateVariableColumn;
use App\Models\MasterData\LetterTemplateVersion;
use App\Models\MasterData\ReferenceGroup;
use App\Services\LetterTemplate\SaveLetterTemplateConfiguration;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class LetterTemplateConfigurationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_saves_all_variable_types_and_order_with_active_signer_candidates(): void
    {
        $version = LetterTemplateVersion::factory()->create(['source_path' => 'sources/original.docx', 'source_sha256' => str_repeat('a', 64), 'validated_at' => now(), 'validation_result' => ['valid' => true], 'tested_at' => now(), 'test_path' => 'previews/old.docx', 'visually_confirmed_at' => now()]);
        $employee = Employee::factory()->create(['status' => AsnStatusEnum::AKTIF]);
        $payload = ['variables' => [self::variable(), self::variable(['name' => 'alamat', 'type' => 2]), self::variable(['name' => 'tanggal', 'type' => 3])], 'signer_slots' => [['candidates' => [$employee->uuid]]]];

        $this->actingAs(UserFactory::new()->create())->put($this->url($version), $payload)
            ->assertSessionHasNoErrors()->assertRedirectToRoute('lt.edit', $version->template->uuid);

        $stored = $version->fresh();
        $this->assertSame(['nama', 'alamat', 'tanggal'], $stored->variables->pluck('name')->all());
        $this->assertSame([LetterTemplateVariableTypeEnum::SHORT_TEXT, LetterTemplateVariableTypeEnum::LONG_TEXT, LetterTemplateVariableTypeEnum::DATE], $stored->variables->pluck('type')->all());
        $this->assertSame([0, 1, 2], $stored->variables->pluck('sort_order')->all());
        $this->assertSame($employee->id, $stored->signerSlots->sole()->candidates->sole()->employee_id);
        $this->assertSame('sources/original.docx', $stored->source_path);
        $this->assertSame(str_repeat('a', 64), $stored->source_sha256);
        foreach (['validated_at', 'validation_result', 'tested_at', 'test_path', 'visually_confirmed_at'] as $field) {
            $this->assertNull($stored->$field);
        }
        $this->assertDatabaseHas('letter_template_audits', ['version_id' => $version->id, 'operation' => 'configuration.updated']);
    }

    #[DataProvider('comboboxModes')]
    public function test_saves_combobox_sources_single_and_multiple(int $source, bool $multiple, array $attributes): void
    {
        $version = LetterTemplateVersion::factory()->create();
        $group = ReferenceGroup::factory()->create();
        $columns = array_map(fn(string $attribute): array => ['source_attribute' => $attribute, 'placeholder' => 'kolom_' . $attribute], $multiple ? $attributes : []);
        $variable = self::variable(['type' => 4, 'option_source' => $source, 'reference_group' => $source === 1 ? $group->uuid : null, 'is_multiple' => $multiple, 'columns' => $columns]);

        $this->actingAs(UserFactory::new()->create())->put($this->url($version), ['variables' => [$variable], 'signer_slots' => []])->assertSessionHasNoErrors();

        $stored = $version->fresh()->variables->sole();
        $this->assertSame(LetterTemplateOptionSourceEnum::from($source), $stored->option_source);
        $this->assertSame($multiple, $stored->is_multiple);
        $this->assertSame($source === 1 ? $group->id : null, $stored->reference_group_id);
        $this->assertSame($multiple ? $attributes : [], $stored->columns->pluck('source_attribute')->all());
    }

    public static function comboboxModes(): array
    {
        return [
            'single reference' => [1, false, []], 'single employee' => [2, false, []],
            'multiple reference' => [1, true, ['row_number', 'content']],
            'multiple employee' => [2, true, ['row_number', 'name', 'nip', 'job_title']],
        ];
    }

    public function test_replaces_draft_configuration_preserves_public_uuid_and_leaves_history_untouched(): void
    {
        $version = LetterTemplateVersion::factory()->create(['version_number' => 2]);
        $history = LetterTemplateVersion::factory()->for($version->template, 'template')->create(['version_number' => 1, 'status' => LetterTemplateVersionStatusEnum::PUBLISHED, 'source_path' => 'history.docx']);
        $historical = LetterTemplateVariable::factory()->for($history, 'version')->create();
        $variable = LetterTemplateVariable::factory()->for($version, 'version')->create(['name' => 'lama']);
        $removed = LetterTemplateVariable::factory()->for($version, 'version')->create(['name' => 'dihapus']);
        $this->actingAs(UserFactory::new()->create())->put($this->url($version), ['variables' => [self::variable(['id' => $variable->uuid, 'name' => 'baru'])], 'signer_slots' => []])->assertSessionHasNoErrors();

        $this->assertSame($variable->uuid, $version->fresh()->variables->sole()->uuid);
        $this->assertSame('baru', $version->fresh()->variables->sole()->name);
        $this->assertFalse(LetterTemplateVariable::where('uuid', $removed->uuid)->exists());
        $this->assertModelExists($historical);
        $this->assertSame('history.docx', $history->fresh()->source_path);
    }

    #[DataProvider('invalidPayloads')]
    public function test_rejects_invalid_configuration_without_changing_existing_state(array $payload, string $field): void
    {
        $version = LetterTemplateVersion::factory()->create(['validated_at' => now()]);
        $existing = LetterTemplateVariable::factory()->for($version, 'version')->create();

        $this->actingAs(UserFactory::new()->create())->put($this->url($version), $payload)->assertInvalid([$field]);

        $this->assertModelExists($existing);
        $this->assertNotNull($version->fresh()->validated_at);
        $this->assertDatabaseCount('letter_template_audits', 0);
    }

    public static function invalidPayloads(): array
    {
        $base = ['variables' => [self::variable()], 'signer_slots' => []];
        $cases = [];
        foreach ([
            'empty name' => ['name', '', 'name'], 'syntax' => ['name', 'Nama Tidak Valid', 'name'],
            'too long' => ['name', str_repeat('a', 101), 'name'], 'empty label' => ['label', '', 'label'],
            'long label' => ['label', str_repeat('A', 101), 'label'], 'invalid type' => ['type', 99, 'type'],
            'required type' => ['is_required', 'yes', 'is_required'], 'multiple text' => ['is_multiple', true, 'type'],
            'source on text' => ['option_source', 2, 'type'], 'invalid source' => ['option_source', 99, 'option_source'],
            'system key' => ['version_id', 'untrusted', ''],
        ] as $name => [$key, $value, $error]) {
            $payload = $base;
            $payload['variables'][0][$key] = $value;
            $cases[$name] = [$payload, 'variables.0' . ($error === '' ? '' : '.' . $error)];
        }
        $cases['missing arrays'] = [[], 'variables'];
        $cases['not list'] = [['variables' => ['x' => self::variable()], 'signer_slots' => []], 'variables'];
        $cases['duplicate variable'] = [['variables' => [self::variable(), self::variable()], 'signer_slots' => []], 'variables.1.name'];
        $cases['combo without source'] = [['variables' => [self::variable(['type' => 4])], 'signer_slots' => []], 'variables.0.option_source'];
        $cases['reference without group'] = [['variables' => [self::variable(['type' => 4, 'option_source' => 1])], 'signer_slots' => []], 'variables.0.reference_group'];
        $cases['multiple without columns'] = [['variables' => [self::variable(['type' => 4, 'option_source' => 2, 'is_multiple' => true])], 'signer_slots' => []], 'variables.0.columns'];
        $cases['single with columns'] = [['variables' => [self::variable(['type' => 4, 'option_source' => 2, 'columns' => [['source_attribute' => 'name', 'placeholder' => 'pegawai']]])], 'signer_slots' => []], 'variables.0.columns'];
        $cases['empty candidate slot'] = [['variables' => [], 'signer_slots' => [['candidates' => []]]], 'signer_slots.0.candidates'];
        $cases['candidate uuid'] = [['variables' => [], 'signer_slots' => [['candidates' => ['not-a-uuid']]]], 'signer_slots.0.candidates.0'];
        $cases['unknown slot key'] = [['variables' => [], 'signer_slots' => [['candidates' => ['00000000-0000-4000-8000-000000000000'], 'sort_order' => 5]]], 'signer_slots.0'];

        return $cases;
    }

    #[DataProvider('invalidColumnAttributes')]
    public function test_rejects_columns_outside_the_agreed_source_attributes(int $source, string $attribute): void
    {
        $version = LetterTemplateVersion::factory()->create();
        $variable = self::variable(['type' => 4, 'option_source' => $source, 'is_multiple' => true, 'reference_group' => $source === 1 ? ReferenceGroup::factory()->create()->uuid : null, 'columns' => [['source_attribute' => $attribute, 'placeholder' => 'kolom']]]);
        $this->actingAs(UserFactory::new()->create())->put($this->url($version), ['variables' => [$variable], 'signer_slots' => []])->assertInvalid(['variables.0.columns.0.source_attribute']);
        $this->assertDatabaseCount('letter_template_variables', 0);
    }

    public static function invalidColumnAttributes(): array
    {
        return [[1, 'name'], [2, 'content'], [2, 'id'], [2, 'uuid'], [2, 'created_at']];
    }

    #[DataProvider('placeholderCollisions')]
    public function test_placeholder_namespace_includes_variables_columns_and_signer_slots(string $collision): void
    {
        $version = LetterTemplateVersion::factory()->create();
        $employee = Employee::factory()->create(['status' => AsnStatusEnum::AKTIF]);
        $variable = self::variable(['name' => $collision === 'signer' ? 'penandatangan' : 'utama', 'type' => 4, 'option_source' => 2, 'is_multiple' => true, 'columns' => [['source_attribute' => 'name', 'placeholder' => $collision === 'variable' ? 'utama' : 'kolom']]]);
        $payload = ['variables' => [$variable], 'signer_slots' => []];
        $field = 'variables.0.columns.0.placeholder';
        if ($collision === 'signer') {
            $payload['signer_slots'] = [['candidates' => [$employee->uuid]]];
            $field = 'signer_slots.0.candidates';
        } elseif ($collision === 'column') {
            $payload['variables'][] = self::variable(['name' => 'kolom']);
            $field = 'variables.1.name';
        } elseif ($collision === 'two columns') {
            $payload['variables'][0]['columns'][] = ['source_attribute' => 'nip', 'placeholder' => 'kolom'];
            $field = 'variables.0.columns.1.placeholder';
        } elseif ($collision === 'numbered signer') {
            $payload['variables'][0]['columns'][0]['placeholder'] = 'penandatangan_2';
            $payload['signer_slots'] = [['candidates' => [$employee->uuid]], ['candidates' => [$employee->uuid]]];
            $field = 'signer_slots.1.candidates';
        }
        $this->actingAs(UserFactory::new()->create())->put($this->url($version), $payload)->assertInvalid([$field]);
        $this->assertDatabaseCount('letter_template_variables', 0);
    }

    public static function placeholderCollisions(): array
    {
        return [['variable'], ['column'], ['two columns'], ['signer'], ['numbered signer']];
    }

    #[DataProvider('inactiveStatuses')]
    public function test_rejects_inactive_candidates_even_when_payload_was_loaded_while_active(AsnStatusEnum $status): void
    {
        $version = LetterTemplateVersion::factory()->create();
        $employee = Employee::factory()->create(['status' => AsnStatusEnum::AKTIF]);
        $payload = ['variables' => [], 'signer_slots' => [['candidates' => [$employee->uuid]]]];
        $employee->update(['status' => $status]);

        $this->actingAs(UserFactory::new()->create())->put($this->url($version), $payload)
            ->assertInvalid(['signer_slots.0.candidates.0' => 'Kandidat penandatangan harus pegawai aktif yang masih tersedia.']);
        $this->assertDatabaseCount('letter_template_signer_slots', 0);
    }

    public static function inactiveStatuses(): array
    {
        return array_map(fn(AsnStatusEnum $status): array => [$status], array_values(array_filter(AsnStatusEnum::cases(), fn(AsnStatusEnum $status): bool => $status !== AsnStatusEnum::AKTIF)));
    }

    public function test_existing_inactive_candidate_is_retained_and_shown_with_name_and_slot(): void
    {
        $version = LetterTemplateVersion::factory()->create();
        $slot = LetterTemplateSignerSlot::factory()->for($version, 'version')->create();
        $employee = Employee::factory()->create(['name' => 'Budi', 'front_title' => 'dr.', 'back_title' => 'Sp.PD', 'status' => AsnStatusEnum::AKTIF]);
        $candidate = LetterTemplateSignerCandidate::factory()->for($slot, 'slot')->create(['employee_id' => $employee->id]);
        $employee->update(['status' => AsnStatusEnum::PENSIUN]);
        $active = Employee::factory()->create(['status' => AsnStatusEnum::AKTIF]);

        $this->actingAs(UserFactory::new()->create())->withoutVite()->get(route('lt.edit', $version->template->uuid))
            ->assertOk()->assertInertia(fn(Assert $page) => $page
            ->where('configuration.data.warnings', [['slot' => 1, 'employee' => $employee->uuid, 'name' => 'dr. Budi Sp.PD']])
            ->where('configuration.data.signer_slots.0.candidates', [$employee->uuid])
            ->where('configuration.data.signer_slots.0.placeholder', 'penandatangan')
            ->where('configurationOptions.employees.0.id', $active->uuid)->has('configurationOptions.employees', 1));
        $this->assertModelExists($candidate);
    }

    public function test_preserves_column_and_slot_uuids_when_reordering_and_exposes_numbered_placeholders(): void
    {
        $version = LetterTemplateVersion::factory()->create();
        $variable = LetterTemplateVariable::factory()->for($version, 'version')->create(['name' => 'pegawai', 'type' => LetterTemplateVariableTypeEnum::COMBOBOX, 'option_source' => LetterTemplateOptionSourceEnum::EMPLOYEE, 'is_multiple' => true]);
        $column = LetterTemplateVariableColumn::factory()->for($variable, 'variable')->create();
        $slot = LetterTemplateSignerSlot::factory()->for($version, 'version')->create();
        $employee = Employee::factory()->create(['status' => AsnStatusEnum::AKTIF]);
        $payload = [
            'variables' => [self::variable(['id' => $variable->uuid, 'name' => 'pegawai', 'type' => 4, 'option_source' => 2, 'is_multiple' => true, 'columns' => [
                ['id' => null, 'source_attribute' => 'nip', 'placeholder' => 'pegawai_nip'],
                ['id' => $column->uuid, 'source_attribute' => 'name', 'placeholder' => 'pegawai_nama'],
            ]])],
            'signer_slots' => [['id' => null, 'candidates' => [$employee->uuid]], ['id' => $slot->uuid, 'candidates' => [$employee->uuid]]],
            'status' => 2, 'source_path' => 'untrusted.docx', 'is_active' => false,
        ];
        $this->actingAs(UserFactory::new()->create())->withoutVite()->put($this->url($version), $payload)->assertSessionHasNoErrors();

        $this->assertSame($column->uuid, $version->fresh()->variables->sole()->columns->last()->uuid);
        $this->assertSame($slot->uuid, $version->fresh()->signerSlots->last()->uuid);
        $this->assertSame(LetterTemplateVersionStatusEnum::DRAFT, $version->fresh()->status);
        $this->assertNull($version->fresh()->source_path);
        $this->assertTrue($version->template->fresh()->is_active);
        $this->get(route('lt.edit', $version->template->uuid))->assertOk()->assertInertia(fn(Assert $page) => $page
            ->where('configuration.data.editable', true)->where('configuration.data.has_configuration', true)
            ->where('configuration.data.variables.0.columns.0.placeholder', 'pegawai_nip')
            ->where('configuration.data.signer_slots.0.placeholder', 'penandatangan_1')
            ->where('configuration.data.signer_slots.1.placeholder', 'penandatangan_2')
            ->missing('configuration.data.source_path')->missing('configuration.data.letter_template_id'));
    }

    public function test_column_cannot_move_to_another_variable_within_the_same_version(): void
    {
        $version = LetterTemplateVersion::factory()->create();
        $first = LetterTemplateVariable::factory()->for($version, 'version')->create();
        $second = LetterTemplateVariable::factory()->for($version, 'version')->create();
        $column = LetterTemplateVariableColumn::factory()->for($first, 'variable')->create();
        $payload = ['variables' => [self::variable(['id' => $second->uuid, 'type' => 4, 'option_source' => 2, 'is_multiple' => true, 'columns' => [['id' => $column->uuid, 'source_attribute' => 'name', 'placeholder' => 'pegawai_nama']]])], 'signer_slots' => []];

        $this->actingAs(UserFactory::new()->create())->put($this->url($version), $payload)->assertInvalid(['variables.0.columns.0.id']);

        $this->assertSame($first->id, $column->fresh()->variable_id);
        $this->assertModelExists($second);
    }

    public function test_reference_source_rejects_archived_groups_and_employee_source_rejects_a_reference_group(): void
    {
        $group = ReferenceGroup::factory()->create();
        $version = LetterTemplateVersion::factory()->create();
        $this->actingAs(UserFactory::new()->create())->put($this->url($version), ['variables' => [self::variable(['type' => 4, 'option_source' => 2, 'reference_group' => $group->uuid])], 'signer_slots' => []])
            ->assertInvalid(['variables.0.reference_group']);
        $this->delete(route('ref.destroy', $group->uuid))->assertSessionHasNoErrors();

        $this->put($this->url($version), ['variables' => [self::variable(['type' => 4, 'option_source' => 1, 'reference_group' => $group->uuid])], 'signer_slots' => []])->assertInvalid(['variables.0.reference_group']);
        $this->assertDatabaseCount('letter_template_variables', 0);
    }

    public function test_unknown_or_deleted_candidate_is_rejected_and_duplicates_are_rejected_per_slot(): void
    {
        $version = LetterTemplateVersion::factory()->create();
        $this->actingAs(UserFactory::new()->create())->put($this->url($version), ['variables' => [], 'signer_slots' => [['candidates' => [(string) Str::uuid()]]]])->assertInvalid(['signer_slots.0.candidates.0']);
        $employee = Employee::factory()->create(['status' => AsnStatusEnum::AKTIF]);
        $this->put($this->url($version), ['variables' => [], 'signer_slots' => [['candidates' => [$employee->uuid, $employee->uuid]]]])->assertInvalid(['signer_slots.0.candidates']);
        $this->assertDatabaseCount('letter_template_signer_slots', 0);
    }

    #[DataProvider('foreignChildren')]
    public function test_rejects_child_uuid_from_another_version_or_variable(string $child): void
    {
        $version = LetterTemplateVersion::factory()->create();
        $foreignVersion = LetterTemplateVersion::factory()->create();
        $column = LetterTemplateVariableColumn::factory()->create();
        $foreignVariable = LetterTemplateVariable::factory()->for($foreignVersion, 'version')->create();
        $foreignSlot = LetterTemplateSignerSlot::factory()->for($foreignVersion, 'version')->create();
        $employee = Employee::factory()->create(['status' => AsnStatusEnum::AKTIF]);
        $variable = self::variable();
        $payload = ['variables' => [$variable], 'signer_slots' => []];
        $field = 'variables.0.id';
        if ($child === 'variable') {
            $payload['variables'][0]['id'] = $foreignVariable->uuid;
        } elseif ($child === 'column') {
            $payload['variables'][0] = self::variable(['type' => 4, 'option_source' => 2, 'is_multiple' => true, 'columns' => [['id' => $column->uuid, 'source_attribute' => 'name', 'placeholder' => 'kolom']]]);
            $field = 'variables.0.columns.0.id';
        } else {
            $payload['signer_slots'] = [['id' => $foreignSlot->uuid, 'candidates' => [$employee->uuid]]];
            $field = 'signer_slots.0.id';
        }
        $this->actingAs(UserFactory::new()->create())->put($this->url($version), $payload)->assertInvalid([$field]);
        $this->assertModelExists($column);
        $this->assertModelExists($foreignVariable);
        $this->assertModelExists($foreignSlot);
        $this->assertCount(0, $version->fresh()->variables);
    }

    public static function foreignChildren(): array
    {
        return [['variable'], ['column'], ['slot']];
    }

    public function test_scoped_version_rejects_foreign_unknown_and_numeric_identifiers(): void
    {
        $version = LetterTemplateVersion::factory()->create();
        $other = LetterTemplateVersion::factory()->create();
        $this->actingAs(UserFactory::new()->create());
        foreach ([$other->uuid, (string) Str::uuid(), $version->id] as $identifier) {
            $this->put(route('lt.configuration.update', [$version->template->uuid, $identifier]), ['variables' => [], 'signer_slots' => []])->assertNotFound();
        }
        $this->expectException(ModelNotFoundException::class);
        app(SaveLetterTemplateConfiguration::class)->save($version->template, $other, ['variables' => [], 'signer_slots' => []], UserFactory::new()->create());
    }

    #[DataProvider('uneditableVersions')]
    public function test_cannot_change_published_archived_or_used_versions(LetterTemplateVersionStatusEnum $status, bool $used): void
    {
        $version = LetterTemplateVersion::factory()->create(['status' => $status, 'first_used_at' => $used ? now() : null]);
        $this->actingAs(UserFactory::new()->create())->withoutVite()->put($this->url($version), ['variables' => [], 'signer_slots' => []])->assertInvalid(['configuration']);
        $this->get(route('lt.edit', $version->template->uuid))->assertOk()->assertInertia(fn(Assert $page) => $page->where('configuration.data.editable', false));
        $this->assertDatabaseCount('letter_template_audits', 0);
    }

    public static function uneditableVersions(): array
    {
        return [[LetterTemplateVersionStatusEnum::PUBLISHED, false], [LetterTemplateVersionStatusEnum::ARCHIVED, false], [LetterTemplateVersionStatusEnum::DRAFT, true]];
    }

    public function test_guests_cannot_save_configuration(): void
    {
        $version = LetterTemplateVersion::factory()->create();
        $this->put($this->url($version), ['variables' => [], 'signer_slots' => []])->assertRedirectToRoute('login.index');
        $this->assertDatabaseCount('letter_template_audits', 0);
    }

    public function test_write_failure_rolls_back_configuration_replacement_and_evidence(): void
    {
        $version = LetterTemplateVersion::factory()->create(['validated_at' => now()]);
        $existing = LetterTemplateVariable::factory()->for($version, 'version')->create();
        Exceptions::fake();
        LetterTemplateVariable::creating(function (): void { throw new RuntimeException('Simulasi kegagalan konfigurasi.'); });
        try {
            $this->actingAs(UserFactory::new()->create())->put($this->url($version), ['variables' => [self::variable()], 'signer_slots' => []])->assertInternalServerError();
        } finally {
            LetterTemplateVariable::getEventDispatcher()->forget('eloquent.creating: ' . LetterTemplateVariable::class);
        }
        Exceptions::assertReported(RuntimeException::class);
        $this->assertModelExists($existing);
        $this->assertNotNull($version->fresh()->validated_at);
        $this->assertDatabaseCount('letter_template_audits', 0);
    }

    private function url(LetterTemplateVersion $version): string
    {
        return route('lt.configuration.update', [$version->template->uuid, $version->uuid]);
    }

    private static function variable(array $changes = []): array
    {
        return [...['id' => null, 'name' => 'nama', 'label' => 'Nama', 'type' => 1, 'is_required' => true, 'option_source' => null, 'reference_group' => null, 'is_multiple' => false, 'columns' => []], ...$changes];
    }
}
