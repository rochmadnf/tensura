<?php

namespace App\Services\Letter;

use App\Models\Letter;
use App\Models\LetterArtifact;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class LetterPdfState
{
    public function __construct(private LetterPreviewState $previews, private LetterDraftState $tokens, private DocumentFontRegistry $fonts) {}

    /** @return array{Letter, LetterArtifact} */
    public function lock(Letter $letter, LetterArtifact $word): array
    {
        if ($word->letter_version_id === null) {
            [$letter] = $this->previews->lock($letter, $word->draft_revision);
        } else {
            $letter = Letter::query()->whereKey($letter->id)->lockForUpdate()->firstOrFail();
        }
        $word = LetterArtifact::query()->whereKey($word->id)->firstOrFail();
        $this->assertWord($letter, $word);

        return [$letter, $word];
    }

    public function assertWord(Letter $letter, LetterArtifact $word): void
    {
        if ($word->letter_id !== $letter->id || $word->kind !== 'docx') {
            throw ValidationException::withMessages(['pdf' => 'Dokumen Word sumber tidak sesuai surat.']);
        }
        if ($word->letter_version_id === null) {
            $this->previews->assertCurrent($letter, $word);
        } elseif ($word->version?->letter_id !== $letter->id || $word->version->word_artifact_id !== $word->id) {
            throw ValidationException::withMessages(['pdf' => 'Versi Word resmi tidak sesuai.']);
        }
        $this->assertFile($word, config('letters.max_output_bytes'));
    }

    public function assertFile(LetterArtifact $artifact, int $maxBytes): void
    {
        $disk = Storage::disk($artifact->disk);
        if ($artifact->size <= 0 || $artifact->size > $maxBytes || !$disk->exists($artifact->path) || $disk->size($artifact->path) !== $artifact->size || !hash_equals($artifact->sha256, hash_file('sha256', $disk->path($artifact->path)))) {
            throw ValidationException::withMessages(['pdf' => 'Berkas dokumen hilang atau berubah. Hubungi pengelola.']);
        }
    }

    public function profile(LetterArtifact $word): array
    {
        $this->fonts->inventory(true);
        $requirements = $word->font_usage['requirements'];
        $profile = $this->fonts->conversionProfile($requirements);
        $environment = $profile['environment'];
        $profile['fingerprint'] = hash('sha256', json_encode([$profile['fingerprint'], config('letters.pdf.profile_revision'), hash_file('sha256', $environment['FONTCONFIG_FILE']), config('document-fonts.executables.version')], JSON_THROW_ON_ERROR));

        return [...$profile, 'report' => [...$this->fonts->report($requirements), 'limitations' => $word->font_usage['limitations'] ?? []]];
    }

    public function assertCurrent(Letter $letter, LetterArtifact $pdf): LetterArtifact
    {
        $word = $pdf->sourceArtifact;
        if ($pdf->kind !== 'pdf' || $pdf->letter_id !== $letter->id || $word === null || $pdf->letter_version_id !== $word->letter_version_id || $pdf->letter_draft_id !== $word->letter_draft_id || $pdf->state_fingerprint !== $word->state_fingerprint || ($pdf->font_usage['word_hash'] ?? null) !== $word->sha256) {
            throw ValidationException::withMessages(['pdf' => 'PDF tidak sesuai dokumen sumber.']);
        }
        $this->assertWord($letter, $word);
        $this->assertFile($pdf, config('letters.pdf.max_bytes'));
        if ($word->letter_version_id === null && ($pdf->expires_at === null || $pdf->expires_at->lessThanOrEqualTo(now()) || !hash_equals($pdf->font_fingerprint, $this->profile($word)['fingerprint']))) {
            throw ValidationException::withMessages(['pdf' => 'PDF draft usang. Generate preview dan periksa ulang.']);
        }

        return $word;
    }

    public function evidence(LetterArtifact $pdf): string
    {
        return $this->tokens->token(['kind' => 'pdf', 'artifact' => $pdf->uuid, 'hash' => $pdf->sha256, 'word' => $pdf->sourceArtifact->uuid, 'word_hash' => $pdf->sourceArtifact->sha256, 'state' => $pdf->state_fingerprint, 'profile' => $pdf->font_fingerprint]);
    }
}
