<?php

namespace App\Services\LetterTemplate;

use App\Enums\LetterTemplateOptionSourceEnum;
use App\Models\MasterData\LetterTemplateSignerSlot;
use App\Models\MasterData\LetterTemplateVariable;
use App\Models\MasterData\LetterTemplateVersion;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * @phpstan-type EmployeeSetting array{source_attribute: string, default_text: ?string, date_format: ?string}
 * @phpstan-type ExchangeVariable array{name: string, label: string, type: int, is_required: bool, is_multiple: bool, option_source: ?int, default_text: ?string, columns: list<array{source_attribute: string, placeholder: string, date_format: ?string}>, employee_attributes: list<EmployeeSetting>}
 * @phpstan-type ExchangeConfiguration array{variables: list<ExchangeVariable>, signer_slots: list<array{employee_attributes: list<EmployeeSetting>}>}
 * @phpstan-type EmployeeContract array<string, array{type: string, dependencies: array<string, string>}>
 */
class LetterTemplateExchangeConfiguration
{
    public function __construct(private LetterTemplateConfigurationRules $rules) {}

    /** @return ExchangeConfiguration */
    public function export(LetterTemplateVersion $version): array
    {
        $version->load(['variables.columns', 'variables.employeeAttributes', 'signerSlots.employeeAttributes']);

        return [
            'variables' => $version->variables->map(fn(LetterTemplateVariable $variable): array => [
                'name' => $variable->name, 'label' => $variable->label, 'type' => $variable->type->value,
                'is_required' => $variable->is_required, 'is_multiple' => $variable->is_multiple,
                'option_source' => $variable->option_source?->value, 'default_text' => $variable->default_text,
                'columns' => $variable->columns->map->only(['source_attribute', 'placeholder', 'date_format'])->all(),
                'employee_attributes' => $variable->employeeAttributes->map->only(['source_attribute', 'default_text', 'date_format'])->all(),
            ])->all(),
            'signer_slots' => $version->signerSlots->map(fn(LetterTemplateSignerSlot $slot): array => [
                'employee_attributes' => $slot->employeeAttributes->map->only(['source_attribute', 'default_text', 'date_format'])->all(),
            ])->all(),
        ];
    }

    /** @param array<string, mixed> $configuration */
    public function validate(array $configuration): void
    {
        Validator::make($configuration, [
            'variables' => ['present', 'array', 'list', 'max:200'],
            'signer_slots' => ['present', 'array', 'list', 'max:50'],
            'variables.*' => ['array'],
            'variables.*.columns' => ['present', 'array', 'list', 'max:100'],
        ])->validate();
        foreach ($configuration['variables'] as &$variable) {
            if (!is_array($variable) || array_diff(array_keys($variable), ['name', 'label', 'type', 'is_required', 'is_multiple', 'option_source', 'default_text', 'columns', 'employee_attributes']) !== []) {
                $this->reject();
            }
            if (!is_int($variable['type'] ?? null) || !is_bool($variable['is_required'] ?? null) || !is_bool($variable['is_multiple'] ?? null) || (isset($variable['option_source']) && !is_int($variable['option_source']))) {
                $this->reject();
            }
            foreach ($variable['columns'] ?? [] as $column) {
                if (!is_array($column) || array_diff(array_keys($column), ['source_attribute', 'placeholder', 'date_format']) !== []) {
                    $this->reject();
                }
            }
            $variable['reference_group'] = ($variable['option_source'] ?? null) === LetterTemplateOptionSourceEnum::REFERENCE->value ? '00000000-0000-4000-8000-000000000001' : null;
        }
        unset($variable);
        foreach ($configuration['signer_slots'] as &$slot) {
            if (!is_array($slot) || array_keys($slot) !== ['employee_attributes']) {
                $this->reject();
            }
            $slot['candidates'] = [];
        }
        unset($slot);
        $rules = $this->rules->rules();
        $rules['variables.*.reference_group'] = ['nullable', 'uuid'];
        $rules['signer_slots.*.candidates'] = ['present', 'array'];
        $validator = Validator::make($configuration, $rules, $this->rules->messages(), $this->rules->attributes());
        foreach ($this->rules->after($configuration) as $after) {
            $validator->after($after);
        }
        $validator->validate();
    }

    /**
     * @param  ExchangeConfiguration  $configuration
     * @return EmployeeContract
     */
    public function contract(array $configuration): array
    {
        $attributes = [];
        foreach ($configuration['variables'] as $variable) {
            if (($variable['option_source'] ?? null) !== LetterTemplateOptionSourceEnum::EMPLOYEE->value) {
                continue;
            }
            $attributes = [...$attributes, ...($variable['is_multiple'] ? array_column($variable['columns'], 'source_attribute') : array_column(EmployeeAttributeCatalog::options(), 'id'))];
        }
        if ($configuration['signer_slots'] !== []) {
            $attributes = [...$attributes, ...array_column(EmployeeAttributeCatalog::options(), 'id')];
        }
        $schema = [];
        foreach (['employees', 'job_titles'] as $table) {
            foreach (Schema::getColumns($table) as $column) {
                $schema[$table . '.' . $column['name']] = $column['type_name'];
            }
        }
        $options = collect(EmployeeAttributeCatalog::options())->keyBy('id');
        $contract = [];
        foreach (array_unique($attributes) as $attribute) {
            if (!$options->has($attribute)) {
                $this->reject();
            }
            $columns = match ($attribute) {
                'name' => ['employees.name', 'employees.front_title', 'employees.back_title'],
                'raw_name' => ['employees.name'],
                'tenure', 'tenure_years', 'tenure_months', 'tenure_days' => ['employees.join_date'],
                'job_title' => ['employees.job_title_id', 'job_titles.id', 'job_titles.name'],
                'job_title_group' => ['employees.job_title_id', 'job_titles.id', 'job_titles.group'],
                default => ['employees.' . $attribute],
            };
            $dependencies = [];
            foreach ($columns as $column) {
                $dependencies[$column] = $schema[$column] ?? 'missing';
            }
            $contract[$attribute] = ['type' => $options[$attribute]['type'], 'dependencies' => $dependencies];
        }
        ksort($contract);

        return $contract;
    }

    /**
     * @param  ExchangeConfiguration  $configuration
     * @param  EmployeeContract  $source
     * @return list<string>
     */
    public function incompatibilities(array $configuration, array $source): array
    {
        $destination = $this->contract($configuration);
        $errors = [];
        if (array_keys($destination) !== array_keys($source)) {
            $errors[] = 'Kontrak atribut pegawai tidak lengkap atau tidak dikenali.';
        }
        foreach ($destination as $attribute => $definition) {
            if (($source[$attribute] ?? null) != $definition || in_array('missing', $definition['dependencies'], true)) {
                $errors[] = "Atribut pegawai {$attribute} hilang atau tidak kompatibel pada instalasi tujuan.";
            }
        }

        return $errors;
    }

    private function reject(): never
    {
        throw ValidationException::withMessages(['package' => 'Konfigurasi paket tidak valid atau memiliki atribut yang tidak didukung.']);
    }
}
