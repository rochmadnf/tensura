<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ExportEmployeeRequest extends FormRequest
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
            'scope' => ['sometimes', 'required', 'string', 'in:all,filtered'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['scope.in' => 'Pilih seluruh pegawai atau hasil pencarian/filter aktif.'];
    }
}
