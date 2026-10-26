<?php

namespace App\Http\Requests;

use App\Services\LetterTemplate\LetterTemplateConfigurationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeLetterTemplateConfigurationItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(LetterTemplateConfigurationRules $configuration): array
    {
        return match ($this->route('operation')) {
            'delete' => [],
            'move' => ['direction' => ['required', Rule::in(['up', 'down'])]],
            'attribute' => [
                'default_text' => ['present', 'nullable', 'string', 'max:10000'],
                'date_format' => ['present', 'nullable', 'string', 'max:100', 'not_regex:/[\x00-\x1F]/'],
            ],
            default => $this->itemRules($configuration),
        };
    }

    /** @return array<string, mixed> */
    private function itemRules(LetterTemplateConfigurationRules $configuration): array
    {
        $prefix = $this->route('kind') === 'variable' ? 'variables.*' : 'signer_slots.*';
        $rules = ['item' => ['required', ...$configuration->rules()[$prefix]]];
        foreach ($configuration->rules() as $key => $value) {
            if (str_starts_with($key, $prefix . '.')) {
                $rules['item' . substr($key, strlen($prefix))] = $value;
            }
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        $prefix = $this->route('kind') === 'variable' ? 'variables.*' : 'signer_slots.*';
        $attributes = ['default_text' => 'Teks Alternatif', 'date_format' => 'Format tanggal'];
        foreach (app(LetterTemplateConfigurationRules::class)->attributes() as $key => $value) {
            if (str_starts_with($key, $prefix)) {
                $attributes['item' . substr($key, strlen($prefix))] = $value;
            }
        }

        return $attributes;
    }
}
