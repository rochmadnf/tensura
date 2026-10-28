<?php

namespace App\Services\Letter;

use App\Models\Letter;
use App\Models\LetterArtifact;
use App\Models\LetterDraft;
use App\Models\LetterNumber;
use Illuminate\Validation\ValidationException;

class LetterIssuanceState
{
    public function __construct(private LetterPreviewState $previews, private LetterPdfState $pdfs) {}

    /** Lock ordering extends draft management: template → letter → draft → classification → operation. */
    public function lock(Letter $letter, int $revision, bool $editable = true): array
    {
        [$letter, $draft] = $this->previews->lock($letter, $revision, $editable);
        $classification = LetterNumber::query()->whereKey($draft->numberClassification()->id)->lockForUpdate()->firstOrFail();
        if ($draft->base_version_id === null) {
            $draft->templateVersion->setRelation('letterNumber', $classification);
        }

        return [$letter, $draft, $classification];
    }

    /** @param array{artifact: string, fingerprint: string, publication_month: string} $data */
    public function inspected(Letter $letter, LetterDraft $draft, array $data, string $actor): LetterArtifact
    {
        app(LetterDraftState::class)->assertIdentity($draft, $data);
        if ($draft->base_version_id !== null && ($letter->current_version_id !== $draft->base_version_id || trim($draft->reason ?? '') === '')) {
            throw ValidationException::withMessages(['reason' => 'Isi alasan dan pastikan versi resmi asal belum berubah sebelum menerbitkan.']);
        }
        if ($draft->revision !== (int) ($data['revision'] ?? $draft->revision) || $data['publication_month'] !== now()->format('Y-m')) {
            throw ValidationException::withMessages(['issuance' => 'Draft atau bulan penerbitan berubah. Muat ulang status dan konfirmasi tanggal/periode lagi.']);
        }
        $artifact = $letter->artifacts()->where('uuid', $data['artifact'])->whereNull('letter_version_id')->where('kind', 'pdf')->first();
        if ($artifact === null) {
            throw ValidationException::withMessages(['issuance' => 'PDF yang diperiksa tidak ditemukan. Buka dan konfirmasikan PDF terbaru.']);
        }
        $word = $this->pdfs->assertCurrent($letter, $artifact);
        $fingerprint = $this->pdfs->evidence($artifact);
        $check = $artifact->checks()->where('actor_uuid', $actor)->whereNotNull('confirmed_at')->first();
        if (!hash_equals($fingerprint, $data['fingerprint']) || $check === null || !hash_equals($fingerprint, $check->fingerprint)) {
            throw ValidationException::withMessages(['issuance' => 'Anda perlu membuka dan mengonfirmasi pemeriksaan preview terbaru sebelum menerbitkan.']);
        }

        return $word;
    }
}
