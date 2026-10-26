<?php

namespace App\Services\Letter;

use App\Enums\LetterTemplateVersionStatusEnum;
use App\Models\Letter;
use App\Models\LetterArtifact;
use App\Models\LetterDraft;
use App\Models\MasterData\LetterTemplate;
use App\Services\LetterTemplate\LetterTemplateConfigurationHash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class LetterPreviewState
{
    public function __construct(private LetterDraftState $state, private LetterTemplateConfigurationHash $configuration, private LetterDraftSnapshot $snapshots) {}

    /** @return array{Letter, LetterDraft} */
    public function lock(Letter $letter, int $revision, bool $editable = true): array
    {
        LetterTemplate::query()->whereKey($letter->letter_template_id)->sharedLock()->firstOrFail();
        $letter = Letter::query()->whereKey($letter->id)->lockForUpdate()->firstOrFail();
        $draft = $letter->draft()->lockForUpdate()->firstOrFail();
        if ($editable) {
            $this->state->assertEditable($letter, $draft, $revision);
        }

        return [$letter, $draft];
    }

    public function assertReady(Letter $letter, LetterDraft $draft): void
    {
        $this->state->assertEditable($letter, $draft, $draft->revision);
        $version = $draft->templateVersion;
        if (!$letter->template->is_active || $letter->template->active_version_id !== $version->id || $version->status !== LetterTemplateVersionStatusEnum::PUBLISHED || !($draft->snapshot['complete'] ?? false)) {
            throw ValidationException::withMessages(['preview' => 'Lengkapi draft dan sesuaikan ke versi templat aktif sebelum memeriksa dokumen.']);
        }
        $this->snapshots->build($version, $draft->form_data, $draft->letter_date, $draft->snapshot);
    }

    public function fingerprint(LetterDraft $draft): string
    {
        return $this->state->token(['draft' => $draft->uuid, 'revision' => $draft->revision, 'snapshot' => $draft->snapshot, 'source' => $draft->templateVersion->source_sha256, 'configuration' => $this->configuration->hash($draft->templateVersion)]);
    }

    public function assertSource(LetterDraft $draft): void
    {
        $version = $draft->templateVersion;
        if (!$version->source_disk || !$version->source_path || !$version->source_sha256) {
            throw ValidationException::withMessages(['preview' => 'DOCX sumber belum tersedia. Hubungi pengelola templat.']);
        }
        $disk = Storage::disk($version->source_disk);
        if (!$disk->exists($version->source_path) || hash_file('sha256', $disk->path($version->source_path)) !== $version->source_sha256) {
            throw ValidationException::withMessages(['preview' => 'DOCX sumber hilang atau berubah. Hubungi pengelola templat.']);
        }
    }

    public function assertCurrent(Letter $letter, LetterArtifact $artifact): void
    {
        $draft = $letter->draft;
        if ($draft === null || $artifact->letter_id !== $letter->id || $artifact->letter_draft_id !== $draft->id || $artifact->draft_revision !== $draft->revision || $artifact->expires_at->lessThanOrEqualTo(now())) {
            throw ValidationException::withMessages(['preview' => 'Preview usang atau kedaluwarsa. Generate dan periksa ulang.']);
        }
        $this->assertReady($letter, $draft);
        $this->assertSource($draft);
        $fonts = app(DocumentFontRegistry::class);
        $fonts->inventory(true);
        $disk = Storage::disk($artifact->disk);
        if (!hash_equals($artifact->state_fingerprint, $this->fingerprint($draft)) || !hash_equals($artifact->font_fingerprint, $fonts->fingerprint($artifact->font_usage['requirements'])) || !$disk->exists($artifact->path) || $disk->size($artifact->path) !== $artifact->size || $artifact->size > config('letters.max_output_bytes') || hash_file('sha256', $disk->path($artifact->path)) !== $artifact->sha256) {
            throw ValidationException::withMessages(['preview' => 'Dokumen, templat, atau font terkait berubah. Generate dan periksa ulang.']);
        }
    }

    public function evidence(LetterArtifact $artifact): string
    {
        return $this->state->token(['artifact' => $artifact->uuid, 'revision' => $artifact->draft_revision, 'hash' => $artifact->sha256, 'state' => $artifact->state_fingerprint, 'fonts' => $artifact->font_fingerprint, 'kind' => 'docx']);
    }
}
