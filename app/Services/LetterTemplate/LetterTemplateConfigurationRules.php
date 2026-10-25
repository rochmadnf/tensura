<?php

namespace App\Services\LetterTemplate;

use App\Enums\LetterTemplateOptionSourceEnum;
use App\Enums\LetterTemplateVariableTypeEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class LetterTemplateConfigurationRules
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $placeholder = ['bail', 'required', 'string', 'max:100', 'regex:/\A[a-z][a-z0-9_]*\z/D'];

        return [
            'variables' => ['present', 'array', 'list'],
            'variables.*' => [
                'array:id,name,label,type,is_required,option_source,reference_group,is_multiple,columns,default_text,employee_attributes',
            ],
            'variables.*.id' => ['bail', 'nullable', 'uuid', 'distinct'],
            'variables.*.name' => $placeholder,
            'variables.*.label' => ['bail', 'required', 'string', 'max:100'],
            'variables.*.type' => ['bail', 'required', 'integer', Rule::enum(LetterTemplateVariableTypeEnum::class)],
            'variables.*.is_required' => ['required', 'boolean'],
            'variables.*.option_source' => [
                'bail',
                'nullable',
                'integer',
                Rule::enum(LetterTemplateOptionSourceEnum::class),
            ],
            'variables.*.reference_group' => ['bail', 'nullable', 'uuid', Rule::exists('reference_groups', 'uuid')],
            'variables.*.is_multiple' => ['required', 'boolean'],
            'variables.*.columns' => ['present', 'array', 'list'],
            'variables.*.columns.*' => ['array:id,source_attribute,placeholder,date_format'],
            'variables.*.columns.*.id' => ['bail', 'nullable', 'uuid', 'distinct'],
            'variables.*.columns.*.source_attribute' => ['bail', 'required', 'string', 'max:50'],
            'variables.*.columns.*.placeholder' => $placeholder,
            'variables.*.default_text' => ['nullable', 'string', 'max:10000'],
            'variables.*.columns.*.date_format' => ['nullable', 'string', 'max:100', 'not_regex:/[\x00-\x1F]/'],
            ...$this->employeeAttributeRules('variables.*'),
            ...$this->employeeAttributeRules('signer_slots.*'),
            'signer_slots' => ['present', 'array', 'list', 'max:32767'],
            'signer_slots.*' => ['array:id,candidates,employee_attributes'],
            'signer_slots.*.id' => ['bail', 'nullable', 'uuid', 'distinct'],
            'signer_slots.*.candidates' => ['required', 'array', 'list', 'min:1'],
            'signer_slots.*.candidates.*' => ['bail', 'required', 'uuid'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'variables' => 'Variabel',
            'variables.*.id' => 'Identitas Pertanyaan',
            'variables.*.name' => 'Nama Variabel',
            'variables.*.label' => 'Pertanyaan',
            'variables.*.type' => 'Jenis Pertanyaan',
            'variables.*.is_required' => 'Wajib diisi',
            'variables.*.option_source' => 'Sumber Pilihan',
            'variables.*.reference_group' => 'Grup Referensi',
            'variables.*.is_multiple' => 'Pilihan lebih dari satu',
            'variables.*.columns' => 'Kolom Tabel',
            'variables.*.columns.*.id' => 'Identitas Kolom',
            'variables.*.columns.*.source_attribute' => 'Atribut Kolom',
            'variables.*.columns.*.placeholder' => 'Placeholder Kolom',
            'variables.*.default_text' => 'Teks Alternatif Variabel',
            'variables.*.columns.*.date_format' => 'Format Tanggal Kolom',
            'variables.*.employee_attributes' => 'Pengaturan Atribut Pegawai',
            'variables.*.employee_attributes.*.source_attribute' => 'Atribut Pegawai',
            'variables.*.employee_attributes.*.default_text' => 'Teks Alternatif Atribut Pegawai',
            'variables.*.employee_attributes.*.date_format' => 'Format Tanggal Pegawai',
            'signer_slots.*.employee_attributes' => 'Pengaturan Atribut Penandatangan',
            'signer_slots.*.employee_attributes.*.source_attribute' => 'Atribut Penandatangan',
            'signer_slots.*.employee_attributes.*.default_text' => 'Teks Alternatif Atribut Penandatangan',
            'signer_slots.*.employee_attributes.*.date_format' => 'Format Tanggal Penandatangan',
            'signer_slots.*.id' => 'Identitas Slot',
            'signer_slots' => 'Slot Penandatangan',
            'signer_slots.*.candidates' => 'Kandidat Penandatangan',
            'signer_slots.*.candidates.*' => 'Kandidat Penandatangan',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'variables.*.name.regex' =>
                ':attribute harus diawali huruf kecil dan hanya berisi huruf kecil, angka, atau garis bawah.',
            'variables.*.columns.*.placeholder.regex' =>
                ':attribute harus diawali huruf kecil dan hanya berisi huruf kecil, angka, atau garis bawah.',
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(array $data): array
    {
        return [
            function (Validator $validator) use ($data): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $placeholders = [];
                foreach ($data['variables'] as $index => $variable) {
                    $path = "variables.{$index}";
                    $this->claimPlaceholder($validator, $placeholders, $variable['name'], "{$path}.name");
                    $isCombo = (int) $variable['type'] === LetterTemplateVariableTypeEnum::COMBOBOX->value;
                    $source = isset($variable['option_source'])
                        ? LetterTemplateOptionSourceEnum::from((int) $variable['option_source'])
                        : null;
                    $multiple = (bool) $variable['is_multiple'];
                    if (
                        !$isCombo &&
                        ($source !== null ||
                            $multiple ||
                            filled($variable['reference_group'] ?? null) ||
                            $variable['columns'] !== [])
                    ) {
                        $validator
                            ->errors()
                            ->add(
                                "{$path}.type",
                                'Sumber pilihan, multiple, dan kolom tabel hanya berlaku untuk combobox.',
                            );
                    }
                    if ($isCombo && $source === null) {
                        $validator
                            ->errors()
                            ->add("{$path}.option_source", 'Sumber pilihan wajib diisi untuk combobox.');
                    }
                    if (
                        $isCombo &&
                        $source === LetterTemplateOptionSourceEnum::REFERENCE &&
                        blank($variable['reference_group'] ?? null)
                    ) {
                        $validator->errors()->add("{$path}.reference_group", 'Grup referensi wajib dipilih.');
                    }
                    if (
                        $source !== LetterTemplateOptionSourceEnum::REFERENCE &&
                        filled($variable['reference_group'] ?? null)
                    ) {
                        $validator
                            ->errors()
                            ->add("{$path}.reference_group", 'Grup referensi hanya berlaku untuk sumber referensi.');
                    }
                    if ($isCombo && $multiple && $variable['columns'] === []) {
                        $validator->errors()->add("{$path}.columns", 'Combobox multiple wajib memiliki kolom tabel.');
                    }
                    if (!$multiple && $variable['columns'] !== []) {
                        $validator
                            ->errors()
                            ->add("{$path}.columns", 'Kolom tabel hanya berlaku untuk combobox multiple.');
                    }
                    $employeeSingle = $isCombo && $source === LetterTemplateOptionSourceEnum::EMPLOYEE && !$multiple;
                    if ($employeeSingle) {
                        foreach (array_keys(EmployeeAttributeCatalog::tokens($variable['name'])) as $token) {
                            if ($token !== $variable['name']) {
                                $this->claimPlaceholder($validator, $placeholders, $token, "{$path}.name");
                            }
                        }
                    }
                    if (($multiple || $employeeSingle) && filled($variable['default_text'] ?? null)) {
                        $validator
                            ->errors()
                            ->add(
                                "{$path}.default_text",
                                'Teks alternatif pada pertanyaan tidak berlaku untuk pilihan Pegawai atau pilihan lebih dari satu.',
                            );
                    }
                    if (!$employeeSingle && filled($variable['employee_attributes'] ?? [])) {
                        $validator
                            ->errors()
                            ->add(
                                "{$path}.employee_attributes",
                                'Pengaturan atribut hanya berlaku untuk variabel Pegawai tunggal.',
                            );
                    }
                    $this->validateEmployeeSettings(
                        $validator,
                        $variable['employee_attributes'] ?? [],
                        "{$path}.employee_attributes",
                    );
                    foreach ($variable['columns'] as $columnIndex => $column) {
                        $columnPath = "{$path}.columns.{$columnIndex}";
                        if (
                            !in_array(
                                $column['source_attribute'],
                                array_column($source?->columnOptions() ?? [], 'id'),
                                true,
                            )
                        ) {
                            $validator
                                ->errors()
                                ->add(
                                    "{$columnPath}.source_attribute",
                                    'Atribut kolom tidak tersedia untuk sumber pilihan ini.',
                                );
                        }
                        if (
                            filled($column['date_format'] ?? null) &&
                            ($source !== LetterTemplateOptionSourceEnum::EMPLOYEE ||
                                !EmployeeAttributeCatalog::isDate($column['source_attribute']))
                        ) {
                            $validator
                                ->errors()
                                ->add(
                                    "{$columnPath}.date_format",
                                    'Format tanggal hanya berlaku untuk atribut tanggal Pegawai.',
                                );
                        }
                        $this->claimPlaceholder(
                            $validator,
                            $placeholders,
                            $column['placeholder'],
                            "{$columnPath}.placeholder",
                        );
                    }
                }
                $slots = $data['signer_slots'];
                foreach ($slots as $index => $slot) {
                    if (count($slot['candidates']) !== count(array_unique($slot['candidates']))) {
                        $validator
                            ->errors()
                            ->add("signer_slots.{$index}.candidates", 'Kandidat dalam satu slot tidak boleh berulang.');
                    }
                    $placeholder = count($slots) === 1 ? 'penandatangan' : 'penandatangan_' . ($index + 1);
                    foreach (array_keys(EmployeeAttributeCatalog::tokens($placeholder)) as $token) {
                        $this->claimPlaceholder($validator, $placeholders, $token, "signer_slots.{$index}.candidates");
                    }
                    $this->validateEmployeeSettings(
                        $validator,
                        $slot['employee_attributes'] ?? [],
                        "signer_slots.{$index}.employee_attributes",
                    );
                }
            },
        ];
    }

    /** @return array<string, array<mixed>> */
    private function employeeAttributeRules(string $prefix): array
    {
        return [
            "{$prefix}.employee_attributes" => [
                'nullable',
                'array',
                'list',
                'max:' . count(EmployeeAttributeCatalog::options()),
            ],
            "{$prefix}.employee_attributes.*" => ['array:source_attribute,default_text,date_format'],
            "{$prefix}.employee_attributes.*.source_attribute" => [
                'required',
                'string',
                Rule::in(array_column(EmployeeAttributeCatalog::options(), 'id')),
            ],
            "{$prefix}.employee_attributes.*.default_text" => ['nullable', 'string', 'max:10000'],
            "{$prefix}.employee_attributes.*.date_format" => [
                'nullable',
                'string',
                'max:100',
                'not_regex:/[\\x00-\\x1F]/',
            ],
        ];
    }

    /** @param list<array{source_attribute: string, default_text?: ?string, date_format?: ?string}> $settings */
    private function validateEmployeeSettings(Validator $validator, array $settings, string $path): void
    {
        $seen = [];
        foreach ($settings as $index => $setting) {
            $attribute = $setting['source_attribute'];
            if (in_array($attribute, $seen, true)) {
                $validator
                    ->errors()
                    ->add("{$path}.{$index}.source_attribute", 'Atribut dalam satu pemilik tidak boleh berulang.');
            }
            $seen[] = $attribute;
            if (filled($setting['date_format'] ?? null) && !EmployeeAttributeCatalog::isDate($attribute)) {
                $validator
                    ->errors()
                    ->add(
                        "{$path}.{$index}.date_format",
                        'Format tanggal hanya berlaku untuk atribut tanggal Pegawai.',
                    );
            }
        }
    }

    /** @param array<string, string> $placeholders */
    private function claimPlaceholder(Validator $validator, array &$placeholders, string $name, string $path): void
    {
        if (strlen($name) > config('letter-templates.placeholders.max_length')) {
            $validator->errors()->add($path, "Placeholder otomatis {$name} melebihi batas panjang placeholder.");
        }
        if (isset($placeholders[$name])) {
            $validator->errors()->add($path, "Placeholder {$name} sudah digunakan dalam versi ini.");

            return;
        }
        $placeholders[$name] = $path;
    }
}
