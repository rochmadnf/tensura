<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LetterLifecycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['state_token' => ['required', 'string', 'size:64'], 'base_version' => ['required', 'uuid'], 'confirmed' => ['accepted'], 'reason' => [$this->routeIs('ltr.cancel') ? 'required' : 'nullable', 'string', 'max:2000', 'regex:/\S/u']];
    }
}
