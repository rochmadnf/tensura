<?php

namespace App\Http\Requests;

use App\Services\Employee\EmployeeDataRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return EmployeeDataRules::rules();
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return EmployeeDataRules::attributes();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return EmployeeDataRules::messages();
    }
}
