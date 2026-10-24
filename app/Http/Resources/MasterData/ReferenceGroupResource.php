<?php

namespace App\Http\Resources\MasterData;

use App\Models\MasterData\ReferenceGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;

/** @mixin ReferenceGroup */
class ReferenceGroupResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{id: string, name: string, description: string|null, items_count: int|MissingValue, items: AnonymousResourceCollection}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'description' => $this->description,
            'items_count' => $this->whenCounted('items'),
            'items' => ReferenceItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
