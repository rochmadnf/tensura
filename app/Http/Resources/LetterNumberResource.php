<?php

namespace App\Http\Resources;

use App\Models\LetterNumber;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LetterNumber
 */
class LetterNumberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{id: string, name: string, code: string, page_number: ?int, sequence_number: ?int, needs_initialization: bool}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->classification_name,
            'code' => $this->classification_code,
            'page_number' => $this->page_number,
            'sequence_number' => $this->sequence_number,
            'needs_initialization' => $this->resource->needsPeriodInitialization(),
            'code_locked' => $this->when($request->routeIs('ln.edit'), fn(): bool => $this->periods()->whereHas('assignments')->exists()),
        ];
    }
}
