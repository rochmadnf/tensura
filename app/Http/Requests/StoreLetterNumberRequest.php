<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLetterNumberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array{classification_name: string, classification_code: string, page_number: string, sequence_number: string}
     */
    public function attributes(): array
    {
        return [
            'classification_name' => 'Nama Klasifikasi Surat',
            'classification_code' => 'Kode Klasifikasi Surat',
            'page_number' => 'Nomor Halaman',
            'sequence_number' => 'Nomor Urut',
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
            'classification_code' => ['bail', 'required', 'string', 'digits:3', Rule::unique('letter_numbers')],
            'page_number' => ['bail', 'required', 'integer', 'min:1', 'max:99'],
            'sequence_number' => ['bail', 'required', 'integer', 'min:1', 'max:99'],
        ];
    }
}
