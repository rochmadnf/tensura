<?php

namespace App\Services\LetterTemplate;

use App\Enums\LetterTemplateVersionStatusEnum;
use App\Models\MasterData\LetterTemplate;
use App\Models\MasterData\LetterTemplateVersion;
use App\Models\MasterData\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ManageLetterTemplateVersions
{
    public function __construct(
        private LetterTemplateVersionState $state,
        private LetterTemplateTestEvidence $evidence,
        private LetterTemplatePublicationReadiness $readiness,
        private CopyLetterTemplateVersion $copier,
        private InvalidateLetterTemplateEvidence $invalidation,
        private RecordLetterTemplateAudit $audit,
    ) {}

    public function publish(LetterTemplate $template, LetterTemplateVersion $version, string $versionToken, string $testToken, User $actor): LetterTemplateVersion
    {
        return $this->locked($template, $version, function (LetterTemplate $template, LetterTemplateVersion $version) use ($versionToken, $testToken, $actor): LetterTemplateVersion {
            $before = $this->audit->snapshot($template, $version);
            $this->state->assertCurrent($version, $versionToken);
            $this->readiness->assertReady($version);
            $this->evidence->assertCurrent($version, $testToken);
            $version->fill(['status' => LetterTemplateVersionStatusEnum::PUBLISHED, 'published_at' => now()])->save();
            $template->fill(['active_version_id' => $version->id])->save();
            $this->audit->record($template, $version, $actor, 'version.published', 'Versi ' . $version->version_number . ' diterbitkan sebagai versi aktif untuk surat baru. Status Aktif/Nonaktif templat dipertahankan.', $before);

            return $version;
        });
    }

    public function draft(LetterTemplate $template, LetterTemplateVersion $version, string $versionToken, User $actor): LetterTemplateVersion
    {
        $createdFiles = [];
        try {
            $draft = $this->locked($template, $version, function (LetterTemplate $template, LetterTemplateVersion $version) use ($versionToken, $actor, &$createdFiles): LetterTemplateVersion {
                $before = $this->audit->snapshot($template, $version);
                $this->state->assertCurrent($version, $versionToken);
                if ($template->versions()->where('status', LetterTemplateVersionStatusEnum::DRAFT)->exists()) {
                    throw ValidationException::withMessages(['version' => 'Masih ada draft berjalan. Lanjutkan atau arsipkan draft tersebut terlebih dahulu.']);
                }
                if ($version->status === LetterTemplateVersionStatusEnum::PUBLISHED && $version->first_used_at === null) {
                    if ($template->active_version_id === $version->id) {
                        $template->fill(['active_version_id' => null])->save();
                    }
                    $this->invalidation->invalidate($version);
                    $version->fill(['status' => LetterTemplateVersionStatusEnum::DRAFT, 'published_at' => null])->save();
                    $this->audit->record($template, $version, $actor, 'draft.reopened', 'Versi terbit yang belum digunakan dibuka kembali sebagai draft dengan nomor yang sama; publikasi serta bukti pemeriksaan dibatalkan.', $before);

                    return $version;
                }
                if (!in_array($version->status, [LetterTemplateVersionStatusEnum::PUBLISHED, LetterTemplateVersionStatusEnum::ARCHIVED], true)) {
                    throw ValidationException::withMessages(['version' => 'Draft baru hanya dapat dibuat dari versi terbit atau arsip.']);
                }
                $draft = $this->copier->copy($template, $version, $createdFiles);
                $this->audit->record($template, $draft, $actor, 'draft.copied', 'Draft versi ' . $draft->version_number . ' dibuat dari versi ' . $version->version_number . '; konfigurasi dan DOCX disalin tanpa bukti pemeriksaan.', $before);

                return $draft;
            });
        } catch (Throwable $exception) {
            $this->cleanup($createdFiles);
            throw $exception;
        }
        $this->cleanup(array_values(array_filter($createdFiles, fn(array $file): bool => $file['disk'] !== $draft->source_disk || $file['path'] !== $draft->source_path)));

        return $draft;
    }

    public function archive(LetterTemplate $template, LetterTemplateVersion $version, string $versionToken, User $actor): LetterTemplateVersion
    {
        return $this->locked($template, $version, function (LetterTemplate $template, LetterTemplateVersion $version) use ($versionToken, $actor): LetterTemplateVersion {
            $before = $this->audit->snapshot($template, $version);
            $this->state->assertCurrent($version, $versionToken);
            if ($version->status !== LetterTemplateVersionStatusEnum::DRAFT || $version->first_used_at !== null) {
                throw ValidationException::withMessages(['version' => 'Hanya draft yang belum digunakan dapat diarsipkan.']);
            }
            if ($template->active_version_id === $version->id) {
                $template->fill(['active_version_id' => null])->save();
            }
            $version->fill(['status' => LetterTemplateVersionStatusEnum::ARCHIVED])->save();
            $this->audit->record($template, $version, $actor, 'draft.archived', 'Draft diarsipkan sebagai status versi. Konfigurasi, DOCX, dan riwayat tetap disimpan.', $before);

            return $version;
        });
    }

    public function activation(LetterTemplate $template, LetterTemplateVersion $version, bool $isActive, User $actor): LetterTemplateVersion
    {
        return $this->locked($template, $version, function (LetterTemplate $template, LetterTemplateVersion $version) use ($isActive, $actor): LetterTemplateVersion {
            $before = $this->audit->snapshot($template, $version);
            if ($template->is_active !== $isActive) {
                $template->fill(['is_active' => $isActive])->save();
                $this->audit->record($template, $version, $actor, $isActive ? 'template.activated' : 'template.deactivated', $isActive ? 'Templat diaktifkan; surat baru memakai versi terbit aktif yang tersedia.' : 'Templat dinonaktifkan untuk surat baru tanpa menghapus versi, DOCX, atau riwayat.', $before);
            }

            return $version;
        });
    }

    /** @param Closure(LetterTemplate, LetterTemplateVersion): LetterTemplateVersion $operation */
    private function locked(LetterTemplate $template, LetterTemplateVersion $version, Closure $operation): LetterTemplateVersion
    {
        return DB::transaction(function () use ($template, $version, $operation): LetterTemplateVersion {
            $template = LetterTemplate::query()->whereKey($template->id)->lockForUpdate()->firstOrFail();
            $version = $template->versions()->whereKey($version->id)->lockForUpdate()->firstOrFail();
            $version->setRelation('template', $template);

            return $operation($template, $version);
        }, 5);
    }

    /** @param list<array{disk: string, path: string}> $files */
    private function cleanup(array $files): void
    {
        foreach ($files as $file) {
            try {
                if (!Storage::disk($file['disk'])->delete($file['path'])) {
                    report(new RuntimeException('Berkas salinan draft yang gagal tidak dapat dibersihkan.'));
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }
}
