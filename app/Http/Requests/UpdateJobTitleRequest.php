<?php

namespace App\Http\Requests;

use App\Enums\JobTitleGroupEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateJobTitleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array{name: string, group: string} */
    public function attributes(): array
    {
        return [
            'name' => 'Nama Jabatan',
            'group' => 'Kelompok Jabatan',
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
            'name' => [
                'bail',
                'required',
                'string',
                'min:3',
                'max:100',
                Rule::unique('job_titles')->ignore($this->route('jobTitle')),
            ],
            'group' => ['bail', 'required', Rule::enum(JobTitleGroupEnum::class)],
        ];
    }
}
