<?php

namespace Tests;

use App\Enums\LetterOperationStatusEnum;
use App\Models\LetterArtifact;
use App\Models\LetterOperation;
use App\Services\Letter\LetterPdfState;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait CreatesPdfArtifact
{
    protected function pdfArtifact(LetterArtifact $word): LetterArtifact
    {
        $contents = "%PDF-1.7\n1 0 obj <</Type /Catalog>> endobj\n%%EOF\n";
        $path = 'letters/pdfs/' . Str::uuid() . '.pdf';
        Storage::disk($word->disk)->put($path, $contents);
        $profile = app(LetterPdfState::class)->profile($word);

        $operation = LetterOperation::factory()->create(['letter_id' => $word->letter_id, 'letter_draft_id' => $word->letter_draft_id, 'draft_revision' => $word->draft_revision, 'kind' => 'pdf', 'source_artifact_id' => $word->id, 'status' => LetterOperationStatusEnum::SUCCEEDED]);

        return LetterArtifact::factory()->create([
            'letter_id' => $word->letter_id, 'letter_draft_id' => $word->letter_draft_id,
            'letter_version_id' => $word->letter_version_id, 'letter_operation_id' => $operation->id,
            'source_artifact_id' => $word->id, 'kind' => 'pdf', 'draft_revision' => $word->draft_revision,
            'state_fingerprint' => $word->state_fingerprint, 'disk' => $word->disk, 'path' => $path,
            'sha256' => hash('sha256', $contents), 'size' => strlen($contents),
            'font_fingerprint' => $profile['fingerprint'],
            'font_usage' => [...$word->font_usage, 'word_hash' => $word->sha256], 'expires_at' => $word->expires_at,
        ]);
    }
}
