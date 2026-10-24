<?php

namespace App\Http\Resources;

use App\Models\ArchiveRecord;
use App\Services\Archive\ArchiveEntityRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use InvalidArgumentException;

/** @mixin ArchiveRecord */
class ArchiveRecordResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        try {
            $supported = resolve(ArchiveEntityRegistry::class)->handler($this->entity)->supports($this->resource);
        } catch (InvalidArgumentException) {
            $supported = false;
        }

        return [
            'uuid' => $this->uuid,
            'entity' => $this->entity,
            'label' => $this->label,
            'source_key' => $this->source_key,
            'source_uuid' => $this->source_uuid,
            'schema_version' => $this->schema_version,
            'supported' => $supported,
            'unsupported_reason' => $supported ? null : 'Entitas, isi snapshot, atau versi skema belum didukung untuk pemulihan.',
            'description' => $supported ? ($this->payload['description'] ?? null) : null,
            'sort_order' => $supported ? ($this->payload['sort_order'] ?? null) : null,
            'original_created_at' => $supported ? ($this->payload['created_at'] ?? null) : null,
            'original_updated_at' => $supported ? ($this->payload['updated_at'] ?? null) : null,
        ];
    }
}
