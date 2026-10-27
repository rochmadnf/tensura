<?php

namespace App\Services\Letter;

use App\Enums\LetterOperationStatusEnum;
use App\Http\Resources\LetterOperationResource;
use App\Models\LetterOperation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class LetterOperationEvents
{
    /** Must run in the same transaction as the durable operation change. */
    public function notify(LetterOperation $operation): void
    {
        DB::select('select pg_notify(?, ?)', [config('letters.sse.channel'), $operation->uuid]);
    }

    public function transition(LetterOperation $operation, LetterOperationStatusEnum $status, ?string $message = null, array $attributes = []): void
    {
        $operation->fill([...$attributes, 'status' => $status, 'message' => $message, 'status_revision' => $operation->status_revision + 1, 'finished_at' => $status->terminal() ? now() : null])->save();
        $this->notify($operation);
    }

    /** @return list<array> */
    public function snapshot(string $actorUuid, ?string $operationUuid = null): array
    {
        return LetterOperation::query()->select(['id', 'uuid', 'letter_id', 'source_artifact_id', 'kind', 'draft_revision', 'status', 'status_revision', 'message', 'updated_at'])->with(['letter:id,uuid,subject', 'sourceArtifact:id,uuid'])->where(fn(Builder $query): Builder => $query->where('actor_uuid', $actorUuid)->orWhereExists(fn(QueryBuilder $request): QueryBuilder => $request->select('letter_pdf_requests.id')->from('letter_pdf_requests')->whereColumn('letter_pdf_requests.letter_operation_id', 'letter_operations.id')->where('letter_pdf_requests.actor_uuid', $actorUuid)))
            ->where(fn(Builder $query): Builder => $query->whereIn('status', [LetterOperationStatusEnum::QUEUED, LetterOperationStatusEnum::RUNNING])->orWhere('updated_at', '>=', now()->subDays(config('letters.sse.history_days'))))
            ->when($operationUuid, fn(Builder $query): Builder => $query->where('uuid', $operationUuid))
            ->orderBy('id')->get()->map(fn(LetterOperation $operation): array => (new LetterOperationResource($operation))->resolve())->all();
    }
}
