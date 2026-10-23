<?php

namespace App\Http\Resources\MasterData;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReferenceGroupResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'description' => $this->description,
            // jika permintaan dari index halaman, tidak perlu ada items, masukkan ketika masuk create / edit
            'items' => ReferenceItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
