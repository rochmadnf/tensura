<?php

namespace App\Http\Resources\MasterData;

use App\Models\MasterData\LetterTemplateVersion;
use App\Services\LetterTemplate\LetterTemplateConfigurationHash;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LetterTemplateVersion */
class LetterTemplateDocumentResource extends JsonResource
{
    /** @return array{id: string, source: array{name: string, size: int}|null, state: string, validated_at: ?string, errors: list<string>, placeholders: list<string>} */
    public function toArray(Request $request): array
    {
        $state = $this->validation_result === null ? 'pending' : ($this->validation_result['valid'] ? 'valid' : 'invalid');
        if ($state === 'valid' && ($this->validated_source_sha256 !== $this->source_sha256 || $this->validated_configuration_sha256 !== (new LetterTemplateConfigurationHash())->hash($this->resource))) {
            $state = 'stale';
        }

        return [
            'id' => $this->uuid,
            'letter_date_format' => $this->letter_date_format ?? 'd-m-Y',
            'source' => $this->source_path === null ? null : ['name' => $this->source_original_name, 'size' => $this->source_size],
            'state' => $state, 'validated_at' => $this->validated_at?->toIso8601String(),
            'errors' => $this->validation_result['errors'] ?? [],
            'placeholders' => $this->validation_result['placeholders'] ?? [],
        ];
    }
}
