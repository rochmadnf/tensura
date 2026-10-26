<?php

namespace App\Http\Requests;

use App\Services\LetterTemplate\LetterTemplateConfigurationRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLetterTemplateConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(LetterTemplateConfigurationRules $rules): array
    {
        return $rules->rules();
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return app(LetterTemplateConfigurationRules::class)->attributes();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return app(LetterTemplateConfigurationRules::class)->messages();
    }

    /** @return list<callable> */
    public function after(): array
    {
        return app(LetterTemplateConfigurationRules::class)->after($this->all());
    }
}
