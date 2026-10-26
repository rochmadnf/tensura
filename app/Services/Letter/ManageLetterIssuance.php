<?php

namespace App\Services\Letter;

use App\Enums\LetterOperationStatusEnum as Status;
use App\Enums\LetterStatusEnum;
use App\Http\Resources\LetterOperationResource;
use App\Jobs\IssueLetter;
use App\Models\Letter;
use App\Models\LetterOperation;
use App\Models\MasterData\User;
use App\Services\LetterNumber\AllocateLetterNumber;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ManageLetterIssuance
{
    public function __construct(private LetterIssuanceState $state, private LetterDraftState $tokens, private LetterOperationEvents $events, private AllocateLetterNumber $numbers) {}

    public function request(Letter $letter, array $data, User $actor): LetterOperation
    {
        $data = [...$data, 'revision' => (int) $data['revision'], 'keep_period' => true, 'confirmed' => true];
        $hash = $this->tokens->token(['letter' => $letter->uuid, 'request' => array_diff_key($data, ['idempotency_key' => true])]);
        $replay = $this->replay($data['idempotency_key'], $actor, $hash);
        if ($replay !== null) {
            return $replay;
        }
        if ($letter->status === LetterStatusEnum::PUBLISHED && $letter->currentVersion !== null) {
            return $letter->currentVersion->operation;
        }
        try {
            $operation = DB::transaction(function () use ($letter, $data, $actor, $hash): LetterOperation {
                [$letter, $draft, $classification] = $this->state->lock($letter, (int) $data['revision']);
                $replay = $this->replay($data['idempotency_key'], $actor, $hash);
                if ($replay !== null) {
                    return $replay;
                }
                $artifact = $this->state->inspected($letter, $draft, $data, $actor->uuid);
                $this->numbers->candidate($classification, $draft);
                $pending = $letter->operations()->where('kind', 'issue')->whereIn('status', [Status::QUEUED, Status::RUNNING])->where('draft_revision', $draft->revision)->first();
                if ($pending !== null) {
                    if ($pending->actor_uuid === $actor->uuid) {
                        return $pending;
                    }
                    throw ValidationException::withMessages(['issuance' => 'Penerbitan surat ini sedang diproses oleh operator lain. Muat ulang status sebelum mencoba lagi.']);
                }
                $operation = $letter->operations()->create(['letter_draft_id' => $draft->id, 'actor_uuid' => $actor->uuid, 'actor_name' => $actor->name, 'kind' => 'issue', 'idempotency_key' => $data['idempotency_key'], 'request_hash' => $hash, 'draft_revision' => $draft->revision, 'status' => Status::QUEUED, 'status_revision' => 1, 'payload' => ['schema_version' => 1, 'revision' => $draft->revision, 'artifact' => $artifact->uuid, 'fingerprint' => $data['fingerprint'], 'publication_month' => $data['publication_month'], 'state' => $artifact->state_fingerprint, 'snapshot' => $artifact->operation->payload['snapshot'], 'source_disk' => $draft->templateVersion->source_disk, 'source_path' => $draft->templateVersion->source_path, 'source_sha256' => $draft->templateVersion->source_sha256, 'subject' => $letter->subject]]);
                $this->events->notify($operation);

                return $operation;
            }, 5);
        } catch (UniqueConstraintViolationException $exception) {
            $operation = $this->replay($data['idempotency_key'], $actor, $hash);
            if ($operation === null) {
                throw $exception;
            }
        }
        if ($operation->status === Status::QUEUED) {
            try {
                IssueLetter::dispatch($operation->uuid)->afterCommit();
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $operation;
    }

    private function replay(string $key, User $actor, string $hash): ?LetterOperation
    {
        $operation = LetterOperation::query()->where('actor_uuid', $actor->uuid)->where('idempotency_key', $key)->first();
        if ($operation !== null && ($operation->kind !== 'issue' || !hash_equals($hash, $operation->request_hash))) {
            throw ValidationException::withMessages(['idempotency_key' => 'Kunci permintaan telah digunakan untuk operasi berbeda.']);
        }

        return $operation;
    }

    public function detail(Letter $letter, User $actor): array
    {
        $operation = $letter->operations()->where('kind', 'issue')->where('actor_uuid', $actor->uuid)->orderByDesc('id')->first();
        $draft = $letter->draft;

        return ['operation' => $operation === null ? null : (new LetterOperationResource($operation))->resolve(), 'publication_month' => now()->format('Y-m'), 'crossed_month' => $draft !== null && $draft->created_at->format('Y-m') !== now()->format('Y-m'), 'date' => $draft?->letter_date->toDateString(), 'period' => $draft === null ? null : sprintf('%04d-%02d', $draft->number_year, $draft->number_month)];
    }
}
