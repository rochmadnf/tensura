<?php

namespace App\Services\LetterTemplate;

use App\Models\MasterData\LetterTemplate;
use App\Models\MasterData\LetterTemplateVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class CleanupLetterTemplateFiles
{
    /** @return array{eligible: int, deleted: int, kept: int, failed: int} */
    public function run(bool $dryRun = false): array
    {
        $result = ['eligible' => 0, 'deleted' => 0, 'kept' => 0, 'failed' => 0];
        $roots = config('letter-templates.paths');
        $this->validateRoots($roots);
        $disks = LetterTemplateVersion::query()->whereNotNull('test_disk')->distinct()->pluck('test_disk')->push(config('letter-templates.disk'))->unique();
        foreach ($disks as $diskName) {
            foreach (['previews', 'temporary'] as $kind) {
                try {
                    $disk = Storage::disk($diskName);
                    foreach ($disk->getDriver()->listContents($roots[$kind], true) as $entry) {
                        if (!$entry->isFile()) {
                            continue;
                        }
                        try {
                            $eligible = $this->clean($diskName, $entry->path(), $kind, $dryRun);
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

    private function clean(string $diskName, string $path, string $kind, bool $dryRun): bool
    {
        return DB::transaction(function () use ($diskName, $path, $kind, $dryRun): bool {
            $references = fn(Builder $query): Builder => $query->where('test_disk', $diskName)->where('test_path', $path);
            $templateIds = LetterTemplateVersion::query()->where($references)->pluck('letter_template_id')->unique()->all();
            LetterTemplate::query()->whereIn('id', $templateIds)->orderBy('id')->lockForUpdate()->get(['id']);
            $versions = LetterTemplateVersion::query()->where($references)->orderBy('id')->lockForUpdate()->get();
            if (LetterTemplateVersion::query()->where('source_disk', $diskName)->where('source_path', $path)->exists()
                || $versions->contains(fn(LetterTemplateVersion $version): bool => $version->test_expires_at === null || $version->test_expires_at->isFuture())) {
                return false;
            }
            $disk = Storage::disk($diskName);
            if (!$disk->exists($path)) {
                return false;
            }
            $hours = config('letter-templates.cleanup.' . ($kind === 'temporary' ? 'temporary_hours' : 'orphan_hours'));
            if ($versions->isEmpty() && $disk->lastModified($path) > now()->subHours($hours)->timestamp) {
                return false;
            }
            if (!$dryRun && !$disk->delete($path)) {
                throw new RuntimeException('Cleanup templat surat gagal menghapus berkas pada disk ' . $diskName . ': ' . $path);
            }

            return true;
        }, 5);
    }

    /** @param array{sources: string, previews: string, temporary: string} $roots */
    private function validateRoots(array $roots): void
    {
        foreach ($roots as $root) {
            if ($root === '' || in_array('.', explode('/', $root), true) || $root !== trim($root, '/') || str_contains($root, '..') || str_contains($root, '\\')) {
                throw new RuntimeException('Direktori cleanup templat surat harus berupa path relatif yang aman.');
            }
        }
        foreach ($roots as $name => $root) {
            foreach ($roots as $otherName => $other) {
                if ($name !== $otherName && ($root === $other || str_starts_with($root, $other . '/'))) {
                    throw new RuntimeException('Direktori sumber, hasil uji, dan sementara tidak boleh saling tumpang tindih.');
                }
            }
        }
    }
}
