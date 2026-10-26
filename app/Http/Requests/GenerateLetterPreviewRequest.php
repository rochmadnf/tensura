<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateLetterPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['revision' => ['required', 'integer', 'between:1,2147483647'], 'idempotency_key' => ['required', 'uuid']];
    }

    public function attributes(): array
    {
        return ['revision' => 'Versi Draft', 'idempotency_key' => 'Kunci Permintaan'];
    }
}
