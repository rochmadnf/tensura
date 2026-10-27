<?php

namespace App\Http\Requests;

use App\Services\Letter\LetterDraftRules;
use App\Services\Letter\LetterDraftState;
use Illuminate\Validation\Validator;

class UpdateLetterDraftRequest extends StoreLetterRequest
{
    public function rules(LetterDraftRules $rules): array
    {
        $result = parent::rules($rules);
        unset($result['template']);

        return [...$result, ...app(LetterDraftState::class)->identityRules($this->route('letter')), 'reason' => ['nullable', 'string', 'max:2000'], 'revision' => ['required', 'integer', 'between:1,2147483647']];
    }

    public function after(): array
    {
        return [...parent::after(), function (Validator $validator): void {
            $draft = $this->route('letter')->draft;
            if (!$validator->errors()->has('revision') && $draft !== null && $draft->revision !== $this->integer('revision')) {
                $validator->errors()->add('revision', app(LetterDraftState::class)->conflictMessage($draft));
            }
        }];
    }
}
