<?php

namespace Tests\Feature;

use App\Enums\AsnStatusEnum;
use App\Enums\ContractStatusEnum;
use App\Enums\JobTitleGroupEnum;
use App\Enums\StrStatusEnum;
use App\Models\MasterData\Employee;
use App\Models\MasterData\JobTitle;
use App\Models\MasterData\LetterTemplateSignerSlot;
use App\Models\MasterData\LetterTemplateVariable;
use App\Models\MasterData\LetterTemplateVersion;
use App\Services\LetterTemplate\EmployeeAttributeCatalog;
use App\Services\LetterTemplate\FormatEmployeeDate;
use App\Services\LetterTemplate\LetterTemplateConfigurationHash;
use App\Services\LetterTemplate\ResolveEmployeeAttributes;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class LetterTemplateEmployeeAttributesTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_resolves_all_business_attributes_and_aliases_from_the_same_employee(): void
    {
        $employee = Employee::factory()->make([
            'name' => 'Budi', 'front_title' => 'dr.', 'back_title' => 'Sp.PD', 'nik' => '1234567890123456', 'nip' => '123456789012345678',
            'gender' => Employee::LAKI_LAKI, 'email' => 'budi@example.test', 'rank' => 'Penata', 'grade' => 'III/c', 'join_date' => '2021-10-22',
            'contract_status' => ContractStatusEnum::PNS, 'status' => AsnStatusEnum::AKTIF, 'str_number' => '1111222233334444',
            'str_status' => StrStatusEnum::SEUMUR_HIDUP, 'str_published_date' => '2022-10-25', 'sip_number' => 'SIP/001',
            'sip_published_date' => '2023-10-25', 'sip_expired_date' => '2028-10-25',
        ]);
        $employee->setRelation('jobTitle', JobTitle::factory()->make(['name' => 'Dokter Spesialis', 'group' => JobTitleGroupEnum::DOKTER]));
        $resolver = app(ResolveEmployeeAttributes::class);
        $values = $resolver->resolve($employee, [['source_attribute' => 'join_date', 'date_format' => 'd F Y']], CarbonImmutable::parse('2026-10-25'));

        $this->assertSame([
            'name' => 'dr. Budi Sp.PD', 'raw_name' => 'Budi', 'front_title' => 'dr.', 'back_title' => 'Sp.PD',
            'nik' => '1234567890123456', 'nip' => '123456789012345678', 'gender' => 'Laki-laki', 'email' => 'budi@example.test',
            'job_title' => 'Dokter Spesialis', 'job_title_group' => 'Dokter', 'rank' => 'Penata', 'grade' => 'III/c',
            'join_date' => '22 Oktober 2021', 'contract_status' => 'PNS', 'status' => 'Aktif', 'tenure' => '5 tahun 3 hari',
            'tenure_years' => '5', 'tenure_months' => '0', 'tenure_days' => '3', 'str_number' => '1111222233334444',
            'str_status' => 'Seumur Hidup', 'str_published_date' => '25-10-2022', 'sip_number' => 'SIP/001',
            'sip_published_date' => '25-10-2023', 'sip_expired_date' => '25-10-2028',
        ], $values);
        $tokens = $resolver->tokens('penandatangan_2', $employee, [], CarbonImmutable::parse('2026-10-25'));
        $this->assertSame('dr. Budi Sp.PD', $tokens['penandatangan_2']);
        $this->assertSame('dr. Budi Sp.PD', $tokens['penandatangan_2_nama']);
        $this->assertSame('Penata', $tokens['penandatangan_2_pangkat']);
        $this->assertSame('III/c', $tokens['penandatangan_2_golongan']);
        $this->assertArrayNotHasKey('id', $values);
        $this->assertArrayNotHasKey('uuid', $values);
    }

    #[DataProvider('tenures')]
    public function test_tenure_uses_calendar_parts_and_omits_zero_text_parts(?string $start, string $end, string $expected): void
    {
        $employee = Employee::factory()->make(['join_date' => $start]);
        $values = app(ResolveEmployeeAttributes::class)->resolve($employee, [['source_attribute' => 'tenure', 'default_text' => '-']], CarbonImmutable::parse($end));
        $this->assertSame($expected, $values['tenure']);
    }

    public static function tenures(): array
    {
        return [
            'years and days' => ['2021-10-22', '2026-10-25', '5 tahun 3 hari'],
            'months and days' => ['2026-08-18', '2026-10-25', '2 bulan 7 hari'],
            'only days' => ['2026-10-13', '2026-10-25', '12 hari'],
            'whole year' => ['2025-10-25', '2026-10-25', '1 tahun'],
            'leap day' => ['2024-02-28', '2024-03-01', '2 hari'],
            'month boundary' => ['2026-01-31', '2026-03-03', '1 bulan'],
            'same day' => ['2026-10-25', '2026-10-25', '0 hari'],
            'future' => ['2027-10-25', '2026-10-25', '0 hari'],
            'missing' => [null, '2026-10-25', '-'],
        ];
    }

    public function test_missing_values_use_attribute_fallbacks_without_treating_zero_as_empty(): void
    {
        $employee = Employee::factory()->make(['nip' => null, 'join_date' => '2026-10-25']);
        $employee->setRelation('jobTitle', null);
        $values = app(ResolveEmployeeAttributes::class)->resolve($employee, [
            ['source_attribute' => 'nip', 'default_text' => '-'], ['source_attribute' => 'job_title', 'default_text' => 'Belum ditetapkan'],
            ['source_attribute' => 'tenure_years', 'default_text' => 'Kosong'],
        ], CarbonImmutable::parse('2026-10-25'));
        $this->assertSame('-', $values['nip']);
        $this->assertSame('Belum ditetapkan', $values['job_title']);
        $this->assertSame('', $values['job_title_group']);
        $this->assertSame('0', $values['tenure_years']);
        $tokens = app(ResolveEmployeeAttributes::class)->tokens('pegawai', null, [['source_attribute' => 'name', 'default_text' => 'Tidak dipilih']], CarbonImmutable::parse('2026-10-25'));
        $this->assertSame('Tidak dipilih', $tokens['pegawai']);
        $this->assertSame('Tidak dipilih', $tokens['pegawai_nama']);
    }

    public function test_preview_uses_php_patterns_and_app_locale_and_requires_authentication(): void
    {
        $this->getJson(route('lt.date-format'))->assertUnauthorized();
        $this->actingAs(UserFactory::new()->create())->getJson(route('lt.date-format', ['date_format' => 'l, d F Y']))
            ->assertOk()->assertExactJson(['example' => 'Minggu, 25 Oktober 2026', 'locale' => 'id']);
        $this->getJson(route('lt.date-format'))->assertOk()->assertJsonPath('example', '25-10-2026');
        $this->getJson(route('lt.date-format', ['date_format' => '\\T\\g\\l d/m/Y']))->assertOk()->assertJsonPath('example', 'Tgl 25/10/2026');
        $this->getJson(route('lt.date-format', ['date_format' => str_repeat('d', 101)]))->assertUnprocessable()->assertJsonValidationErrors(['date_format']);
        config(['app.locale' => 'en']);
        $this->getJson(route('lt.date-format', ['date_format' => 'd F Y']))->assertOk()->assertJsonPath('example', '25 October 2026');
    }

    public function test_formatter_preserves_literals_and_other_php_format_tokens(): void
    {
        $date = CarbonImmutable::parse('2026-10-25 12:34:56', 'Asia/Makassar');
        $this->assertSame('2026-10-25T12:34:56+08:00', app(FormatEmployeeDate::class)->format($date, 'c'));
        $this->assertSame('Tgl 25 Oktober 2026', app(FormatEmployeeDate::class)->format($date, '\\T\\g\\l d F Y'));
    }

    public function test_saves_attribute_settings_per_owner_and_preserves_them_for_legacy_payloads(): void
    {
        $version = LetterTemplateVersion::factory()->create();
        $employee = Employee::factory()->create(['status' => AsnStatusEnum::AKTIF]);
        $payload = ['variables' => [self::variable(['default_text' => '-']), self::variable([
            'name' => 'pegawai', 'type' => 4, 'option_source' => 2,
            'employee_attributes' => [['source_attribute' => 'nip', 'default_text' => 'NIP belum ada'], ['source_attribute' => 'join_date', 'date_format' => 'd F Y']],
        ])], 'signer_slots' => [['candidates' => [$employee->uuid], 'employee_attributes' => [['source_attribute' => 'nip', 'default_text' => 'Tidak tersedia']]]]];
        $this->actingAs(UserFactory::new()->create())->put($this->url($version), $payload)->assertSessionHasNoErrors();
        $stored = $version->fresh();
        $variable = $stored->variables->last();
        $slot = $stored->signerSlots->sole();
        $this->assertSame('-', $stored->variables->first()->default_text);
        $this->assertSame('NIP belum ada', $variable->employeeAttributes->firstWhere('source_attribute', 'nip')->default_text);
        $this->assertSame('Tidak tersedia', $slot->employeeAttributes->sole()->default_text);
        $this->assertSame('d F Y', $variable->employeeAttributes->firstWhere('source_attribute', 'join_date')->date_format);
        $hash = app(LetterTemplateConfigurationHash::class)->hash($stored);
        $legacy = ['variables' => [], 'signer_slots' => [['id' => $slot->uuid, 'candidates' => [$employee->uuid]]]];
        foreach ($stored->variables as $item) {
            $legacy['variables'][] = self::variable(['id' => $item->uuid, 'name' => $item->name, 'type' => $item->type->value, 'option_source' => $item->option_source?->value]);
        }
        $this->put($this->url($version), $legacy)->assertSessionHasNoErrors();
        $this->assertSame($hash, app(LetterTemplateConfigurationHash::class)->hash($version->fresh()));
        $this->withoutVite()->get(route('lt.edit', $version->template->uuid))->assertOk()->assertInertia(fn(Assert $page) => $page
            ->has('configurationOptions.employeeAttributes', 25)->has('configuration.data.variables.1.employee_attributes', 25)
            ->where('configuration.data.variables.1.employee_attributes.5.default_text', 'NIP belum ada')
            ->where('configuration.data.signer_slots.0.employee_attributes.5.default_text', 'Tidak tersedia'));
        $legacy['variables'][0]['default_text'] = null;
        $legacy['variables'][1]['employee_attributes'] = [];
        $legacy['signer_slots'][0]['employee_attributes'] = null;
        $this->put($this->url($version), $legacy)->assertSessionHasNoErrors();
        $this->assertNull($version->fresh()->variables->first()->default_text);
        $this->assertDatabaseCount('letter_template_variable_employee_attributes', 0);
        $this->assertDatabaseCount('letter_template_signer_employee_attributes', 0);
    }

    public function test_all_catalog_attributes_are_available_as_columns_and_date_format_survives_legacy_save(): void
    {
        $version = LetterTemplateVersion::factory()->create();
        $columns = array_map(fn(array $field): array => ['source_attribute' => $field['id'], 'placeholder' => 'baris_' . $field['suffix'], 'date_format' => $field['type'] === 'date' ? 'd F Y' : null], EmployeeAttributeCatalog::options());
        $variable = self::variable(['type' => 4, 'option_source' => 2, 'is_multiple' => true, 'columns' => $columns]);
        $this->actingAs(UserFactory::new()->create())->put($this->url($version), ['variables' => [$variable], 'signer_slots' => []])->assertSessionHasNoErrors();
        $stored = $version->fresh()->variables->sole();
        $this->assertCount(25, $stored->columns);
        $variable['id'] = $stored->uuid;
        $variable['columns'] = $stored->columns->map(fn($column): array => ['id' => $column->uuid, 'source_attribute' => $column->source_attribute, 'placeholder' => $column->placeholder])->all();
        $this->put($this->url($version), ['variables' => [$variable], 'signer_slots' => []])->assertSessionHasNoErrors();
        $this->assertSame('d F Y', $version->fresh()->variables->sole()->columns->firstWhere('source_attribute', 'join_date')->date_format);
    }

    #[DataProvider('invalidSettings')]
    public function test_rejects_unknown_duplicate_and_misplaced_settings(array $changes, string $field): void
    {
        $version = LetterTemplateVersion::factory()->create();
        $this->actingAs(UserFactory::new()->create())->put($this->url($version), ['variables' => [self::variable($changes)], 'signer_slots' => []])->assertInvalid([$field]);
        $this->assertDatabaseCount('letter_template_variables', 0);
        $this->assertDatabaseCount('letter_template_variable_employee_attributes', 0);
    }

    public static function invalidSettings(): array
    {
        return [
            'unknown' => [['type' => 4, 'option_source' => 2, 'employee_attributes' => [['source_attribute' => 'uuid']]], 'variables.0.employee_attributes.0.source_attribute'],
            'duplicates' => [['type' => 4, 'option_source' => 2, 'employee_attributes' => [['source_attribute' => 'nip'], ['source_attribute' => 'nip']]], 'variables.0.employee_attributes.1.source_attribute'],
            'non date' => [['type' => 4, 'option_source' => 2, 'employee_attributes' => [['source_attribute' => 'nip', 'date_format' => 'd-m-Y']]], 'variables.0.employee_attributes.0.date_format'],
            'text variable' => [['employee_attributes' => [['source_attribute' => 'nip']]], 'variables.0.employee_attributes'],
            'parent fallback' => [['type' => 4, 'option_source' => 2, 'default_text' => '-'], 'variables.0.default_text'],
            'multiple fallback' => [['type' => 4, 'option_source' => 2, 'is_multiple' => true, 'default_text' => '-', 'columns' => [['source_attribute' => 'name', 'placeholder' => 'baris_nama']]], 'variables.0.default_text'],
            'unknown child key' => [['type' => 4, 'option_source' => 2, 'employee_attributes' => [['source_attribute' => 'nip', 'slot_id' => 1]]], 'variables.0.employee_attributes.0'],
            'column fallback' => [['type' => 4, 'option_source' => 2, 'is_multiple' => true, 'columns' => [['source_attribute' => 'name', 'placeholder' => 'baris_nama', 'default_text' => '-']]], 'variables.0.columns.0'],
            'column non date format' => [['type' => 4, 'option_source' => 2, 'is_multiple' => true, 'columns' => [['source_attribute' => 'name', 'placeholder' => 'baris_nama', 'date_format' => 'd-m-Y']]], 'variables.0.columns.0.date_format'],
        ];
    }

    public function test_automatic_token_collisions_with_variables_columns_and_signer_attributes_are_rejected(): void
    {
        $version = LetterTemplateVersion::factory()->create();
        $this->actingAs(UserFactory::new()->create());
        $employeeVariable = self::variable(['name' => 'pegawai', 'type' => 4, 'option_source' => 2]);
        $this->put($this->url($version), ['variables' => [$employeeVariable, self::variable(['name' => 'pegawai_pangkat'])], 'signer_slots' => []])->assertInvalid(['variables.1.name']);
        $employee = Employee::factory()->create(['status' => AsnStatusEnum::AKTIF]);
        $this->put($this->url($version), ['variables' => [self::variable(['name' => 'penandatangan_golongan'])], 'signer_slots' => [['candidates' => [$employee->uuid]]]])->assertInvalid(['signer_slots.0.candidates']);
        $employeeVariable['name'] = str_repeat('a', 90);
        $this->put($this->url($version), ['variables' => [$employeeVariable], 'signer_slots' => []])->assertInvalid(['variables.0.name']);
        $this->assertDatabaseCount('letter_template_variables', 0);
    }

    public function test_setting_changes_invalidate_evidence_without_modifying_other_versions(): void
    {
        $version = LetterTemplateVersion::factory()->create(['source_path' => 'original.docx', 'validated_at' => now(), 'tested_at' => now()]);
        $history = LetterTemplateVersion::factory()->create();
        $old = LetterTemplateVariable::factory()->for($history, 'version')->create(['default_text' => 'Historis']);
        $slot = LetterTemplateSignerSlot::factory()->for($history, 'version')->create();
        $setting = $slot->employeeAttributes()->create(['source_attribute' => 'nip', 'default_text' => 'Historis']);
        $before = app(LetterTemplateConfigurationHash::class)->hash($history);
        $this->actingAs(UserFactory::new()->create())->put($this->url($version), ['variables' => [self::variable(['default_text' => '-'])], 'signer_slots' => []])->assertSessionHasNoErrors();
        $this->assertNull($version->fresh()->validated_at);
        $this->assertNull($version->fresh()->tested_at);
        $this->assertSame('original.docx', $version->fresh()->source_path);
        $this->assertSame($before, app(LetterTemplateConfigurationHash::class)->hash($history->fresh()));
        $this->assertModelExists($setting);
        $this->assertSame('Historis', $old->fresh()->default_text);
        config(['app.locale' => 'en']);
        $this->assertNotSame($before, app(LetterTemplateConfigurationHash::class)->hash($history->fresh()));
    }

    public function test_changing_source_clears_employee_settings_and_deleting_configuration_removes_children(): void
    {
        $version = LetterTemplateVersion::factory()->create();
        $employee = Employee::factory()->create(['status' => AsnStatusEnum::AKTIF]);
        $variable = LetterTemplateVariable::factory()->for($version, 'version')->create(['type' => 4, 'option_source' => 2, 'is_multiple' => false]);
        $variable->employeeAttributes()->create(['source_attribute' => 'nip', 'default_text' => '-']);
        $slot = LetterTemplateSignerSlot::factory()->for($version, 'version')->create();
        $slot->employeeAttributes()->create(['source_attribute' => 'nip', 'default_text' => 'Slot']);
        $payload = ['variables' => [self::variable(['id' => $variable->uuid])], 'signer_slots' => [['id' => $slot->uuid, 'candidates' => [$employee->uuid]]]];
        $this->actingAs(UserFactory::new()->create())->put($this->url($version), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('letter_template_variable_employee_attributes', 0);
        $this->assertDatabaseCount('letter_template_signer_employee_attributes', 1);
        $this->put($this->url($version), ['variables' => [], 'signer_slots' => []])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('letter_template_signer_employee_attributes', 0);
        $this->assertDatabaseCount('letter_template_signer_slots', 0);
    }

    public function test_failed_snapshot_replacement_restores_attribute_children_and_evidence(): void
    {
        $version = LetterTemplateVersion::factory()->create(['validated_at' => now()]);
        $variable = LetterTemplateVariable::factory()->for($version, 'version')->create(['type' => 4, 'option_source' => 2, 'is_multiple' => false]);
        $setting = $variable->employeeAttributes()->create(['source_attribute' => 'nip', 'default_text' => 'Tetap']);
        $slot = LetterTemplateSignerSlot::factory()->for($version, 'version')->create();
        $slotSetting = $slot->employeeAttributes()->create(['source_attribute' => 'join_date', 'date_format' => 'd F Y']);
        Exceptions::fake();
        LetterTemplateVariable::creating(function (): void { throw new RuntimeException('Simulasi rollback pengaturan atribut.'); });
        try {
            $this->actingAs(UserFactory::new()->create())->put($this->url($version), ['variables' => [self::variable()], 'signer_slots' => []])->assertInternalServerError();
        } finally {
            LetterTemplateVariable::getEventDispatcher()->forget('eloquent.creating: ' . LetterTemplateVariable::class);
        }
        Exceptions::assertReported(RuntimeException::class);
        $this->assertModelExists($setting);
        $this->assertModelExists($slotSetting);
        $this->assertNotNull($version->fresh()->validated_at);
        $this->assertSame('Tetap', $setting->fresh()->default_text);
        $this->assertDatabaseCount('letter_template_audits', 0);
    }

    public function test_fingerprint_changes_for_fallback_date_format_and_locale(): void
    {
        $version = LetterTemplateVersion::factory()->create();
        $slot = LetterTemplateSignerSlot::factory()->for($version, 'version')->create();
        $setting = $slot->employeeAttributes()->create(['source_attribute' => 'join_date', 'date_format' => 'd F Y']);
        $hash = app(LetterTemplateConfigurationHash::class)->hash($version);
        $setting->update(['default_text' => '-']);
        $fallbackHash = app(LetterTemplateConfigurationHash::class)->hash($version->fresh());
        $this->assertNotSame($hash, $fallbackHash);
        $setting->update(['date_format' => 'Y-m-d']);
        $formatHash = app(LetterTemplateConfigurationHash::class)->hash($version->fresh());
        $this->assertNotSame($fallbackHash, $formatHash);
        config(['app.locale' => 'en']);
        $this->assertNotSame($formatHash, app(LetterTemplateConfigurationHash::class)->hash($version->fresh()));
    }

    private static function variable(array $changes = []): array
    {
        return [...['id' => null, 'name' => 'nama', 'label' => 'Nama', 'type' => 1, 'is_required' => false, 'option_source' => null, 'reference_group' => null, 'is_multiple' => false, 'columns' => []], ...$changes];
    }

    private function url(LetterTemplateVersion $version): string
    {
        return route('lt.configuration.update', [$version->template->uuid, $version->uuid]);
    }
}
