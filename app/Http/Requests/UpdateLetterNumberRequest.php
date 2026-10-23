<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLetterNumberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'classification_name' => 'Nama Klasifikasi Surat',
            'classification_code' => 'Kode Klasifikasi Surat',
        ];
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'classification_name' => ['bail', 'required', 'string', 'min:3', 'max:100'],
            'classification_code' => [
                'bail',
                'required',
                'string',
                'digits:3',
                Rule::unique('letter_numbers')->ignore($this->route('letterNumber')),
            ],
        ];
    }
}
