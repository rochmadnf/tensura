<?php

namespace App\Services\Archive;

use App\Enums\ArchiveKindEnum;
use App\Models\Archive;
use App\Models\ArchiveRecord;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ArchiveSelection
{
    public const array RESTORE_MODES = ['parent_only', 'parent_with_children', 'children_all', 'child', 'children_selected'];

    public const array DELETE_MODES = ['package', 'child', 'children_selected'];

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public static function rules(?string $action): array
    {
        return [
            'action' => ['required', Rule::in(['restore', 'delete_forever'])],
            'mode' => ['required', Rule::in($action === 'delete_forever' ? self::DELETE_MODES : self::RESTORE_MODES)],
            'record_uuids' => ['sometimes', 'array', 'list'],
            'record_uuids.*' => ['required', 'string', 'uuid', 'distinct:strict'],
            'target_group_uuid' => ['nullable', 'string', 'uuid', Rule::prohibitedIf($action === 'delete_forever')],
        ];
    }

    /** @param array<string, mixed> $input
     * @return array{action: string, mode: string, record_uuids: list<string>, target_group_uuid: ?string}
     */
    public static function normalize(array $input): array
    {
        $validated = Validator::make($input, self::rules(is_string($input['action'] ?? null) ? $input['action'] : null))->validate();
        $ids = $validated['record_uuids'] ?? [];
        $selected = in_array($validated['mode'], ['child', 'children_selected'], true);
        if ((!$selected && $ids !== []) || ($selected && $ids === []) || ($validated['mode'] === 'child' && count($ids) !== 1)) {
            throw ValidationException::withMessages(['record_uuids' => 'Pilih item sesuai cakupan operasi yang diminta.']);
        }
        sort($ids, SORT_STRING);

        return ['action' => $validated['action'], 'mode' => $validated['mode'], 'record_uuids' => $ids, 'target_group_uuid' => $validated['target_group_uuid'] ?? null];
    }

    /** @param Collection<int, ArchiveRecord> $records
     * @param  array{action: string, mode: string, record_uuids: list<string>, target_group_uuid: ?string}  $selection
     * @return Collection<int, ArchiveRecord>
     */
    public static function records(Archive $archive, Collection $records, array $selection): Collection
    {
        $parentMode = in_array($selection['mode'], ['parent_only', 'parent_with_children'], true);
        if ($parentMode && ($archive->kind !== ArchiveKindEnum::PACKAGE || !$records->contains('entity', ReferenceSnapshot::GROUP))) {
            throw ValidationException::withMessages(['mode' => 'Induk arsip tidak tersedia untuk dipulihkan.']);
        }
        if (in_array($selection['mode'], ['children_all', 'children_selected'], true) && $archive->kind !== ArchiveKindEnum::PACKAGE) {
            throw ValidationException::withMessages(['mode' => 'Mode ini hanya tersedia pada paket arsip.']);
        }
        if ($selection['mode'] === 'package') {
            return $records;
        }
        if ($selection['mode'] === 'parent_only') {
            return $records->where('entity', ReferenceSnapshot::GROUP)->values();
        }
        if ($selection['mode'] === 'parent_with_children') {
            return $records;
        }
        $children = $records->where('entity', ReferenceSnapshot::ITEM);
        if ($selection['mode'] === 'children_all') {
            return $children->values();
        }
        $chosen = $children->whereIn('uuid', $selection['record_uuids'])->values();
        if ($chosen->count() !== count($selection['record_uuids'])) {
            throw ValidationException::withMessages(['record_uuids' => 'Item tidak termasuk dalam paket arsip ini atau sudah diproses.']);
        }

        return $chosen;
    }
}
