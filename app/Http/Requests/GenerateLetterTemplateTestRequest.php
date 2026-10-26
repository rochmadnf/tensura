<?php

namespace App\Http\Requests;

use App\Enums\LetterTemplateVariableTypeEnum;
use App\Services\LetterTemplate\LetterTemplateTestFields;
use Illuminate\Foundation\Http\FormRequest;

class GenerateLetterTemplateTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<mixed>> */
    public function rules(LetterTemplateTestFields $fields): array
    {
        $version = $this->route('version');
        $variables = $fields->variables($version);
        $slots = $fields->slots($version);
        $rules = [
            'validation_token' => ['required', 'string', 'size:64'],
            'values' => ['present', 'array' . ($variables->isEmpty() ? '' : ':' . $variables->pluck('uuid')->implode(','))],
            'signers' => ['present', 'array' . ($slots === [] ? '' : ':' . implode(',', array_keys($slots)))],
        ];
        if ($variables->isEmpty()) {
            $rules['values'][] = 'max:0';
        }
        if ($slots === []) {
            $rules['signers'][] = 'max:0';
        }
        foreach ($variables as $variable) {
            $path = 'values.' . $variable->uuid;
            $rules[$path] = [$variable->is_required ? 'required' : 'nullable'];
            if ($variable->is_multiple) {
                $rules[$path] = [...$rules[$path], 'array', 'list', 'max:' . config('letter-templates.test.max_rows')];
                if ($variable->is_required) {
                    $rules[$path][] = 'min:1';
                }
                $rules[$path . '.*'] = ['required', 'uuid', 'distinct'];
            } elseif ($variable->type === LetterTemplateVariableTypeEnum::COMBOBOX) {
                $rules[$path] = [...$rules[$path], 'uuid'];
            } elseif ($variable->type === LetterTemplateVariableTypeEnum::DATE) {
                $rules[$path] = [...$rules[$path], 'date_format:Y-m-d'];
            } else {
                $rules[$path] = [...$rules[$path], 'string', 'max:' . config('letter-templates.test.' . ($variable->type === LetterTemplateVariableTypeEnum::LONG_TEXT ? 'long_text_max' : 'short_text_max')), 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F]/'];
            }
        }
        foreach ($slots as $uuid => $prefix) {
            $rules['signers.' . $uuid] = ['required', 'uuid', 'distinct'];
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        $labels = ['validation_token' => 'Versi Validasi', 'values' => 'Data Contoh', 'signers' => 'Penandatangan'];
        $version = $this->route('version');
        foreach ($version->variables as $variable) {
            $labels['values.' . $variable->uuid] = $variable->label;
            $labels['values.' . $variable->uuid . '.*'] = $variable->label;
        }
        foreach ($version->signerSlots as $index => $slot) {
            $labels['signers.' . $slot->uuid] = 'Penandatangan Slot ' . ($index + 1);
        }

        return $labels;
    }
}
