<?php

namespace App\Http\Resources\MasterData;

use App\Models\MasterData\LetterTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LetterTemplate */
class LetterTemplateResource extends JsonResource
{
    /** @return array{id: string, name: string, is_active: bool, active_version: int|null, has_draft: bool, classification: array{id: string, code: string, name: string}|null, draft: array{id: string, number: int, letter_number: string}|null} */
    public function toArray(Request $request): array
    {
        $classification = $this->displayVersion?->letterNumber;

        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'active_version' => $this->activeVersion?->version_number,
            'has_draft' => $this->draftVersion !== null,
            'classification' => $classification === null ? null : [
                'id' => $classification->uuid,
                'code' => $classification->classification_code,
                'name' => $classification->classification_name,
            ],
            'draft' => $this->when($this->relationLoaded('draftVersion') && $this->draftVersion !== null, fn(): array => [
                'id' => $this->draftVersion->uuid,
                'number' => $this->draftVersion->version_number,
                'letter_number' => $this->draftVersion->letterNumber->uuid,
            ], null),
        ];
    }
}
