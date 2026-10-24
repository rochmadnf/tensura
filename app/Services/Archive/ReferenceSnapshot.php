<?php

namespace App\Services\Archive;

use App\Models\ArchiveRecord;
use App\Models\MasterData\ReferenceGroup;
use App\Models\MasterData\ReferenceItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class ReferenceSnapshot implements SnapshotHandler
{
    public const string GROUP = 'reference_group';

    public const string ITEM = 'reference_item';

    public const int VERSION = 1;

    /** @return array{entity: string, source_key: string, source_uuid: string, parent_entity: ?string, parent_source_key: ?string, parent_source_uuid: ?string, label: string, schema_version: int, payload: array<string, mixed>} */
    public function snapshot(Model $model): array
    {
        if (!$model instanceof ReferenceGroup && !$model instanceof ReferenceItem) {
            throw new InvalidArgumentException('Model tidak didukung oleh snapshot Reference.');
        }
        $isGroup = $model instanceof ReferenceGroup;
        $columns = $isGroup
            ? ['id', 'uuid', 'name', 'description', 'created_at', 'updated_at']
            : ['id', 'uuid', 'reference_group_id', 'content', 'sort_order', 'created_at', 'updated_at'];
        $parent = $isGroup ? null : $model->group;

        return [
            'entity' => $isGroup ? self::GROUP : self::ITEM,
            'source_key' => (string) $model->getKey(),
            'source_uuid' => $model->uuid,
            'parent_entity' => $isGroup ? null : self::GROUP,
            'parent_source_key' => $parent ? (string) $parent->getKey() : null,
            'parent_source_uuid' => $parent?->uuid,
            'label' => $isGroup ? $model->name : $model->content,
            'schema_version' => self::VERSION,
            'payload' => collect($columns)->mapWithKeys(fn(string $column): array => [$column => $model->getRawOriginal($column)])->all(),
        ];
    }

    public function supports(ArchiveRecord $record): bool
    {
        if ($record->schema_version !== self::VERSION || !in_array($record->entity, [self::GROUP, self::ITEM], true)) {
            return false;
        }
        $payload = $record->payload;
        $rules = [
            'id' => ['required', 'integer', 'min:1'],
            'uuid' => ['required', 'uuid'],
            'created_at' => ['present', 'nullable', 'date'],
            'updated_at' => ['present', 'nullable', 'date'],
        ];
        $rules += $record->entity === self::GROUP
            ? ['name' => ['required', 'string', 'min:3', 'max:100'], 'description' => ['present', 'nullable', 'string', 'max:255']]
            : ['reference_group_id' => ['required', 'integer', 'min:1'], 'content' => ['required', 'string', 'max:255'], 'sort_order' => ['required', 'integer', 'min:0', 'max:65535']];
        if (!is_array($payload) || Validator::make($payload, $rules)->fails()) {
            return false;
        }
        if (array_diff(array_keys($payload), array_keys($rules)) !== []
            || (string) $payload['id'] !== $record->source_key
            || $payload['uuid'] !== $record->source_uuid) {
            return false;
        }

        return $record->entity === self::GROUP
            ? $record->parent_entity === null && $record->parent_source_key === null && $record->parent_source_uuid === null
            : $record->parent_entity === self::GROUP
                && (string) $payload['reference_group_id'] === $record->parent_source_key
                && is_string($record->parent_source_uuid);
    }
}
