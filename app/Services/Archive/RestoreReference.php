<?php

namespace App\Services\Archive;

use App\Enums\ArchiveKindEnum;
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
        if ($selection['action'] !== 'restore' || !in_array($selection['mode'], ArchiveSelection::PARENT_MODES, true)) {
            throw ValidationException::withMessages(['mode' => 'Mode pemulihan ini belum tersedia.']);
        }
        if ($selection['target_group_uuid'] !== null) {
            throw ValidationException::withMessages(['target_group_uuid' => 'Penggabungan grup belum tersedia pada tahap ini.']);
        }

        try {
            return DB::transaction(function () use ($archive, $actor, $token, $selection): array {
                $current = Archive::query()->whereKey($archive->id)->where('uuid', $archive->uuid)->lockForUpdate()->first();
                if ($current === null) {
                    throw new ArchiveChangedException();
                }
                if ($current->module !== 'reference' || $current->kind !== ArchiveKindEnum::PACKAGE || $current->entity !== ReferenceSnapshot::GROUP) {
                    throw ValidationException::withMessages(['mode' => 'Arsip ini bukan paket grup Reference.']);
                }

                $records = $current->records()->orderBy('id')->lockForUpdate()->get();
                $this->lockActiveRecords($current, $records);
                $plan = $this->confirmation->verify($token, $current, $actor, $selection);
                $parent = $records->firstWhere('uuid', $plan['parent']['record_uuid'] ?? null);
                $parentCandidate = collect($plan['candidates'])->firstWhere('record_uuid', $parent?->uuid);
                if ($parent === null || ($parentCandidate['status'] ?? null) !== 'ready') {
                    throw new ArchiveChangedException();
                }

                $group = new ReferenceGroup();
                $this->insertSnapshot($group, $parent);
                $this->removeRecord($parent);

                $restored = 1;
                $readyIds = collect($plan['candidates'])->where('status', 'ready')->pluck('record_uuid')->all();
                foreach ($records->where('entity', ReferenceSnapshot::ITEM)->whereIn('uuid', $readyIds)->chunk(200) as $chunk) {
                    foreach ($chunk as $record) {
                        $this->insertSnapshot(new ReferenceItem(), $record);
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
                        'parent_resolved' => true,
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
    private function lockActiveRecords(Archive $archive, Collection $records): void
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

    private function insertSnapshot(Model $model, ArchiveRecord $record): void
    {
        $model->timestamps = false;
        $model->setRawAttributes($record->payload);
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
}
