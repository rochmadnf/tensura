<?php

namespace App\Services\Archive;

use App\Enums\ArchiveKindEnum;
use App\Exceptions\ArchiveChangedException;
use App\Models\Archive;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class RenameArchivedReferenceGroup
{
    public function __construct(private ReferenceSnapshot $snapshot) {}

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public static function rules(): array
    {
        return ['name' => ['bail', 'required', 'string', 'min:3', 'max:100', Rule::unique('reference_groups', 'name')]];
    }

    /** @return array{archive_uuid: string, group: array{record_uuid: string, uuid: string, name: string, description: ?string}, revision: int} */
    public function rename(Archive $archive, string $name): array
    {
        return DB::transaction(function () use ($archive, $name): array {
            $current = Archive::query()->whereKey($archive->id)->where('uuid', $archive->uuid)->lockForUpdate()->first();
            if ($current === null) {
                throw new ArchiveChangedException();
            }
            $record = $current->records()->where('entity', ReferenceSnapshot::GROUP)->orderBy('id')->lockForUpdate()->first();
            if ($current->module !== 'reference' || $current->kind !== ArchiveKindEnum::PACKAGE
                || $current->entity !== ReferenceSnapshot::GROUP || $record === null
                || !$this->snapshot->supports($record)
                || $record->source_key !== ($current->context['parent']['source_key'] ?? null)
                || $record->source_uuid !== ($current->context['parent']['source_uuid'] ?? null)) {
                throw ValidationException::withMessages(['name' => 'Induk arsip sudah diselesaikan atau snapshot tidak didukung.']);
            }
            $name = trim($name);
            Validator::make(['name' => $name], self::rules())->validate();
            if (!$record->update(['label' => $name, 'payload' => [...$record->payload, 'name' => $name]])) {
                throw new RuntimeException('Perubahan nama grup arsip gagal.');
            }
            if (!$current->update([
                'label' => $name,
                'context' => [...$current->context, 'parent' => [...$current->context['parent'], 'label' => $name]],
                'revision' => $current->revision + 1,
            ])) {
                throw new RuntimeException('Perubahan konteks grup arsip gagal.');
            }

            return [
                'archive_uuid' => $current->uuid,
                'group' => ['record_uuid' => $record->uuid, 'uuid' => $record->source_uuid, 'name' => $name, 'description' => $record->payload['description']],
                'revision' => $current->revision,
            ];
        }, 5);
    }
}
