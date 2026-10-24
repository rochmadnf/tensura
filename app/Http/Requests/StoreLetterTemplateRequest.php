<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLetterTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array{name: string, letter_number: string} */
    public function attributes(): array
    {
        return ['name' => 'Nama Templat', 'letter_number' => 'Klasifikasi'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.string' => ':attribute harus berupa teks.',
            'name.min' => ':attribute minimal :min karakter.',
            'name.max' => ':attribute maksimal :max karakter.',
            'letter_number.uuid' => ':attribute yang dipilih tidak valid.',
            'letter_number.exists' => ':attribute yang dipilih tidak valid.',
        ];
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'min:3', 'max:100', Rule::unique('letter_templates')],
            'letter_number' => ['bail', 'required', 'uuid', Rule::exists('letter_numbers', 'uuid')],
        ];
    }
}
