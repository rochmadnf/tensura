<?php

namespace App\Http\Resources\MasterData;

use App\Models\MasterData\ReferenceItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ReferenceItem */
class ReferenceItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{id: string, content: string, sort_order: int, group: ReferenceGroupResource}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'content' => $this->content,
            'sort_order' => $this->sort_order,
            'group' => ReferenceGroupResource::make($this->whenLoaded('group')),
        ];
    }
}
