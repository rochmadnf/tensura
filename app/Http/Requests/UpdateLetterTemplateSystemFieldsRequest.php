<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLetterTemplateSystemFieldsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return ['letter_date_format' => ['required', 'string', 'max:100', 'not_regex:/[\x00-\x1F]/']];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['letter_date_format' => 'Format Tanggal Surat'];
    }
}
