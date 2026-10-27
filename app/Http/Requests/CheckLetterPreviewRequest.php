<?php

namespace App\Http\Requests;

use App\Services\Letter\LetterDraftState;
use Illuminate\Foundation\Http\FormRequest;

class CheckLetterPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [...app(LetterDraftState::class)->identityRules($this->route('letter')), 'revision' => ['required', 'integer', 'between:1,2147483647'], 'fingerprint' => ['required', 'string', 'size:64'], 'preview_kind' => ['required', 'in:docx,pdf'], 'confirmed' => [$this->routeIs('ltr.previews.confirm') ? 'accepted' : 'exclude']];
    }
}
