<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DestroyLetterDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['revision' => ['required', 'integer', 'between:1,2147483647'], 'confirmed' => ['required', 'accepted']];
    }
}
