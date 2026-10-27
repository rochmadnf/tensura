<?php

namespace App\Http\Resources;

use App\Models\LetterVersion;
use App\Services\Letter\DocumentFontRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LetterVersion */
class LetterVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->uuid, 'word_id' => $this->wordArtifact?->uuid, 'version' => $this->version_number, 'number' => $this->number, 'subject' => $this->subject, 'reason' => $this->reason, 'letter_date' => $this->letter_date->toDateString(), 'issued_at' => $this->issued_at->toIso8601String(), 'issued_by' => $this->actor_name, 'resolved' => array_filter($this->snapshot['values'], fn(string $token): bool => in_array(preg_replace('/#\d+$/', '', $token), $this->snapshot['used_tokens'] ?? [], true), ARRAY_FILTER_USE_KEY), 'max_bytes' => config('letters.max_output_bytes'), 'font_report' => [...app(DocumentFontRegistry::class)->report($this->wordArtifact?->font_usage['requirements'] ?? []), 'limitations' => $this->wordArtifact?->font_usage['limitations'] ?? []]];
    }
}
