<?php

namespace App\Http\Requests;

use App\Enums\JobTitleGroupEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'source' => ['required', Rule::in(['classifications', 'job-titles', 'reference-groups', 'employees', 'variable-options', 'signer-candidates'])],
            'groups' => ['sometimes', 'array', 'max:20'],
            'groups.*' => ['integer', Rule::enum(JobTitleGroupEnum::class)],
            'q' => ['nullable', 'string', 'max:255'],
            'context' => ['nullable', 'uuid', 'required_if:source,variable-options,signer-candidates'],
            'selected' => ['sometimes', 'array', 'max:100'],
            'selected.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
