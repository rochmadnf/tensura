<?php

namespace App\Services\Archive;

use App\Exceptions\ArchiveChangedException;
use App\Models\Archive;
use App\Models\ArchiveRecord;
use App\Models\MasterData\ReferenceGroup;
use App\Models\MasterData\ReferenceItem;
use App\Models\MasterData\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class RestoreReference
{
    public function __construct(private ArchiveConfirmation $confirmation, private ReferenceSnapshot $snapshot) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{archive_uuid: string, mode: string, target: array{uuid: string, name: string}, counts: array{requested: int, restored: int, conflicted: int, remaining: int}, archive_exists: bool, conflicts: list<array<string, mixed>>}
     */
    public function restore(Archive $archive, User $actor, string $token, array $input): array
    {
        $selection = ArchiveSelection::normalize($input);
        if ($selection['action'] !== 'restore') {
            throw ValidationException::withMessages(['action' => 'Aksi ini bukan pemulihan arsip.']);
        }

        try {
            return DB::transaction(function () use ($archive, $actor, $token, $selection): array {
                $current = Archive::query()->whereKey($archive->id)->where('uuid', $archive->uuid)->lockForUpdate()->first();
                if ($current === null) {
                    throw new ArchiveChangedException();
                }
                if ($current->module !== 'reference' || !in_array($current->entity, [ReferenceSnapshot::GROUP, ReferenceSnapshot::ITEM], true)) {
                    throw ValidationException::withMessages(['mode' => 'Jenis arsip Reference ini belum didukung.']);
                }

                $records = $current->records()->orderBy('id')->lockForUpdate()->get();
                $this->lockActiveRecords($current, $records, $selection['target_group_uuid']);
                $plan = $this->confirmation->verify($token, $current, $actor, $selection);
                $restoringParent = in_array($selection['mode'], ArchiveSelection::PARENT_MODES, true);
                $append = !$restoringParent || $selection['target_group_uuid'] !== null;
                $restored = 0;
                if ($restoringParent) {
                    $parent = $records->firstWhere('uuid', $plan['parent']['record_uuid'] ?? null);
                    $parentCandidate = collect($plan['candidates'])->firstWhere('record_uuid', $parent?->uuid);
                    if ($parent === null || ($parentCandidate['status'] ?? null) !== 'ready') {
                        throw new ArchiveChangedException();
                    }
                    if ($selection['target_group_uuid'] === null) {
                        $group = new ReferenceGroup();
                        $this->insertSnapshot($group, $parent);
                    } else {
                        $group = $this->activeTarget($plan);
                    }
                    $this->removeRecord($parent);
                    $restored++;
                } else {
                    $group = $this->activeTarget($plan);
                }

                $nextOrder = $append ? (int) $group->items()->max('sort_order') : 0;
                $readyIds = collect($plan['candidates'])->where('status', 'ready')->pluck('record_uuid')->all();
                $readyChildren = $records->where('entity', ReferenceSnapshot::ITEM)->whereIn('uuid', $readyIds)
                    ->sortBy(['payload.sort_order', 'payload.id']);
                foreach ($readyChildren->chunk(200) as $chunk) {
                    foreach ($chunk as $record) {
                        $overrides = ['reference_group_id' => $group->id];
                        if ($append) {
                            $overrides['sort_order'] = ++$nextOrder;
                        }
                        $this->insertSnapshot(new ReferenceItem(), $record, $overrides);
                        $this->removeRecord($record);
                        $restored++;
                    }
                }

                $remaining = $current->records()->count();
                if ($remaining === 0) {
                    if (!$current->delete()) {
                        throw new RuntimeException('Pembersihan paket arsip gagal.');
                    }
                } elseif (!$current->update([
                    'context' => [
                        ...$current->context,
                        'target' => ['entity' => ReferenceSnapshot::GROUP, 'uuid' => $group->uuid],
                        'parent_resolved' => $restoringParent || ($current->context['parent_resolved'] ?? false),
                    ],
                    'revision' => $current->revision + 1,
                ])) {
                    throw new RuntimeException('Penyimpanan konteks pemulihan gagal.');
                }

                return [
                    'archive_uuid' => $current->uuid,
                    'mode' => $selection['mode'],
                    'target' => ['uuid' => $group->uuid, 'name' => $group->name],
                    'counts' => ['requested' => $plan['counts']['total'], 'restored' => $restored, 'conflicted' => $plan['counts']['conflicted'], 'remaining' => $remaining],
                    'archive_exists' => $remaining > 0,
                    'conflicts' => $plan['conflicts'],
                ];
            }, 5);
        } catch (UniqueConstraintViolationException) {
            throw new ArchiveChangedException();
        }
    }

    /** @param Collection<int, ArchiveRecord> $records */
    private function lockActiveRecords(Archive $archive, Collection $records, ?string $targetUuid): void
    {
        $supported = $records->filter(fn(ArchiveRecord $record): bool => $this->snapshot->supports($record));
        $parent = $supported->firstWhere('entity', ReferenceSnapshot::GROUP);
        $children = $supported->where('entity', ReferenceSnapshot::ITEM);
        $ids = $children->pluck('payload.id')->all();
        $uuids = $children->pluck('source_uuid')->all();
        $parentUuids = array_values(array_filter([
            $archive->context['parent']['source_uuid'] ?? null,
            $archive->context['target']['uuid'] ?? null,
            $parent?->source_uuid,
            $targetUuid,
        ], fn(mixed $uuid): bool => is_string($uuid) && Str::isUuid($uuid)));

        $groups = ReferenceGroup::query()->where(function (Builder $query) use ($parent, $parentUuids, $ids, $uuids): void {
            $query->whereIn('uuid', $parentUuids);
            if ($parent !== null) {
                $query->orWhereKey($parent->payload['id'])->orWhere('name', $parent->payload['name']);
            }
            $query->orWhereHas('items', function (Builder $items) use ($ids, $uuids): void {
                $items->whereIn('reference_items.id', $ids)->orWhereIn('reference_items.uuid', $uuids);
            });
        })->orderBy('id')->lockForUpdate()->get(['id']);

        ReferenceItem::query()->where(function (Builder $query) use ($groups, $ids, $uuids): void {
            $query->whereIn('id', $ids)->orWhereIn('uuid', $uuids)->orWhereIn('reference_group_id', $groups->pluck('id')->all());
        })->orderBy('id')->lockForUpdate()->get(['id']);
    }

    /** @param array{reference_group_id?: int, sort_order?: int} $overrides */
    private function insertSnapshot(Model $model, ArchiveRecord $record, array $overrides = []): void
    {
        $model->timestamps = false;
        $model->setRawAttributes([...$record->payload, ...$overrides]);
        if (!$model->save()) {
            throw new RuntimeException('Penyimpanan data pemulihan gagal.');
        }
    }

    private function removeRecord(ArchiveRecord $record): void
    {
        if (!$record->delete()) {
            throw new RuntimeException('Pembersihan record arsip gagal.');
        }
    }

    /** @param array<string, mixed> $plan */
    private function activeTarget(array $plan): ReferenceGroup
    {
        $group = ReferenceGroup::query()->where('uuid', $plan['target']['uuid'] ?? null)->first();
        if ($group === null) {
            throw new ArchiveChangedException();
        }

        return $group;
    }
}
