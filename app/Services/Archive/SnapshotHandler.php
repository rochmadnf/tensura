<?php

namespace App\Services\Archive;

use App\Models\ArchiveRecord;
use Illuminate\Database\Eloquent\Model;

interface SnapshotHandler
{
    /** @return array{entity: string, source_key: string, source_uuid: string, parent_entity: ?string, parent_source_key: ?string, parent_source_uuid: ?string, label: string, schema_version: int, payload: array<string, mixed>} */
    public function snapshot(Model $model): array;

    public function supports(ArchiveRecord $record): bool;
}
