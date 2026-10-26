<?php

namespace App\Services\Letter;

use App\Enums\LetterOperationStatusEnum as Status;
use App\Models\LetterArtifact;
use App\Models\LetterOperation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class RunLetterPdf
{
    public function __construct(private LetterPdfState $state, private ConvertLetterPdf $converter, private LetterOperationEvents $events) {}

    public function run(string $uuid): void
    {
        $operation = DB::transaction(function () use ($uuid): ?LetterOperation {
            $candidate = LetterOperation::query()->where('uuid', $uuid)->where('kind', 'pdf')->first();
            if ($candidate === null || $candidate->status->terminal() || $candidate->lease_expires_at?->isFuture()) {
                return null;
            }
            $word = LetterArtifact::query()->find($candidate->source_artifact_id);
            try {
                if ($word === null) {
                    throw ValidationException::withMessages(['pdf' => 'Word sumber tidak tersedia.']);
                }
                $this->state->lock($candidate->letter, $word);
                $error = null;
            } catch (ValidationException $exception) {
                $error = $exception;
            }
            $current = LetterOperation::query()->whereKey($candidate->id)->lockForUpdate()->firstOrFail();
            if ($current->status->terminal() || $current->lease_expires_at?->isFuture()) {
                return null;
            }
            if ($error !== null || $current->attempts >= config('letters.max_attempts')) {
                $this->events->transition($current, $error !== null ? Status::STALE : Status::FAILED, $error !== null ? 'Word sumber usang atau tidak tersedia. Buat preview terbaru.' : 'Konversi PDF terhenti. Silakan coba lagi.');

                return null;
            }
            $this->events->transition($current, Status::RUNNING, null, ['attempts' => $current->attempts + 1, 'lease_token' => (string) Str::uuid(), 'lease_expires_at' => now()->addSeconds(config('letters.lease_seconds'))]);

            return $current;
        }, 5);
        if ($operation === null) {
            return;
        }
        $lease = $operation->lease_token;
        $disk = Storage::disk(config('letters.disk'));
        $destination = config('letters.paths.pdfs') . '/' . $operation->uuid . '/' . $lease . '.pdf';
        try {
            $word = LetterArtifact::query()->findOrFail($operation->source_artifact_id);
            if (!hash_equals($operation->payload['word_hash'], $word->sha256)) {
                throw ValidationException::withMessages(['pdf' => 'Word sumber berubah.']);
            }
            $profile = $this->state->profile($word);
            $output = $this->converter->convert($operation, $word, $lease, $profile);
            DB::transaction(function () use ($operation, $word, $lease, $profile, $output, $destination, $disk): void {
                [$letter, $word] = $this->state->lock($operation->letter, $word);
                $current = LetterOperation::query()->whereKey($operation->id)->lockForUpdate()->firstOrFail();
                if ($current->status !== Status::RUNNING || $current->lease_token !== $lease || !$current->lease_expires_at?->isFuture()) {
                    return;
                }
                if (!hash_equals($operation->payload['word_hash'], $word->sha256) || !hash_equals($profile['fingerprint'], $this->state->profile($word)['fingerprint'])) {
                    throw ValidationException::withMessages(['pdf' => 'Word atau profil konversi berubah. Coba PDF kembali.']);
                }
                $existing = $letter->artifacts()->where('source_artifact_id', $word->id)->where('kind', 'pdf')->latest('id')->first();
                if ($existing !== null && $word->letter_version_id === null) {
                    try {
                        $this->state->assertCurrent($letter, $existing);
                    } catch (ValidationException) {
                        $existing = null;
                    }
                }
                if ($existing === null) {
                    if (!$disk->move($output['path'], $destination)) {
                        throw new RuntimeException('Cannot save PDF.');
                    }
                    $letter->artifacts()->create(['letter_draft_id' => $word->letter_draft_id, 'letter_version_id' => $word->letter_version_id, 'source_artifact_id' => $word->id, 'letter_operation_id' => $current->id, 'kind' => 'pdf', 'draft_revision' => $word->draft_revision, 'state_fingerprint' => $word->state_fingerprint, 'disk' => config('letters.disk'), 'path' => $destination, 'sha256' => $output['sha256'], 'size' => $output['size'], 'font_fingerprint' => $profile['fingerprint'], 'font_usage' => ['schema_version' => 1, 'requirements' => $word->font_usage['requirements'], 'limitations' => $word->font_usage['limitations'] ?? [], 'report' => $profile['report'], 'substitutions' => $profile['substitutions'], 'converter' => $profile['converter'], 'word_hash' => $word->sha256], 'expires_at' => $word->expires_at]);
                    $letter->audits()->create(['actor_uuid' => $current->actor_uuid, 'actor_name' => $current->actor_name, 'event' => 'pdf.generated', 'description' => $word->letter_version_id === null ? 'PDF draft disiapkan dari preview Word.' : 'PDF resmi disiapkan dari Word tersimpan.', 'draft_revision' => $word->draft_revision, 'changes' => ['schema_version' => 1, 'entries' => [], 'operation' => $current->uuid, 'source_artifact' => $word->uuid, 'word_hash' => $word->sha256, 'profile' => $profile['fingerprint']], 'occurred_at' => now()]);
                }
                $this->events->transition($current, Status::SUCCEEDED, 'PDF siap dilihat, dicetak, atau diunduh.', ['lease_token' => null, 'lease_expires_at' => null]);
            }, 1);
        } catch (ValidationException $exception) {
            $this->finish($operation, $lease, Status::STALE, mb_substr(collect($exception->errors())->flatten()->first(), 0, 255));
        } catch (Throwable $exception) {
            $terminal = $operation->attempts >= config('letters.max_attempts');
            $this->finish($operation, $lease, $terminal ? Status::FAILED : Status::QUEUED, $terminal ? 'PDF belum berhasil dibuat. Word tetap tersedia; coba PDF kembali.' : 'Konversi PDF terganggu dan akan dicoba ulang. Word tetap tersedia.');
            throw $exception;
        } finally {
            $disk->deleteDirectory($this->converter->directory($operation, $lease));
            if (!LetterArtifact::query()->where('disk', config('letters.disk'))->where('path', $destination)->exists()) {
                $disk->delete($destination);
            }
        }
    }

    private function finish(LetterOperation $operation, string $lease, Status $status, string $message): void
    {
        DB::transaction(function () use ($operation, $lease, $status, $message): void {
            $current = LetterOperation::query()->whereKey($operation->id)->lockForUpdate()->first();
            if ($current !== null && !$current->status->terminal() && $current->lease_token === $lease) {
                $this->events->transition($current, $status, $message, ['lease_token' => null, 'lease_expires_at' => null]);
            }
        }, 5);
    }

    public function failed(string $uuid): void
    {
        DB::transaction(function () use ($uuid): void {
            $current = LetterOperation::query()->where('uuid', $uuid)->where('kind', 'pdf')->lockForUpdate()->first();
            if ($current !== null && !$current->status->terminal() && !$current->lease_expires_at?->isFuture()) {
                $this->events->transition($current, Status::FAILED, 'Konversi PDF terhenti. Word tetap tersedia; coba PDF kembali.', ['lease_token' => null, 'lease_expires_at' => null]);
            }
        }, 5);
    }
}
