<?php

namespace App\Services\LetterTemplate;

use App\Enums\AsnStatusEnum;
use App\Enums\LetterTemplateOptionSourceEnum;
use App\Enums\LetterTemplateVersionStatusEnum;
use App\Models\MasterData\Employee;
use App\Models\MasterData\LetterTemplate;
use App\Models\MasterData\LetterTemplateSignerCandidate;
use App\Models\MasterData\LetterTemplateSignerEmployeeAttribute;
use App\Models\MasterData\LetterTemplateVariableColumn;
use App\Models\MasterData\LetterTemplateVariableEmployeeAttribute;
use App\Models\MasterData\LetterTemplateVersion;
use App\Models\MasterData\ReferenceGroup;
use App\Models\MasterData\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveLetterTemplateConfiguration
{
    public function __construct(private RecordLetterTemplateAudit $audit, private InvalidateLetterTemplateEvidence $evidence) {}

    /** @param array{variables: list<array{id?: ?string, name: string, label: string, type: int, is_required: bool, option_source?: ?int, reference_group?: ?string, is_multiple: bool, default_text?: ?string, employee_attributes?: ?list<array{source_attribute: string, default_text?: ?string, date_format?: ?string}>, columns: list<array{id?: ?string, source_attribute: string, placeholder: string, date_format?: ?string}>}>, signer_slots: list<array{id?: ?string, candidates: list<string>, employee_attributes?: ?list<array{source_attribute: string, default_text?: ?string, date_format?: ?string}>}>} $data */
    public function save(LetterTemplate $template, LetterTemplateVersion $version, array $data, User $actor): void
    {
        DB::transaction(function () use ($template, $version, $data, $actor): void {
            $template = LetterTemplate::query()->whereKey($template->id)->lockForUpdate()->firstOrFail();
            $version = $template->versions()->whereKey($version->id)->lockForUpdate()->firstOrFail();
            $before = $this->audit->snapshot($template, $version);
            if ($version->status !== LetterTemplateVersionStatusEnum::DRAFT || $version->first_used_at !== null) {
                throw ValidationException::withMessages(['configuration' => 'Konfigurasi hanya dapat diubah pada draft yang belum digunakan.']);
            }
            $version->load(['variables.columns', 'variables.employeeAttributes', 'signerSlots.candidates', 'signerSlots.employeeAttributes']);
            $this->validateChildOwnership($version, $data);

            $groupUuids = collect($data['variables'])->pluck('reference_group')->filter()->unique()->all();
            $groups = ReferenceGroup::query()->whereIn('uuid', $groupUuids)->orderBy('id')->sharedLock()->get()->keyBy('uuid');
            foreach ($data['variables'] as $index => $variable) {
                if (filled($variable['reference_group'] ?? null) && !$groups->has($variable['reference_group'])) {
                    throw ValidationException::withMessages(["variables.{$index}.reference_group" => 'Grup referensi sudah tidak tersedia.']);
                }
            }
            $employeeUuids = collect($data['signer_slots'])->pluck('candidates')->flatten()->unique()->all();
            $employees = Employee::query()->whereIn('uuid', $employeeUuids)->orderBy('id')->sharedLock()->get()->keyBy('uuid');
            foreach ($data['signer_slots'] as $index => $slot) {
                foreach ($slot['candidates'] as $candidateIndex => $uuid) {
                    if (!$employees->has($uuid) || $employees->get($uuid)->status !== AsnStatusEnum::AKTIF) {
                        throw ValidationException::withMessages(["signer_slots.{$index}.candidates.{$candidateIndex}" => 'Kandidat penandatangan harus pegawai aktif yang masih tersedia.']);
                    }
                }
            }

            $this->replaceConfiguration($version, $data, $groups->map->id->all(), $employees->map->id->all());
            $this->evidence->invalidate($version);
            $version->save();
            $this->audit->record($template, $version, $actor, 'configuration.updated', 'Variabel dan penandatangan diperbarui; bukti pemeriksaan dibatalkan.', $before);
        }, 5);
    }

    /** @param array{variables: list<array<string, mixed>>, signer_slots: list<array<string, mixed>>} $data */
    private function validateChildOwnership(LetterTemplateVersion $version, array $data): void
    {
        $variables = $version->variables->keyBy('uuid');
        $slots = $version->signerSlots->keyBy('uuid');
        foreach ($data['variables'] as $index => $variable) {
            $existing = isset($variable['id']) ? $variables->get($variable['id']) : null;
            if (isset($variable['id']) && $existing === null) {
                throw ValidationException::withMessages(["variables.{$index}.id" => 'Variabel bukan milik versi ini.']);
            }
            foreach ($variable['columns'] as $columnIndex => $column) {
                if (isset($column['id']) && !($existing?->columns->contains('uuid', $column['id']) ?? false)) {
                    throw ValidationException::withMessages(["variables.{$index}.columns.{$columnIndex}.id" => 'Kolom bukan milik variabel ini.']);
                }
            }
        }
        foreach ($data['signer_slots'] as $index => $slot) {
            if (isset($slot['id']) && !$slots->has($slot['id'])) {
                throw ValidationException::withMessages(["signer_slots.{$index}.id" => 'Slot bukan milik versi ini.']);
            }
        }
    }

    /**
     * Ganti snapshot konfigurasi draft secara atomik; UUID existing yang sudah diperiksa tetap dipertahankan.
     *
     * @param  array{variables: list<array<string, mixed>>, signer_slots: list<array<string, mixed>>}  $data
     * @param  array<string, int>  $groups
     * @param  array<string, int>  $employees
     */
    private function replaceConfiguration(LetterTemplateVersion $version, array $data, array $groups, array $employees): void
    {
        $oldVariables = $version->variables->keyBy('uuid');
        $oldSlots = $version->signerSlots->keyBy('uuid');
        LetterTemplateVariableEmployeeAttribute::query()->whereIn('variable_id', $version->variables->modelKeys())->delete();
        LetterTemplateSignerEmployeeAttribute::query()->whereIn('slot_id', $version->signerSlots->modelKeys())->delete();
        LetterTemplateVariableColumn::query()->whereIn('variable_id', $version->variables->modelKeys())->delete();
        $version->variables()->delete();
        LetterTemplateSignerCandidate::query()->whereIn('slot_id', $version->signerSlots->modelKeys())->delete();
        $version->signerSlots()->delete();

        foreach ($data['variables'] as $index => $variable) {
            $old = $oldVariables->get($variable['id'] ?? '');
            $employeeSingle = (int) ($variable['option_source'] ?? 0) === LetterTemplateOptionSourceEnum::EMPLOYEE->value && !$variable['is_multiple'];
            $sameSource = $old !== null && $old->type->value === (int) $variable['type'] && $old->option_source?->value === (isset($variable['option_source']) ? (int) $variable['option_source'] : null) && $old->is_multiple === (bool) $variable['is_multiple'];
            $model = $version->variables()->make([
                'name' => $variable['name'], 'label' => $variable['label'], 'type' => $variable['type'],
                'is_required' => $variable['is_required'], 'sort_order' => $index,
                'option_source' => $variable['option_source'] ?? null,
                'reference_group_id' => isset($variable['reference_group']) ? $groups[$variable['reference_group']] : null,
                'is_multiple' => $variable['is_multiple'],
                'default_text' => $variable['is_multiple'] || $employeeSingle ? null : (array_key_exists('default_text', $variable) ? $variable['default_text'] : ($sameSource ? $old?->default_text : null)),
            ]);
            if (isset($variable['id'])) {
                $model->uuid = $variable['id'];
            }
            $model->save();
            if ($employeeSingle) {
                $settings = array_key_exists('employee_attributes', $variable) ? ($variable['employee_attributes'] ?? []) : ($sameSource ? $old->employeeAttributes->map->only(['source_attribute', 'default_text', 'date_format'])->all() : []);
                $model->employeeAttributes()->createMany($this->attributeSettings($settings));
            }
            foreach ($variable['columns'] as $columnIndex => $column) {
                $oldColumn = $old?->columns->firstWhere('uuid', $column['id'] ?? null);
                $dateFormat = (int) ($variable['option_source'] ?? 0) === LetterTemplateOptionSourceEnum::EMPLOYEE->value && EmployeeAttributeCatalog::isDate($column['source_attribute']);
                $columnModel = $model->columns()->make([
                    'source_attribute' => $column['source_attribute'], 'placeholder' => $column['placeholder'], 'sort_order' => $columnIndex,
                    'date_format' => $dateFormat ? (array_key_exists('date_format', $column) ? $column['date_format'] : ($sameSource && $oldColumn?->source_attribute === $column['source_attribute'] ? $oldColumn?->date_format : null)) : null,
                ]);
                if (isset($column['id'])) {
                    $columnModel->uuid = $column['id'];
                }
                $columnModel->save();
            }
        }
        foreach ($data['signer_slots'] as $index => $slot) {
            $model = $version->signerSlots()->make(['sort_order' => $index + 1]);
            if (isset($slot['id'])) {
                $model->uuid = $slot['id'];
            }
            $model->save();
            $settings = array_key_exists('employee_attributes', $slot) ? ($slot['employee_attributes'] ?? []) : ($oldSlots->get($slot['id'] ?? '')?->employeeAttributes->map->only(['source_attribute', 'default_text', 'date_format'])->all() ?? []);
            $model->employeeAttributes()->createMany($this->attributeSettings($settings));
            foreach ($slot['candidates'] as $uuid) {
                $model->candidates()->create(['employee_id' => $employees[$uuid]]);
            }
        }
    }

    /**
     * @param  list<array{source_attribute: string, default_text?: ?string, date_format?: ?string}>  $settings
     * @return list<array{source_attribute: string, default_text: ?string, date_format: ?string}>
     */
    private function attributeSettings(array $settings): array
    {
        return array_values(array_filter(EmployeeAttributeCatalog::settings($settings), fn(array $setting): bool => filled($setting['default_text']) || ($setting['date_format'] !== null && $setting['date_format'] !== 'd-m-Y')));
    }
}
