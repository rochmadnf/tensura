<?php

namespace App\Http\Requests;

use App\Services\Letter\LetterDraftState;
use Illuminate\Foundation\Http\FormRequest;

class ChangeLetterDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [...app(LetterDraftState::class)->identityRules($this->route('letter')), 'revision' => ['required', 'integer', 'between:1,2147483647'], ...($this->isMethod('GET') ? [] : ['change_token' => ['required', 'string', 'size:64'], 'confirmed' => ['required', 'accepted']])];
    }
}
