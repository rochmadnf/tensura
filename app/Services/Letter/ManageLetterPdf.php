<?php

namespace App\Services\Letter;

use App\Enums\LetterOperationStatusEnum as Status;
use App\Http\Resources\LetterOperationResource;
use App\Jobs\ConvertLetterToPdf;
use App\Models\Letter;
use App\Models\LetterArtifact;
use App\Models\LetterOperation;
use App\Models\MasterData\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ManageLetterPdf
{
    public function __construct(private LetterPdfState $state, private LetterDraftState $tokens, private LetterOperationEvents $events, private RecordLetterNotification $notifications) {}

    public function request(Letter $letter, LetterArtifact $word, array $data, User $actor): LetterOperation
    {
        $hash = $this->tokens->token(['kind' => 'pdf', 'letter' => $letter->uuid, 'word' => $word->uuid]);
        $replay = $this->replay($data['idempotency_key'], $actor, $hash);
        if ($replay !== null) {
            return $replay;
        }
        try {
            $operation = DB::transaction(function () use ($letter, $word, $data, $actor, $hash): LetterOperation {
                [$letter, $word] = $this->state->lock($letter, $word);
                $replay = $this->replay($data['idempotency_key'], $actor, $hash);
                if ($replay !== null) {
                    return $replay;
                }
                $pdf = $letter->artifacts()->where('source_artifact_id', $word->id)->where('kind', 'pdf')->latest('id')->first();
                if ($pdf !== null && $word->letter_version_id === null) {
                    try {
                        $this->state->assertCurrent($letter, $pdf);
                    } catch (ValidationException) {
                        $pdf = null;
                    }
                }
                if ($pdf !== null) {
                    $this->state->assertCurrent($letter, $pdf);
                    $operation = $pdf->operation;
                } else {
                    $operation = $letter->operations()->where('source_artifact_id', $word->id)->where('kind', 'pdf')->whereIn('status', [Status::QUEUED, Status::RUNNING])->first();
                    if ($operation === null) {
                        $operation = $letter->operations()->create(['letter_draft_id' => $word->letter_draft_id, 'source_artifact_id' => $word->id, 'actor_uuid' => $actor->uuid, 'actor_name' => $actor->name, 'kind' => 'pdf', 'idempotency_key' => $data['idempotency_key'], 'request_hash' => $hash, 'draft_revision' => $word->draft_revision, 'status' => Status::QUEUED, 'status_revision' => 1, 'payload' => ['schema_version' => 1, 'word' => $word->uuid, 'word_hash' => $word->sha256]]);
                    }
                }
                DB::table('letter_pdf_requests')->insert(['letter_operation_id' => $operation->id, 'actor_uuid' => $actor->uuid, 'idempotency_key' => $data['idempotency_key'], 'request_hash' => $hash, 'created_at' => now(), 'updated_at' => now()]);
                $this->notifications->record($operation, $actor);
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
                ConvertLetterToPdf::dispatch($operation->uuid)->afterCommit();
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $operation;
    }

    private function replay(string $key, User $actor, string $hash): ?LetterOperation
    {
        $request = DB::table('letter_pdf_requests')->where('actor_uuid', $actor->uuid)->where('idempotency_key', $key)->first();
        $operation = $request === null ? LetterOperation::query()->where('actor_uuid', $actor->uuid)->where('idempotency_key', $key)->first() : LetterOperation::query()->findOrFail($request->letter_operation_id);
        if ($operation !== null && ($operation->kind !== 'pdf' || !hash_equals($hash, $request?->request_hash ?? $operation->request_hash))) {
            throw ValidationException::withMessages(['idempotency_key' => 'Kunci permintaan telah digunakan untuk operasi berbeda.']);
        }

        return $operation;
    }

    public function detail(Letter $letter, User $actor, ?LetterArtifact $source = null): array
    {
        $word = $source ?? ($letter->draft?->isWorking() ? null : $letter->currentVersion?->wordArtifact) ?? $letter->artifacts()->whereNull('letter_version_id')->where('kind', 'docx')->where('draft_revision', $letter->draft?->revision)->latest('id')->first();
        $result = ['source_id' => null, 'operation' => null, 'artifact' => null, 'reason' => null, 'max_bytes' => config('letters.pdf.max_bytes')];
        if ($word === null) {
            return $result;
        }
        try {
            $this->state->assertWord($letter, $word);
            $result['source_id'] = $word->uuid;
            $result['draft_id'] = $word->draft?->uuid;
            $result['base_version'] = $word->draft?->baseVersion?->uuid;
            $operation = $letter->operations()->where('source_artifact_id', $word->id)->where('kind', 'pdf')->latest('id')->first();
            $result['operation'] = $operation === null ? null : (new LetterOperationResource($operation))->resolve();
            $pdf = $letter->artifacts()->where('source_artifact_id', $word->id)->where('kind', 'pdf')->latest('id')->first();
            if ($pdf !== null) {
                $this->state->assertCurrent($letter, $pdf);
                $check = $pdf->checks()->where('actor_uuid', $actor->uuid)->first();
                $result['artifact'] = ['id' => $pdf->uuid, 'fingerprint' => $this->state->evidence($pdf), 'confirmed' => $check?->confirmed_at !== null, 'font_report' => $pdf->font_usage['report'], 'substitutions' => $pdf->font_usage['substitutions'], 'converter' => $pdf->font_usage['converter']];
            }
        } catch (ValidationException $exception) {
            $result['reason'] = collect($exception->errors())->flatten()->first();
        } catch (Throwable $exception) {
            report($exception);
            $result['reason'] = 'Status PDF belum dapat diperiksa. Word tetap tersedia.';
        }

        return $result;
    }
}
