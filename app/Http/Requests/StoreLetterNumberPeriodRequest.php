<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLetterNumberPeriodRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'year' => ['bail', 'required', 'integer', 'between:1,9999'],
            'month' => ['bail', 'required', 'integer', 'between:1,12'],
            'mode' => ['bail', 'required', Rule::in(['legacy', 'empty', 'custom'])],
            'last_page_number' => ['exclude_unless:mode,custom', 'bail', 'required', 'integer', 'between:1,2147483647'],
            'last_sequence_number' => ['exclude_unless:mode,custom', 'bail', 'required', 'integer', 'between:1,99'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['year' => 'Tahun', 'month' => 'Bulan', 'mode' => 'Sumber angka awal', 'last_page_number' => 'Halaman terakhir', 'last_sequence_number' => 'Nomor urut terakhir'];
    }
}
