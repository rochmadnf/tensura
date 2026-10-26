<?php

namespace App\Http\Resources\MasterData;

use App\Enums\LetterTemplateVersionStatusEnum;
use App\Models\MasterData\LetterTemplateSignerSlot;
use App\Models\MasterData\LetterTemplateVariable;
use App\Models\MasterData\LetterTemplateVersion;
use App\Services\Letter\DocumentFontRegistry;
use App\Services\LetterTemplate\LetterTemplateConfigurationHash;
use App\Services\LetterTemplate\LetterTemplateTestEvidence;
use App\Services\LetterTemplate\LetterTemplateTestFields;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LetterTemplateVersion */
class LetterTemplateTestResource extends JsonResource
{
    /**
     * @return array{
     *   id: string, can_generate: bool, validation_token: string, state: string, test_token: ?string,
     *   tested_at: ?string, expires_at: ?string, confirmed_at: ?string, confirmed_by: ?string, limits: array<string, int>,
     *   variables: list<array{id: string, label: string, type: int, is_required: bool, is_multiple: bool, options: list<array{id: string, label: string}>}>,
     *   signer_slots: list<array{id: string, label: string, options: list<array{id: string, label: string}>}>
     * }
     */
    public function toArray(Request $request): array
    {
        $version = $this->resource;
        $evidence = new LetterTemplateTestEvidence(new LetterTemplateConfigurationHash());
        $fields = new LetterTemplateTestFields();
        $variables = $fields->variables($version);
        $prefixes = $fields->slots($version);

        return [
            'id' => $this->uuid,
            'can_generate' => $evidence->validated($version) && $version->status === LetterTemplateVersionStatusEnum::DRAFT && $version->first_used_at === null,
            'validation_token' => $evidence->validationToken($version),
            'state' => $evidence->state($version), 'test_token' => $evidence->token($version),
            'tested_at' => $version->tested_at?->toIso8601String(), 'expires_at' => $version->test_expires_at?->toIso8601String(),
            'confirmed_at' => $version->visually_confirmed_at?->toIso8601String(), 'confirmed_by' => $version->visually_confirmed_by_name,
            'fonts' => [...app(DocumentFontRegistry::class)->report($version->test_font_requirements['requirements'] ?? []), 'limitations' => $version->test_font_requirements['limitations'] ?? []],
            'limits' => config('letter-templates.test'),
            'variables' => $variables->map(fn(LetterTemplateVariable $variable): array => [
                'id' => $variable->uuid, 'label' => $variable->label, 'type' => $variable->type->value, 'is_required' => $variable->is_required,
                'is_multiple' => $variable->is_multiple,
                'options' => [],
            ])->all(),
            'signer_slots' => $version->signerSlots->filter(fn(LetterTemplateSignerSlot $slot): bool => isset($prefixes[$slot->uuid]))->map(fn(LetterTemplateSignerSlot $slot): array => [
                'id' => $slot->uuid, 'label' => 'Penandatangan Slot ' . $slot->sort_order,
                'options' => [],
            ])->values()->all(),
        ];
    }
}
