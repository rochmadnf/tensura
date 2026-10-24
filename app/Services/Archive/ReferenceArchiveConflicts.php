<?php

namespace App\Services\Archive;

use App\Models\Archive;
use App\Models\ArchiveRecord;
use App\Models\MasterData\ReferenceGroup;
use App\Models\MasterData\ReferenceItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ReferenceArchiveConflicts
{
    public function __construct(private ArchiveEntityRegistry $registry) {}

    /** @param Collection<int, ArchiveRecord> $records
     * @param  Collection<int, ArchiveRecord>  $selected
     * @param  array{action: string, mode: string, record_uuids: list<string>, target_group_uuid: ?string}  $selection
     * @return array{parent: ?array<string, mixed>, target: ?array<string, mixed>, matching_groups: list<array<string, mixed>>, candidates: list<array<string, mixed>>, state: array<string, mixed>}
     */
    public function inspect(Archive $archive, Collection $records, Collection $selected, array $selection): array
    {
        $root = $records->firstWhere('entity', ReferenceSnapshot::GROUP);
        $restoringParent = in_array($selection['mode'], ['parent_only', 'parent_with_children'], true);
        $parentUuid = $archive->context['parent']['source_uuid'] ?? null;
        $targetUuid = $selection['target_group_uuid'] ?? ($restoringParent ? $parentUuid : ($archive->context['target']['uuid'] ?? $parentUuid));
        $target = is_string($targetUuid) ? ReferenceGroup::query()->where('uuid', $targetUuid)->first() : null;
        if ($selection['target_group_uuid'] !== null && $target === null) {
            throw ValidationException::withMessages(['target_group_uuid' => 'Grup tujuan tidak tersedia.']);
        }

        $supported = $records->mapWithKeys(fn(ArchiveRecord $record): array => [$record->uuid => $this->supported($archive, $record)])->all();
        $rootSupported = $root !== null && $supported[$root->uuid];
        $identityGroups = collect();
        $nameGroups = collect();
        if ($rootSupported && $restoringParent) {
            $identityGroups = ReferenceGroup::query()->where(function (Builder $query) use ($root): void {
                $query->whereKey($root->payload['id'])->orWhere('uuid', $root->source_uuid);
            })->orderBy('id')->get();
            $nameGroups = ReferenceGroup::query()->where('name', $root->payload['name'])->orderBy('id')->get();
        }
        $groups = $identityGroups->concat($nameGroups)->when($target !== null, fn(Collection $groups): Collection => $groups->push($target))
            ->unique('id')->sortBy('id')->values();
        $children = $selected->where('entity', ReferenceSnapshot::ITEM)->filter(fn(ArchiveRecord $record): bool => $supported[$record->uuid]);
        $ids = $children->pluck('payload.id')->all();
        $uuids = $children->pluck('source_uuid')->all();
        $groupIds = $groups->pluck('id')->all();
        $activeItems = ReferenceItem::query()->where(function (Builder $query) use ($ids, $uuids, $groupIds): void {
            $query->whereIn('id', $ids)->orWhereIn('uuid', $uuids)->orWhereIn('reference_group_id', $groupIds);
        })->orderBy('id')->get();
        $activeItemIds = $activeItems->keyBy('id');
        $activeItemUuids = $activeItems->keyBy('uuid');
        $duplicates = collect();
        if ($target !== null && $children->isNotEmpty()) {
            $duplicates = ReferenceItem::query()
                ->join('archive_records', function (JoinClause $join): void {
                    $join->on('reference_items.content', '=', 'archive_records.label');
                })
                ->where('archive_records.archive_id', $archive->id)
                ->whereIn('archive_records.uuid', $children->pluck('uuid')->all())
                ->where('reference_items.reference_group_id', $target->id)
                ->orderBy('reference_items.id')
                ->get(['reference_items.id', 'reference_items.uuid', 'reference_items.content', 'archive_records.uuid as record_uuid'])
                ->keyBy('record_uuid');
        }

        $parentReasons = [];
        if ($restoringParent) {
            if (!$rootSupported) {
                $parentReasons[] = $this->reason('unsupported_snapshot', 'Snapshot induk atau versi skemanya belum didukung.');
            } elseif ($root->source_uuid !== $parentUuid || $root->source_key !== ($archive->context['parent']['source_key'] ?? null)) {
                $parentReasons[] = $this->reason('parent_mismatch', 'Identitas induk snapshot tidak sesuai konteks paket arsip.');
            } elseif ($selection['target_group_uuid'] === null) {
                if ($identityGroups->isNotEmpty()) {
                    $parentReasons[] = $this->reason('identity_conflict', 'ID atau UUID induk sudah digunakan data aktif.');
                }
                if ($nameGroups->isNotEmpty()) {
                    $parentReasons[] = $this->reason('name_conflict', 'Nama grup sudah digunakan grup aktif.');
                }
            }
        }
        $parentReady = $restoringParent && $parentReasons === [];
        $candidates = [];
        foreach ($selected as $record) {
            $reasons = [];
            if (!$supported[$record->uuid]) {
                $reasons[] = $this->reason('unsupported_snapshot', 'Entitas, isi snapshot, atau versi skema belum didukung.');
            } elseif ($record->entity === ReferenceSnapshot::GROUP) {
                $reasons = $parentReasons;
            } else {
                if ($record->parent_source_uuid !== $parentUuid
                    || $record->parent_source_key !== ($archive->context['parent']['source_key'] ?? null)) {
                    $reasons[] = $this->reason('parent_mismatch', 'Hubungan induk snapshot tidak sesuai paket arsip.');
                }
                if ($restoringParent && !$parentReady) {
                    $reasons[] = $this->reason('parent_blocked', 'Selesaikan konflik induk sebelum memulihkan children.');
                } elseif (!$restoringParent && $target === null) {
                    $reasons[] = $this->reason('parent_missing', 'Pulihkan induk terlebih dahulu atau tentukan grup aktif tujuan.');
                }
                if ($activeItemIds->has($record->payload['id']) || $activeItemUuids->has($record->source_uuid)) {
                    $reasons[] = $this->reason('identity_conflict', 'ID atau UUID item sudah digunakan data aktif.');
                }
                if ((!$restoringParent || $selection['target_group_uuid'] !== null) && $duplicates->has($record->uuid)) {
                    $reasons[] = $this->reason('content_conflict', 'Konten item sudah tersedia dalam grup tujuan.');
                }
            }
            $candidates[] = [
                'record_uuid' => $record->uuid,
                'entity' => $record->entity,
                'label' => $record->label,
                'operation' => $record->entity === ReferenceSnapshot::GROUP && $selection['target_group_uuid'] !== null ? 'merge' : 'restore',
                'status' => $reasons === [] ? 'ready' : 'conflict',
                'conflicts' => $reasons,
            ];
        }

        return [
            'parent' => $rootSupported ? [
                'record_uuid' => $root->uuid, 'uuid' => $root->source_uuid,
                'name' => $root->payload['name'], 'description' => $root->payload['description'],
                'items' => $records->where('entity', ReferenceSnapshot::ITEM)->map(
                    fn(ArchiveRecord $record): array => ['record_uuid' => $record->uuid, 'label' => $record->label],
                )->values()->all(),
            ] : null,
            'target' => $target !== null ? $this->groupData($target, $activeItems) : null,
            'matching_groups' => $groups->map(fn(ReferenceGroup $group): array => $this->groupData($group, $activeItems))->all(),
            'candidates' => $candidates,
            'state' => [
                'groups' => $groups->map(fn(ReferenceGroup $group): array => $group->getAttributes())->all(),
                'items' => $activeItems->map(fn(ReferenceItem $item): array => $item->getAttributes())->all(),
                'duplicates' => $duplicates->map(fn(ReferenceItem $item): array => $item->getAttributes())->sortKeys()->all(),
            ],
        ];
    }

    private function supported(Archive $archive, ArchiveRecord $record): bool
    {
        if ($archive->module !== 'reference' || !in_array($archive->entity, [ReferenceSnapshot::GROUP, ReferenceSnapshot::ITEM], true)) {
            return false;
        }
        try {
            return $this->registry->handler($record->entity)->supports($record);
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /** @return array{code: string, message: string} */
    private function reason(string $code, string $message): array
    {
        return ['code' => $code, 'message' => $message];
    }

    /** @param Collection<int, ReferenceItem> $items
     * @return array{uuid: string, name: string, description: ?string, items: list<array{uuid: string, content: string, sort_order: int}>}
     */
    private function groupData(ReferenceGroup $group, Collection $items): array
    {
        return [
            'uuid' => $group->uuid,
            'name' => $group->name,
            'description' => $group->description,
            'items' => $items->where('reference_group_id', $group->id)->sortBy(['sort_order', 'id'])->map(
                fn(ReferenceItem $item): array => ['uuid' => $item->uuid, 'content' => $item->content, 'sort_order' => $item->sort_order],
            )->values()->all(),
        ];
    }
}
