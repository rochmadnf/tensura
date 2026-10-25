<?php

namespace App\Http\Resources\MasterData;

use App\Enums\AsnStatusEnum;
use App\Enums\LetterTemplateOptionSourceEnum;
use App\Enums\LetterTemplateVersionStatusEnum;
use App\Models\MasterData\Employee;
use App\Models\MasterData\LetterTemplateSignerCandidate;
use App\Models\MasterData\LetterTemplateSignerSlot;
use App\Models\MasterData\LetterTemplateVariable;
use App\Models\MasterData\LetterTemplateVariableColumn;
use App\Models\MasterData\LetterTemplateVersion;
use App\Services\LetterTemplate\EmployeeAttributeCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LetterTemplateVersion */
class LetterTemplateConfigurationResource extends JsonResource
{
    /**
     * @return array{
     *     id: string, editable: bool, has_configuration: bool,
     *     variables: list<array{id: string, name: string, label: string, type: int, is_required: bool,
     *         option_source: ?int, reference_group: ?string, is_multiple: bool, default_text: ?string,
     *         employee_attributes: list<array{source_attribute: string, default_text: ?string, date_format: ?string}>,
     *         columns: list<array{id: string, source_attribute: string, placeholder: string, date_format: ?string}>}>,
     *     signer_slots: list<array{id: string, placeholder: string, candidates: list<string>, employee_attributes: list<array{source_attribute: string, default_text: ?string, date_format: ?string}>}>,
     *     warnings: list<array{slot: int, employee: string, name: string}>
     * }
     */
    public function toArray(Request $request): array
    {
        $slotCount = $this->signerSlots->count();

        return [
            'id' => $this->uuid,
            'has_configuration' => (bool) $this->configuration_saved,
            'editable' => $this->status === LetterTemplateVersionStatusEnum::DRAFT && $this->first_used_at === null,
            'variables' => $this->variables->map(fn(LetterTemplateVariable $variable): array => [
                'id' => $variable->uuid, 'name' => $variable->name, 'label' => $variable->label,
                'type' => $variable->type->value, 'is_required' => $variable->is_required,
                'option_source' => $variable->option_source?->value, 'reference_group' => $variable->referenceGroup?->uuid,
                'is_multiple' => $variable->is_multiple,
                'default_text' => $variable->default_text,
                'employee_attributes' => $variable->option_source === LetterTemplateOptionSourceEnum::EMPLOYEE && !$variable->is_multiple ? EmployeeAttributeCatalog::settings($variable->employeeAttributes->map->only(['source_attribute', 'default_text', 'date_format'])->all()) : [],
                'columns' => $variable->columns->map(fn(LetterTemplateVariableColumn $column): array => [
                    'id' => $column->uuid, 'source_attribute' => $column->source_attribute, 'placeholder' => $column->placeholder,
                    'date_format' => $variable->option_source === LetterTemplateOptionSourceEnum::EMPLOYEE && EmployeeAttributeCatalog::isDate($column->source_attribute) ? ($column->date_format ?? 'd-m-Y') : null,
                ])->all(),
            ])->all(),
            'signer_slots' => $this->signerSlots->map(fn(LetterTemplateSignerSlot $slot, int $index): array => [
                'id' => $slot->uuid,
                'placeholder' => $slotCount === 1 ? 'penandatangan' : 'penandatangan_' . ($index + 1),
                'employee_attributes' => EmployeeAttributeCatalog::settings($slot->employeeAttributes->map->only(['source_attribute', 'default_text', 'date_format'])->all()),
                'candidates' => $slot->candidates->map(fn(LetterTemplateSignerCandidate $candidate): string => $candidate->employee->uuid)->all(),
            ])->all(),
            'warnings' => $this->signerSlots->flatMap(fn(LetterTemplateSignerSlot $slot, int $index) => $slot->candidates
                ->filter(fn(LetterTemplateSignerCandidate $candidate): bool => $candidate->employee->status !== AsnStatusEnum::AKTIF)
                ->map(fn(LetterTemplateSignerCandidate $candidate): array => [
                    'slot' => $index + 1, 'employee' => $candidate->employee->uuid,
                    'name' => self::employeeName($candidate->employee),
                ]))->values()->all(),
        ];
    }

    public static function employeeName(Employee $employee): string
    {
        return trim(($employee->front_title ?? '') . ' ' . $employee->name . ' ' . ($employee->back_title ?? ''));
    }
}
