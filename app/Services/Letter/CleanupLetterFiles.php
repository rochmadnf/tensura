<?php

namespace App\Services\Letter;

use App\Enums\LetterOperationStatusEnum as Status;
use App\Models\Letter;
use App\Models\LetterArtifact;
use App\Models\LetterOperation;
use App\Models\LetterVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CleanupLetterFiles
{
    /** @return array{eligible: int, deleted: int, kept: int, failed: int} */
    public function run(bool $dryRun = false): array
    {
        $result = ['eligible' => 0, 'deleted' => 0, 'kept' => 0, 'failed' => 0];
        $roots = config('letters.paths');
        $this->validateRoots($roots);
        $disks = LetterArtifact::query()->distinct()->pluck('disk')->push(config('letters.disk'))->unique();
        foreach ($disks as $diskName) {
            foreach ($roots as $root) {
                try {
                    foreach (Storage::disk($diskName)->getDriver()->listContents($root, true) as $entry) {
                        if (!$entry->isFile()) {
                            continue;
                        }
                        try {
                            $eligible = $this->clean($diskName, $entry->path(), $root, $dryRun);
                            $result[$eligible ? 'eligible' : 'kept']++;
                            if ($eligible && !$dryRun) {
                                $result['deleted']++;
                            }
                        } catch (Throwable $exception) {
                            report($exception);
                            $result['failed']++;
                        }
                    }
                } catch (Throwable $exception) {
                    report($exception);
                    $result['failed']++;
                }
            }
        }

        return $result;
    }

    private function clean(string $diskName, string $path, string $root, bool $dryRun): bool
    {
        return DB::transaction(function () use ($diskName, $path, $root, $dryRun): bool {
            $references = LetterArtifact::query()->where('disk', $diskName)->where('path', $path)->get();
            $operationUuid = explode('/', substr($path, strlen($root) + 1))[0];
            $operation = Str::isUuid($operationUuid) ? LetterOperation::query()->where('uuid', $operationUuid)->first() : null;
            $letterIds = $references->pluck('letter_id')->when($operation !== null, fn($ids) => $ids->push($operation->letter_id))->unique()->sort()->all();
            Letter::query()->whereIn('id', $letterIds)->orderBy('id')->lockForUpdate()->get(['id']);
            if (LetterOperation::query()->whereIn('letter_id', $letterIds)->whereIn('status', [Status::QUEUED, Status::RUNNING])->exists()) {
                return false;
            }
            $references = LetterArtifact::query()->where('disk', $diskName)->where('path', $path)->lockForUpdate()->get();
            if ($references->contains(fn(LetterArtifact $artifact): bool => $artifact->letter_version_id !== null || $artifact->expires_at === null || $artifact->expires_at->isFuture()) || LetterVersion::query()->whereIn('word_artifact_id', $references->pluck('id'))->exists()) {
                return false;
            }
            $disk = Storage::disk($diskName);
            if (!$disk->exists($path) || ($references->isEmpty() && $disk->lastModified($path) > now()->subHours(config('letters.cleanup.orphan_hours'))->timestamp)) {
                return false;
            }
            if (!$dryRun && !$disk->delete($path)) {
                throw new RuntimeException('Berkas sementara Surat belum dapat dibersihkan.');
            }

            return true;
        }, 1);
    }

    private function validateRoots(array $roots): void
    {
        $protected = [...array_values(config('letter-templates.paths')), config('document-fonts.directory'), config('document-fonts.runtime_directory')];
        foreach ($roots as $name => $root) {
            if ($root === '' || $root !== trim($root, '/') || str_contains($root, '..') || str_contains($root, '\\') || in_array('.', explode('/', $root), true)) {
                throw new RuntimeException('Path cleanup Surat harus relatif dan aman.');
            }
            foreach ([...array_values(array_diff_key($roots, [$name => true])), ...$protected] as $other) {
                if ($root === $other || str_starts_with($root, $other . '/') || str_starts_with($other, $root . '/')) {
                    throw new RuntimeException('Direktori cleanup Surat tidak boleh saling tumpang tindih atau mencakup sumber/font.');
                }
            }
        }
    }
}
