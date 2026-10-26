<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmLetterTemplateTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<string>> */
    public function rules(): array
    {
        return ['test_token' => ['required', 'string', 'size:64'], 'confirmed' => ['required', 'accepted']];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['test_token' => 'Hasil Uji', 'confirmed' => 'Pernyataan Pemeriksaan Visual'];
    }
}
