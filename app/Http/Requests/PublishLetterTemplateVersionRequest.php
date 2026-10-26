<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PublishLetterTemplateVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'version_token' => ['bail', 'required', 'string', 'size:64', 'regex:/\A[a-f0-9]{64}\z/D'],
            'test_token' => ['bail', 'required', 'string', 'size:64', 'regex:/\A[a-f0-9]{64}\z/D'],
            'confirmed' => ['required', 'accepted'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['version_token' => 'Identitas Pemeriksaan Versi', 'test_token' => 'Identitas Hasil Uji', 'confirmed' => 'Konfirmasi Publikasi'];
    }
}
