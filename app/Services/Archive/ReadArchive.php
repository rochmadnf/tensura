<?php

namespace App\Services\Archive;

use App\Models\Archive;
use App\Models\MasterData\ReferenceGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReadArchive
{
    /** @return Builder<Archive> */
    public function query(): Builder
    {
        return Archive::query()->withCount([
            'records',
            'records as items_count' => fn(Builder $query) => $query->where('entity', ReferenceSnapshot::ITEM),
            'records as parents_count' => fn(Builder $query) => $query->where('entity', ReferenceSnapshot::GROUP),
        ]);
    }

    /** @param Collection<int, Archive> $archives */
    public function loadTargets(Collection $archives): void
    {
        $uuids = $archives->where('module', 'reference')->map(fn(Archive $archive): ?string => $this->targetUuid($archive))->filter()->unique()->values();
        $groups = ReferenceGroup::query()->whereIn('uuid', $uuids)->get(['id', 'uuid', 'name'])->keyBy('uuid');
        foreach ($archives as $archive) {
            $archive->setRelation('activeTarget', $archive->module === 'reference' ? $groups->get($this->targetUuid($archive)) : null);
        }
    }

    private function targetUuid(Archive $archive): ?string
    {
        $uuid = $archive->context['target']['uuid'] ?? $archive->context['parent']['source_uuid'] ?? null;

        return is_string($uuid) ? $uuid : null;
    }
}
