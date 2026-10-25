<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PreviewLetterTemplateDateFormatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<string>> */
    public function rules(): array
    {
        return ['date_format' => ['nullable', 'string', 'max:100', 'not_regex:/[\x00-\x1F]/']];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['date_format' => 'Format Tanggal'];
    }
}
