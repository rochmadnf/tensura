<?php

namespace App\Services\Letter;

use App\Enums\LetterOperationStatusEnum;
use App\Http\Resources\LetterOperationResource;
use App\Jobs\GenerateLetterPreview;
use App\Models\Letter;
use App\Models\LetterArtifact;
use App\Models\LetterOperation;
use App\Models\MasterData\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ManageLetterPreview
{
    public function __construct(private LetterPreviewState $state, private LetterDraftState $tokens, private LetterOperationEvents $events, private DocumentFontRegistry $fonts) {}

    /** @param array{revision: int, idempotency_key: string} $data */
    public function request(Letter $letter, array $data, User $actor): LetterOperation
    {
        $hash = $this->tokens->token(['letter' => $letter->uuid, 'revision' => (int) $data['revision'], 'kind' => 'preview']);
        $existing = $this->replay($data['idempotency_key'], $actor, $hash);
        if ($existing !== null) {
            return $existing;
        }
        try {
            $operation = DB::transaction(function () use ($letter, $data, $actor): LetterOperation {
                [$letter, $draft] = $this->state->lock($letter, (int) $data['revision']);
                $hash = $this->tokens->token(['letter' => $letter->uuid, 'revision' => (int) $data['revision'], 'kind' => 'preview']);
                $existing = LetterOperation::query()->where('actor_uuid', $actor->uuid)->where('idempotency_key', $data['idempotency_key'])->first();
                if ($existing !== null) {
                    if (!hash_equals($hash, $existing->request_hash)) {
                        throw ValidationException::withMessages(['idempotency_key' => 'Kunci permintaan telah digunakan untuk operasi berbeda.']);
                    }

                    return $existing;
                }
                $this->state->assertReady($letter, $draft);
                $this->state->assertSource($draft);
                $fingerprint = $this->state->fingerprint($draft);
                $requirements = $draft->templateVersion->test_font_requirements['requirements'] ?? $draft->templateVersion->validation_result['fonts']['requirements'] ?? [];
                $this->fonts->inventory(true);
                $profile = $this->fonts->fingerprint($requirements);
                $existing = LetterOperation::query()->where('letter_id', $letter->id)->where('actor_uuid', $actor->uuid)->where('draft_revision', $draft->revision)->whereIn('status', [LetterOperationStatusEnum::QUEUED, LetterOperationStatusEnum::RUNNING, LetterOperationStatusEnum::SUCCEEDED])->orderByDesc('id')->first();
                if ($existing !== null && ($existing->payload['state'] ?? '') === $fingerprint && ($existing->payload['requested_profile'] ?? '') === $profile) {
                    if (!$existing->status->terminal()) {
                        return $existing;
                    }
                    try {
                        if ($existing->artifact !== null) {
                            $this->state->assertCurrent($letter, $existing->artifact);

                            return $existing;
                        }
                    } catch (ValidationException) {
                    }
                }
                $operation = LetterOperation::query()->create(['letter_id' => $letter->id, 'letter_draft_id' => $draft->id, 'actor_uuid' => $actor->uuid, 'actor_name' => $actor->name, 'kind' => 'preview', 'idempotency_key' => $data['idempotency_key'], 'request_hash' => $hash, 'draft_revision' => $draft->revision, 'status' => LetterOperationStatusEnum::QUEUED, 'status_revision' => 1, 'payload' => ['schema_version' => 1, 'state' => $fingerprint, 'requested_profile' => $profile, 'snapshot' => $draft->snapshot, 'source_disk' => $draft->templateVersion->source_disk, 'source_path' => $draft->templateVersion->source_path, 'source_sha256' => $draft->templateVersion->source_sha256]]);
                $this->events->notify($operation);

                return $operation;
            }, 5);
        } catch (UniqueConstraintViolationException $exception) {
            $operation = $this->replay($data['idempotency_key'], $actor, $hash);
            if ($operation === null) {
                throw $exception;
            }
        }
        if ($operation->status === LetterOperationStatusEnum::QUEUED) {
            try {
                GenerateLetterPreview::dispatch($operation->uuid)->afterCommit();
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $operation;
    }

    private function replay(string $key, User $actor, string $hash): ?LetterOperation
    {
        $operation = LetterOperation::query()->where('actor_uuid', $actor->uuid)->where('idempotency_key', $key)->first();
        if ($operation !== null && !hash_equals($hash, $operation->request_hash)) {
            throw ValidationException::withMessages(['idempotency_key' => 'Kunci permintaan telah digunakan untuk operasi berbeda.']);
        }

        return $operation;
    }

    public function check(Letter $letter, LetterArtifact $artifact, array $data, User $actor, bool $confirm): void
    {
        DB::transaction(function () use ($letter, $artifact, $data, $actor, $confirm): void {
            [$letter] = $this->state->lock($letter, (int) $data['revision']);
            $this->state->assertCurrent($letter, $artifact);
            $fingerprint = $this->state->evidence($artifact);
            if (!hash_equals($fingerprint, $data['fingerprint'])) {
                throw ValidationException::withMessages(['preview' => 'Hasil yang diperiksa berubah. Buka preview terbaru.']);
            }
            $check = $artifact->checks()->where('actor_uuid', $actor->uuid)->lockForUpdate()->first();
            if ($confirm) {
                if ($check === null || !hash_equals($fingerprint, $check->fingerprint)) {
                    throw ValidationException::withMessages(['preview' => 'Buka dan tunggu render preview selesai sebelum mengonfirmasi.']);
                }
                if ($check->confirmed_at !== null) {
                    return;
                }
                $check->update(['confirmed_at' => now()]);
                $letter->audits()->create(['actor_uuid' => $actor->uuid, 'actor_name' => $actor->name, 'event' => 'preview.confirmed', 'description' => 'Preview DOCX draft telah diperiksa.', 'draft_revision' => $artifact->draft_revision, 'changes' => ['schema_version' => 1, 'entries' => [], 'artifact' => $artifact->uuid, 'fingerprint' => $fingerprint], 'occurred_at' => now()]);
            } elseif ($check === null) {
                $artifact->checks()->create(['actor_uuid' => $actor->uuid, 'actor_name' => $actor->name, 'preview_kind' => 'docx', 'fingerprint' => $fingerprint, 'rendered_at' => now()]);
            }
        }, 5);
    }

    public function detail(Letter $letter, User $actor): array
    {
        $latest = LetterOperation::query()->where('letter_id', $letter->id)->where('actor_uuid', $actor->uuid)->orderByDesc('id')->first();
        $artifact = LetterArtifact::query()->where('letter_id', $letter->id)->where('draft_revision', $letter->draft?->revision)->orderByDesc('id')->first();
        $reason = null;
        $canGenerate = false;
        try {
            if ($letter->draft === null) {
                throw ValidationException::withMessages(['preview' => 'Draft tidak tersedia.']);
            }
            $this->state->assertReady($letter, $letter->draft);
            $this->state->assertSource($letter->draft);
            $canGenerate = true;
            if ($artifact !== null) {
                $this->state->assertCurrent($letter, $artifact);
            }
        } catch (ValidationException $exception) {
            $reason = collect($exception->errors())->flatten()->first();
            $artifact = null;
        }
        $check = $artifact?->checks()->where('actor_uuid', $actor->uuid)->first();

        return ['revision' => $letter->draft?->revision, 'operation' => $latest === null ? null : (new LetterOperationResource($latest))->resolve(), 'artifact' => $artifact === null ? null : ['id' => $artifact->uuid, 'fingerprint' => $this->state->evidence($artifact), 'font_report' => [...$this->fonts->report($artifact->font_usage['requirements']), 'limitations' => $artifact->font_usage['limitations']], 'expires_at' => $artifact->expires_at->toIso8601String(), 'confirmed' => $check?->confirmed_at !== null], 'can_generate' => $canGenerate, 'reason' => $reason, 'max_bytes' => config('letters.max_output_bytes')];
    }
}
