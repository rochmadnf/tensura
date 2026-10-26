<?php

namespace App\Http\Requests;

use App\Models\MasterData\JobTitle;
use App\Services\Employee\EmployeeImportTemplate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ImportEmployeeRequest extends FormRequest
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
            'file' => ['required', 'file', 'mimes:xlsx', 'extensions:xlsx', 'max:2048'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file.required' => 'Pilih file Excel yang akan diimpor.',
            'file.file' => 'File gagal diunggah. Silakan pilih ulang file.',
            'file.mimes' => 'Gunakan file Excel berformat .xlsx.',
            'file.extensions' => 'Gunakan file Excel berformat .xlsx.',
            'file.max' => 'Ukuran file maksimal 2 MB.',
        ];
    }

    /** @return list<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (!JobTitle::query()->exists()) {
                $validator->errors()->add('file', EmployeeImportTemplate::JOB_TITLE_REQUIRED);
            }
        }];
    }
}
