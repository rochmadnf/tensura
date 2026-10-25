<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ViewLetterTemplateVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['version' => ['sometimes', 'required', 'uuid']];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['version' => 'Versi Templat'];
    }
}
