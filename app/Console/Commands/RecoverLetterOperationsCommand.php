<?php

namespace App\Console\Commands;

use App\Enums\LetterOperationStatusEnum as Status;
use App\Jobs\ConvertLetterToPdf;
use App\Jobs\GenerateLetterPreview;
use App\Jobs\IssueLetter;
use App\Models\LetterOperation;
use App\Services\Letter\LetterOperationEvents;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class RecoverLetterOperationsCommand extends Command
{
    protected $signature = 'letters:recover-operations';

    protected $description = 'Recover pending letter dispatches and expired worker leases';

    public function handle(LetterOperationEvents $events): int
    {
        LetterOperation::query()->whereIn('kind', ['preview', 'issue', 'pdf'])->where(function (Builder $query): void {
            $query->where(fn(Builder $query): Builder => $query->where('status', Status::QUEUED)->where('updated_at', '<=', now()->subMinutes(2)))
                ->orWhere(fn(Builder $query): Builder => $query->where('status', Status::RUNNING)->where('lease_expires_at', '<=', now()));
        })->orderBy('id')->chunkById(100, function ($operations) use ($events): void {
            foreach ($operations as $candidate) {
                DB::transaction(function () use ($candidate, $events): void {
                    $operation = LetterOperation::query()->whereKey($candidate->id)->lockForUpdate()->first();
                    if ($operation === null || $operation->status->terminal() || $operation->lease_expires_at?->isFuture() || ($operation->status === Status::QUEUED && $operation->updated_at->greaterThan(now()->subMinutes(2)))) {
                        return;
                    }
                    if ($operation->attempts >= config('letters.max_attempts')) {
                        $message = $operation->kind === 'pdf' ? 'Konversi PDF terhenti. Word tetap tersedia; coba PDF kembali.' : ($operation->kind === 'issue' ? 'Penerbitan terhenti setelah beberapa percobaan. Periksa status dan coba lagi.' : 'Proses terhenti setelah beberapa percobaan. Generate ulang preview.');
                        $events->transition($operation, Status::FAILED, $message, ['lease_token' => null, 'lease_expires_at' => null]);

                        return;
                    }
                    $events->transition($operation, Status::QUEUED, 'Melanjutkan proses yang tertunda.', ['lease_token' => null, 'lease_expires_at' => null]);
                    if ($operation->kind === 'pdf') {
                        ConvertLetterToPdf::dispatch($operation->uuid)->afterCommit();
                    } elseif ($operation->kind === 'issue') {
                        IssueLetter::dispatch($operation->uuid)->afterCommit();
                    } else {
                        GenerateLetterPreview::dispatch($operation->uuid)->afterCommit();
                    }
                }, 5);
            }
        });

        return self::SUCCESS;
    }
}
