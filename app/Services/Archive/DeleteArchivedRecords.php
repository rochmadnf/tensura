<?php

namespace App\Services\Archive;

use App\Exceptions\ArchiveChangedException;
use App\Models\Archive;
use App\Models\MasterData\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class DeleteArchivedRecords
{
    public function __construct(private ArchiveConfirmation $confirmation) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{archive_uuid: string, mode: string, counts: array{requested: int, deleted: int, remaining: int}, archive_exists: bool}
     */
    public function delete(Archive $archive, User $actor, string $token, array $input): array
    {
        $selection = ArchiveSelection::normalize($input);
        if ($selection['action'] !== 'delete_forever') {
            throw ValidationException::withMessages(['action' => 'Aksi ini bukan penghapusan permanen arsip.']);
        }

        return DB::transaction(function () use ($archive, $actor, $token, $selection): array {
            $current = Archive::query()->whereKey($archive->id)->where('uuid', $archive->uuid)->lockForUpdate()->first();
            if ($current === null) {
                throw new ArchiveChangedException();
            }
            $records = $current->records()->orderBy('id')->lockForUpdate()->get();
            $plan = $this->confirmation->verify($token, $current, $actor, $selection);
            $selected = ArchiveSelection::records($current, $records, $selection);
            foreach ($selected as $record) {
                if (!$record->delete()) {
                    throw new RuntimeException('Penghapusan permanen record arsip gagal.');
                }
            }
            $remaining = $current->records()->count();
            if ($remaining === 0) {
                if (!$current->delete()) {
                    throw new RuntimeException('Pembersihan paket arsip gagal.');
                }
            } elseif (!$current->update(['revision' => $current->revision + 1])) {
                throw new RuntimeException('Pembaruan paket arsip gagal.');
            }

            return [
                'archive_uuid' => $current->uuid,
                'mode' => $selection['mode'],
                'counts' => ['requested' => $plan['counts']['total'], 'deleted' => $selected->count(), 'remaining' => $remaining],
                'archive_exists' => $remaining > 0,
            ];
        }, 5);
    }
}
