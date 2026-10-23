<?php

namespace App\Http\Resources\MasterData;

use App\Models\MasterData\JobTitle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin JobTitle */
class JobTitleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{id: string, name: string, group: array{id: int|null, label: string|null}}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'group' => [
                'id' => $this->group?->value,
                'label' => $this->group?->label(),
            ],
        ];
    }
}
