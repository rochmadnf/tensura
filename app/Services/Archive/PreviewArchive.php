<?php

namespace App\Services\Archive;

use App\Models\Archive;
use App\Models\ArchiveRecord;

class PreviewArchive
{
    public function __construct(private ReferenceArchiveConflicts $conflicts) {}

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function build(Archive $archive, array $input): array
    {
        $selection = ArchiveSelection::normalize($input);
        $records = $archive->records()->orderBy('id')->get();
        $selected = ArchiveSelection::records($archive, $records, $selection);
        if ($selection['action'] === 'restore') {
            $inspection = $this->conflicts->inspect($archive, $records, $selected, $selection);
        } else {
            $inspection = [
                'parent' => null, 'target' => null, 'matching_groups' => [], 'state' => [],
                'candidates' => $selected->map(fn(ArchiveRecord $record): array => [
                    'record_uuid' => $record->uuid, 'entity' => $record->entity, 'label' => $record->label,
                    'operation' => 'delete_forever', 'status' => 'ready', 'conflicts' => [],
                ])->all(),
            ];
        }
        $candidates = $inspection['candidates'];
        $ready = count(array_filter($candidates, fn(array $candidate): bool => $candidate['status'] === 'ready'));
        $state = [
            'archive' => [...$archive->getAttributes(), 'context' => $archive->context],
            'records' => $records->map(fn(ArchiveRecord $record): array => [...$record->getAttributes(), 'payload' => $record->payload])->all(),
            'active' => $inspection['state'],
        ];

        return [
            'archive_uuid' => $archive->uuid,
            'action' => $selection['action'],
            'mode' => $selection['mode'],
            'selection' => $selection,
            'fingerprint' => hash('sha256', self::canonicalJson($state)),
            'parent' => $inspection['parent'],
            'target' => $inspection['target'],
            'matching_groups' => $inspection['matching_groups'],
            'candidates' => $candidates,
            'conflicts' => array_values(array_filter($candidates, fn(array $candidate): bool => $candidate['status'] === 'conflict')),
            'counts' => ['total' => count($candidates), 'ready' => $ready, 'conflicted' => count($candidates) - $ready],
            'can_confirm' => $ready > 0,
        ];
    }

    public static function canonicalJson(mixed $value): string
    {
        return json_encode(self::canonicalize($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    }

    private static function canonicalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return array_map(fn(mixed $item): mixed => self::canonicalize($item), $value);
    }
}
