<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LetterNumberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function attributes()
    {
        return [
            'classification_name' => 'Nama Klasifikasi Surat',
            'classification_code' => 'Kode Klasifikasi Surat',
            'page_number' => 'Nomor Halaman',
            'sequence_number' => 'Nomor Urut',
        ];
    }

    public function rules(): array
    {

        $attributes = [
            'classification_name' => ['bail', 'required', 'string', 'min:3'],
            'classification_code' => ['bail', 'required', 'string', 'digits:3', Rule::unique('letter_numbers')],
            'page_number' => ['bail', 'required', 'integer', 'min:1', 'max:99'],
            'sequence_number' => ['bail', 'required', 'integer', 'min:1', 'max:99'],
        ];

        return $attributes;
    }

    public function whenFulfill()
    {
        $validated = $this->validated();

        return $validated;
    }
}
