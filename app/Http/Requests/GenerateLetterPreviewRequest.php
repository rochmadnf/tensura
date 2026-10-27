<?php

namespace App\Http\Requests;

use App\Services\Letter\LetterDraftState;
use Illuminate\Foundation\Http\FormRequest;

class GenerateLetterPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [...app(LetterDraftState::class)->identityRules($this->route('letter')), 'revision' => ['required', 'integer', 'between:1,2147483647'], 'idempotency_key' => ['required', 'uuid']];
    }

    public function attributes(): array
    {
        return [...app(LetterDraftState::class)->identityRules($this->route('letter')), 'revision' => 'Versi Draft', 'idempotency_key' => 'Kunci Permintaan'];
    }
}
