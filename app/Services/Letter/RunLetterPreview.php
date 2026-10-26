<?php

namespace App\Services\Letter;

use App\Enums\LetterOperationStatusEnum as Status;
use App\Models\LetterArtifact;
use App\Models\LetterOperation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class RunLetterPreview
{
    public function __construct(private LetterPreviewState $state, private LetterOperationEvents $events, private RenderLetterOperation $renderer, private DocumentFontRegistry $fonts) {}

    public function run(string $uuid): void
    {
        $operation = DB::transaction(function () use ($uuid): ?LetterOperation {
            $operation = LetterOperation::query()->where('uuid', $uuid)->where('kind', 'preview')->first();
            if ($operation === null || $operation->status->terminal()) {
                return null;
            }
            [$letter, $draft] = $this->state->lock($operation->letter, $operation->draft_revision, false);
            $operation = LetterOperation::query()->whereKey($operation->id)->lockForUpdate()->first();
            if ($operation === null || $operation->status->terminal() || $operation->lease_expires_at?->isFuture()) {
                return null;
            }
            if ($operation->attempts >= config('letters.max_attempts')) {
                $this->events->transition($operation, Status::FAILED, 'Proses belum berhasil setelah beberapa percobaan. Generate ulang preview.');

                return null;
            }
            try {
                if ($draft->revision !== $operation->draft_revision) {
                    throw ValidationException::withMessages(['preview' => 'Draft berubah.']);
                }
                $this->state->assertReady($letter, $draft);
                if (!hash_equals($operation->payload['state'], $this->state->fingerprint($draft))) {
                    throw ValidationException::withMessages(['preview' => 'Draft atau templat telah berubah.']);
                }
            } catch (ValidationException) {
                $this->events->transition($operation, Status::STALE, 'Draft atau templat berubah. Generate preview terbaru.');

                return null;
            }
            $this->events->transition($operation, Status::RUNNING, null, ['attempts' => $operation->attempts + 1, 'lease_token' => (string) Str::uuid(), 'lease_expires_at' => now()->addSeconds(config('letters.lease_seconds'))]);

            return $operation;
        }, 5);
        if ($operation === null) {
            return;
        }
        $lease = $operation->lease_token;
        $disk = Storage::disk(config('letters.disk'));
        $destination = config('letters.paths.previews') . '/' . $operation->uuid . '/' . $lease . '.docx';
        $rendered = false;
        try {
            $baseline = $this->fonts->freeze();
            $output = $this->renderer->render($operation, $lease);
            $rendered = true;
            $profile = $baseline->fingerprint($output['usage']['requirements']);
            $this->fonts->inventory(true);
            DB::transaction(function () use ($operation, $lease, $destination, $output, $profile, $disk): void {
                [$letter, $draft] = $this->state->lock($operation->letter, $operation->draft_revision, false);
                $current = LetterOperation::query()->whereKey($operation->id)->lockForUpdate()->firstOrFail();
                if ($current->status !== Status::RUNNING || $current->lease_token !== $lease || !$current->lease_expires_at?->isFuture()) {
                    return;
                }
                $this->state->assertReady($letter, $draft);
                $this->state->assertSource($draft);
                if (!hash_equals($operation->payload['state'], $this->state->fingerprint($draft)) || !hash_equals($profile, $this->fonts->fingerprint($output['usage']['requirements']))) {
                    throw ValidationException::withMessages(['preview' => 'Draft, templat, atau font terkait berubah selama generate.']);
                }
                if (!$disk->move($output['path'], $destination)) {
                    throw new \RuntimeException('Cannot promote preview output.');
                }
                LetterArtifact::query()->create(['letter_id' => $letter->id, 'letter_draft_id' => $draft->id, 'letter_operation_id' => $current->id, 'kind' => 'docx', 'draft_revision' => $draft->revision, 'state_fingerprint' => $operation->payload['state'], 'disk' => config('letters.disk'), 'path' => $destination, 'sha256' => $output['sha256'], 'size' => $output['size'], 'font_fingerprint' => $profile, 'font_usage' => ['schema_version' => 1, ...$output['usage']], 'expires_at' => now()->addHours(config('letters.preview_hours'))]);
                $this->events->transition($current, Status::SUCCEEDED, 'Preview draft siap diperiksa.', ['lease_token' => null, 'lease_expires_at' => null]);
                $letter->audits()->create(['actor_uuid' => $current->actor_uuid, 'actor_name' => $current->actor_name, 'event' => 'preview.generated', 'description' => 'Preview DOCX draft dibuat tanpa mengambil nomor surat.', 'draft_revision' => $draft->revision, 'changes' => ['schema_version' => 1, 'entries' => [], 'operation' => $current->uuid], 'occurred_at' => now()]);
            }, 1);
        } catch (ValidationException) {
            $this->finish($operation, $lease, $rendered ? Status::STALE : Status::FAILED, $rendered ? 'Draft, templat, atau font berubah. Generate preview terbaru.' : 'Preview gagal dibuat. Periksa isian dan DOCX templat, lalu coba lagi.');
        } catch (Throwable $exception) {
            $terminal = $operation->attempts >= config('letters.max_attempts');
            $this->finish($operation, $lease, $terminal ? Status::FAILED : Status::QUEUED, $terminal ? 'Preview gagal dibuat. Silakan coba generate kembali.' : 'Gangguan sementara. Proses akan dicoba ulang.');
            throw $exception;
        } finally {
            $disk->deleteDirectory($this->renderer->directory($operation, $lease));
            if (!LetterArtifact::query()->where('disk', config('letters.disk'))->where('path', $destination)->exists()) {
                $disk->delete($destination);
            }
        }
    }

    private function finish(LetterOperation $operation, string $lease, Status $status, string $message): void
    {
        DB::transaction(function () use ($operation, $lease, $status, $message): void {
            $current = LetterOperation::query()->whereKey($operation->id)->lockForUpdate()->first();
            if ($current !== null && $current->lease_token === $lease && !$current->status->terminal()) {
                $this->events->transition($current, $status, $message, ['lease_token' => null, 'lease_expires_at' => null]);
            }
        }, 5);
    }

    public function failed(string $uuid): void
    {
        DB::transaction(function () use ($uuid): void {
            $operation = LetterOperation::query()->where('uuid', $uuid)->lockForUpdate()->first();
            if ($operation !== null && !$operation->status->terminal() && !$operation->lease_expires_at?->isFuture()) {
                $this->events->transition($operation, Status::FAILED, 'Proses terhenti atau melewati batas waktu. Generate ulang preview.', ['lease_token' => null, 'lease_expires_at' => null]);
            }
        }, 5);
    }
}
