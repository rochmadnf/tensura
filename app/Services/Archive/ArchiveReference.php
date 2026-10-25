<?php

namespace App\Services\Archive;

use App\Enums\ArchiveKindEnum;
use App\Models\Archive;
use App\Models\MasterData\ReferenceGroup;
use App\Models\MasterData\ReferenceItem;
use App\Models\MasterData\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ArchiveReference
{
    public function __construct(private ReferenceSnapshot $snapshot) {}

    public function group(ReferenceGroup $group, User $actor): Archive
    {
        return DB::transaction(function () use ($group, $actor): Archive {
            $group = ReferenceGroup::query()->whereKey($group->id)->where('uuid', $group->uuid)->lockForUpdate()->firstOrFail();
            if ($group->templateVariables()->exists()) {
                throw ValidationException::withMessages(['deletion' => 'Grup referensi tidak dapat diarsipkan karena masih dirujuk templat surat, termasuk versi historis atau diarsipkan.']);
            }
            $archive = $this->package($group, $actor, ArchiveKindEnum::PACKAGE, $group->name);
            $archive->records()->create($this->snapshot->snapshot($group));
            $group->items()->reorder()->chunkById(200, function (Collection $items) use ($group, $archive): void {
                foreach ($items as $item) {
                    $item->setRelation('group', $group);
                    $archive->records()->create($this->snapshot->snapshot($item));
                }
            });
            $group->delete();

            return $archive;
        }, 5);
    }

    public function item(ReferenceGroup $group, ReferenceItem $item, User $actor): Archive
    {
        return DB::transaction(function () use ($group, $item, $actor): Archive {
            $group = ReferenceGroup::query()->whereKey($group->id)->where('uuid', $group->uuid)->lockForUpdate()->firstOrFail();
            $item = $group->items()->whereKey($item->id)->where('uuid', $item->uuid)->firstOrFail();
            $item->setRelation('group', $group);
            $archive = $this->package($group, $actor, ArchiveKindEnum::RECORD, $item->content);
            $archive->records()->create($this->snapshot->snapshot($item));
            $item->delete();

            return $archive;
        }, 5);
    }

    private function package(ReferenceGroup $group, User $actor, ArchiveKindEnum $kind, string $label): Archive
    {
        return Archive::create([
            'module' => 'reference',
            'kind' => $kind,
            'entity' => $kind === ArchiveKindEnum::PACKAGE ? ReferenceSnapshot::GROUP : ReferenceSnapshot::ITEM,
            'label' => $label,
            'archived_by' => $actor->id,
            'archived_by_name' => $actor->name,
            'archived_at' => now(),
            'revision' => 1,
            'context' => [
                'parent' => ['entity' => ReferenceSnapshot::GROUP, 'source_key' => (string) $group->id, 'source_uuid' => $group->uuid, 'label' => $group->name],
                'target' => $kind === ArchiveKindEnum::RECORD ? ['entity' => ReferenceSnapshot::GROUP, 'uuid' => $group->uuid] : null,
                'parent_resolved' => $kind === ArchiveKindEnum::RECORD,
            ],
        ]);
    }
}
