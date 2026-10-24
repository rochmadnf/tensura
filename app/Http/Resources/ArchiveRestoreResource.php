<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArchiveRestoreResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return collect($this->resource)->only([
            'archive_uuid', 'mode', 'target', 'counts', 'archive_exists', 'conflicts',
        ])->all();
    }
}
