<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DownloadLetterTemplateTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<string>> */
    public function rules(): array
    {
        return ['test_token' => ['required', 'string', 'size:64'], 'format' => ['sometimes', 'required', 'in:docx,pdf'], 'download' => ['sometimes', 'boolean']];
    }
}
