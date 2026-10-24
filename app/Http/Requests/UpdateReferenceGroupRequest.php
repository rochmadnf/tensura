<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReferenceGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array{name: string, description: string} */
    public function attributes(): array
    {
        return ['name' => 'Nama Grup', 'description' => 'Deskripsi'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.string' => ':attribute harus berupa teks.',
            'name.min' => ':attribute minimal :min karakter.',
            'name.max' => ':attribute maksimal :max karakter.',
            'description.string' => ':attribute harus berupa teks.',
            'description.max' => ':attribute maksimal :max karakter.',
        ];
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'min:3', 'max:100', Rule::unique('reference_groups')->ignore($this->route('referenceGroup'))],
            'description' => ['bail', 'nullable', 'string', 'max:255'],
        ];
    }
}
