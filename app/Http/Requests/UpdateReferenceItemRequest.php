<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReferenceItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array{content: string} */
    public function attributes(): array
    {
        return ['content' => 'Konten Item'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'content.string' => ':attribute harus berupa teks.',
            'content.max' => ':attribute maksimal :max karakter.',
        ];
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'content' => [
                'bail',
                'required',
                'string',
                'max:255',
                Rule::unique('reference_items', 'content')
                    ->where('reference_group_id', $this->route('referenceGroup')->id)
                    ->ignore($this->route('item')),
            ],
        ];
    }
}
