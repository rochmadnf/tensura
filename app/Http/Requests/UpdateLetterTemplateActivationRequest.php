<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLetterTemplateActivationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['is_active' => ['required', 'boolean'], 'confirmed' => ['required', 'accepted']];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['is_active' => 'Status Aktif', 'confirmed' => 'Konfirmasi Perubahan Status'];
    }
}
