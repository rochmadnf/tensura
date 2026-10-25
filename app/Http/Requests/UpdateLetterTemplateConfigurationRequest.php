<?php

namespace App\Http\Requests;

use App\Enums\LetterTemplateOptionSourceEnum;
use App\Enums\LetterTemplateVariableTypeEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateLetterTemplateConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $placeholder = ['bail', 'required', 'string', 'max:100', 'regex:/\A[a-z][a-z0-9_]*\z/D'];

        return [
            'variables' => ['present', 'array', 'list'],
            'variables.*' => ['array:id,name,label,type,is_required,option_source,reference_group,is_multiple,columns'],
            'variables.*.id' => ['bail', 'nullable', 'uuid', 'distinct'],
            'variables.*.name' => $placeholder,
            'variables.*.label' => ['bail', 'required', 'string', 'max:100'],
            'variables.*.type' => ['bail', 'required', 'integer', Rule::enum(LetterTemplateVariableTypeEnum::class)],
            'variables.*.is_required' => ['required', 'boolean'],
            'variables.*.option_source' => ['bail', 'nullable', 'integer', Rule::enum(LetterTemplateOptionSourceEnum::class)],
            'variables.*.reference_group' => ['bail', 'nullable', 'uuid', Rule::exists('reference_groups', 'uuid')],
            'variables.*.is_multiple' => ['required', 'boolean'],
            'variables.*.columns' => ['present', 'array', 'list'],
            'variables.*.columns.*' => ['array:id,source_attribute,placeholder'],
            'variables.*.columns.*.id' => ['bail', 'nullable', 'uuid', 'distinct'],
            'variables.*.columns.*.source_attribute' => ['bail', 'required', 'string', 'max:50'],
            'variables.*.columns.*.placeholder' => $placeholder,
            'signer_slots' => ['present', 'array', 'list', 'max:32767'],
            'signer_slots.*' => ['array:id,candidates'],
            'signer_slots.*.id' => ['bail', 'nullable', 'uuid', 'distinct'],
            'signer_slots.*.candidates' => ['required', 'array', 'list', 'min:1'],
            'signer_slots.*.candidates.*' => ['bail', 'required', 'uuid'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'variables' => 'Variabel', 'variables.*.id' => 'Identitas Variabel',
            'variables.*.name' => 'Nama Placeholder', 'variables.*.label' => 'Label Variabel',
            'variables.*.type' => 'Jenis Variabel', 'variables.*.is_required' => 'Isian Wajib',
            'variables.*.option_source' => 'Sumber Pilihan', 'variables.*.reference_group' => 'Grup Referensi',
            'variables.*.is_multiple' => 'Pilihan Multiple', 'variables.*.columns' => 'Kolom Tabel',
            'variables.*.columns.*.id' => 'Identitas Kolom',
            'variables.*.columns.*.source_attribute' => 'Atribut Kolom',
            'variables.*.columns.*.placeholder' => 'Placeholder Kolom',
            'signer_slots.*.id' => 'Identitas Slot',
            'signer_slots' => 'Slot Penandatangan', 'signer_slots.*.candidates' => 'Kandidat Penandatangan',
            'signer_slots.*.candidates.*' => 'Kandidat Penandatangan',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'variables.*.name.regex' => ':attribute harus diawali huruf kecil dan hanya berisi huruf kecil, angka, atau garis bawah.',
            'variables.*.columns.*.placeholder.regex' => ':attribute harus diawali huruf kecil dan hanya berisi huruf kecil, angka, atau garis bawah.',
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $placeholders = [];
            foreach ($this->input('variables') as $index => $variable) {
                $path = "variables.{$index}";
                $this->claimPlaceholder($validator, $placeholders, $variable['name'], "{$path}.name");
                $isCombo = (int) $variable['type'] === LetterTemplateVariableTypeEnum::COMBOBOX->value;
                $source = isset($variable['option_source']) ? LetterTemplateOptionSourceEnum::from((int) $variable['option_source']) : null;
                $multiple = (bool) $variable['is_multiple'];
                if (!$isCombo && ($source !== null || $multiple || filled($variable['reference_group'] ?? null) || $variable['columns'] !== [])) {
                    $validator->errors()->add("{$path}.type", 'Sumber pilihan, multiple, dan kolom tabel hanya berlaku untuk combobox.');
                }
                if ($isCombo && $source === null) {
                    $validator->errors()->add("{$path}.option_source", 'Sumber pilihan wajib diisi untuk combobox.');
                }
                if ($isCombo && $source === LetterTemplateOptionSourceEnum::REFERENCE && blank($variable['reference_group'] ?? null)) {
                    $validator->errors()->add("{$path}.reference_group", 'Grup referensi wajib dipilih.');
                }
                if ($source !== LetterTemplateOptionSourceEnum::REFERENCE && filled($variable['reference_group'] ?? null)) {
                    $validator->errors()->add("{$path}.reference_group", 'Grup referensi hanya berlaku untuk sumber referensi.');
                }
                if ($isCombo && $multiple && $variable['columns'] === []) {
                    $validator->errors()->add("{$path}.columns", 'Combobox multiple wajib memiliki kolom tabel.');
                }
                if (!$multiple && $variable['columns'] !== []) {
                    $validator->errors()->add("{$path}.columns", 'Kolom tabel hanya berlaku untuk combobox multiple.');
                }
                foreach ($variable['columns'] as $columnIndex => $column) {
                    $columnPath = "{$path}.columns.{$columnIndex}";
                    if (!in_array($column['source_attribute'], array_column($source?->columnOptions() ?? [], 'id'), true)) {
                        $validator->errors()->add("{$columnPath}.source_attribute", 'Atribut kolom tidak tersedia untuk sumber pilihan ini.');
                    }
                    $this->claimPlaceholder($validator, $placeholders, $column['placeholder'], "{$columnPath}.placeholder");
                }
            }
            $slots = $this->input('signer_slots');
            foreach ($slots as $index => $slot) {
                if (count($slot['candidates']) !== count(array_unique($slot['candidates']))) {
                    $validator->errors()->add("signer_slots.{$index}.candidates", 'Kandidat dalam satu slot tidak boleh berulang.');
                }
                $placeholder = count($slots) === 1 ? 'penandatangan' : 'penandatangan_' . ($index + 1);
                $this->claimPlaceholder($validator, $placeholders, $placeholder, "signer_slots.{$index}.candidates");
            }
        }];
    }

    /** @param array<string, string> $placeholders */
    private function claimPlaceholder(Validator $validator, array &$placeholders, string $name, string $path): void
    {
        if (isset($placeholders[$name])) {
            $validator->errors()->add($path, "Placeholder {$name} sudah digunakan dalam versi ini.");

            return;
        }
        $placeholders[$name] = $path;
    }
}
