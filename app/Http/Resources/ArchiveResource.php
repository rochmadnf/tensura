<?php

namespace App\Http\Resources;

use App\Models\Archive;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Archive */
class ArchiveResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $target = $this->getRelation('activeTarget');
        $parent = $this->context['parent'] ?? null;
        $status = $this->module !== 'reference' ? 'unavailable' : ($this->parents_count > 0 ? 'archived' : ($target !== null ? 'active' : 'missing'));

        return [
            'uuid' => $this->uuid,
            'module' => $this->module,
            'kind' => $this->kind->value,
            'entity' => $this->entity,
            'label' => $this->label,
            'parent' => is_array($parent) ? ['uuid' => $parent['source_uuid'] ?? null, 'label' => $parent['label'] ?? null] : null,
            'parent_status' => $status,
            'parent_resolved' => (bool) ($this->context['parent_resolved'] ?? false),
            'target' => $target !== null ? ['uuid' => $target->uuid, 'name' => $target->name] : null,
            'records_count' => (int) $this->records_count,
            'items_count' => (int) $this->items_count,
            'archived_at' => $this->archived_at?->toISOString(),
            'archived_by_name' => $this->archived_by_name,
        ];
    }
}
