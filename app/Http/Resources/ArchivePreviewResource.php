<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArchivePreviewResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return collect($this->resource)->only([
            'archive_uuid', 'action', 'mode', 'parent', 'target', 'matching_groups',
            'candidates', 'conflicts', 'counts', 'can_confirm', 'confirmation_token',
        ])->all();
    }
}
