<?php

namespace App\Services\Letter;

use App\Enums\LetterNumberingModeEnum;
use App\Enums\LetterOperationStatusEnum as Status;
use App\Enums\LetterStatusEnum;
use App\Models\LetterArtifact;
use App\Models\LetterOperation;
use App\Services\LetterNumber\AllocateLetterNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class RunLetterIssuance
{
    public function __construct(private LetterIssuanceState $state, private AllocateLetterNumber $numbers, private LetterOperationEvents $events, private RenderLetterOperation $renderer, private DocumentFontRegistry $fonts) {}

    public function run(string $uuid): void
    {
        $operation = DB::transaction(function () use ($uuid): ?LetterOperation {
            $operation = LetterOperation::query()->where('uuid', $uuid)->where('kind', 'issue')->first();
            if ($operation === null || $operation->status->terminal()) {
                return null;
            }
            [$letter, $draft] = $this->state->lock($operation->letter, $operation->draft_revision, false);
            $operation = LetterOperation::query()->whereKey($operation->id)->lockForUpdate()->firstOrFail();
            if ($operation->status->terminal() || $operation->lease_expires_at?->isFuture()) {
                return null;
            }
            if ($letter->status === LetterStatusEnum::PUBLISHED) {
                $this->events->transition($operation, Status::STALE, 'Surat sudah diterbitkan. Buka dokumen resminya.');

                return null;
            }
            if ($operation->attempts >= config('letters.max_attempts')) {
                $this->events->transition($operation, Status::FAILED, 'Penerbitan belum berhasil. Silakan coba kembali.');

                return null;
            }
            try {
                $this->state->inspected($letter, $draft, $operation->payload, $operation->actor_uuid);
            } catch (ValidationException) {
                $this->events->transition($operation, Status::STALE, 'Draft, tanggal/periode, templat, atau pemeriksaan berubah. Periksa status terbaru.');

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
        $destination = config('letters.paths.official') . '/' . $operation->uuid . '/' . $lease . '.docx';
        $started = microtime(true);
        try {
            for ($conflict = 0; $conflict < 3; $conflict++) {
                $candidate = DB::transaction(function () use ($operation): array {
                    [$letter, $draft, $classification] = $this->state->lock($operation->letter, $operation->draft_revision);
                    $this->state->inspected($letter, $draft, $operation->payload, $operation->actor_uuid);

                    return $this->numbers->candidate($classification, $draft);
                }, 5);
                $snapshot = $operation->payload['snapshot'];
                $snapshot['values']['nomor_surat'] = $candidate['number'];
                $remaining = config('letters.render_timeout') - (microtime(true) - $started);
                if ($remaining <= 0) {
                    throw ValidationException::withMessages(['issuance' => 'Waktu persiapan dokumen habis. Coba penerbitan kembali.']);
                }
                $output = $this->renderer->render($operation, $lease, $snapshot, false, $remaining);
                $committed = DB::transaction(function () use ($operation, $lease, $candidate, $snapshot, $output, $disk, $destination): bool {
                    [$letter, $draft, $classification] = $this->state->lock($operation->letter, $operation->draft_revision);
                    $current = LetterOperation::query()->whereKey($operation->id)->lockForUpdate()->firstOrFail();
                    if ($current->status !== Status::RUNNING || $current->lease_token !== $lease || !$current->lease_expires_at?->isFuture()) {
                        return true;
                    }
                    $preview = $this->state->inspected($letter, $draft, $operation->payload, $operation->actor_uuid);
                    if ($candidate !== $this->numbers->candidate($classification, $draft)) {
                        if ($draft->numbering_mode === LetterNumberingModeEnum::MANUAL) {
                            throw ValidationException::withMessages(['number' => 'Pilihan nomor berubah. Pilih dan periksa ulang.']);
                        }

                        return false;
                    }
                    $this->fonts->inventory(true);
                    if (!hash_equals($preview->font_fingerprint, $this->fonts->fingerprint($output['usage']['requirements']))) {
                        throw ValidationException::withMessages(['issuance' => 'Profil font berubah. Generate preview dan periksa ulang.']);
                    }
                    if (!$disk->move($output['path'], $destination)) {
                        throw new RuntimeException('Cannot save official Word.');
                    }
                    $claim = $this->numbers->claim($classification, $draft, $candidate);
                    $version = $letter->versions()->create(['version_number' => 1, 'template_version_id' => $draft->template_version_id, 'letter_number_assignment_id' => $claim->id, 'letter_operation_id' => $current->id, 'subject' => $operation->payload['subject'], 'number' => $candidate['number'], 'letter_date' => $draft->letter_date, 'number_year' => $draft->number_year, 'number_month' => $draft->number_month, 'snapshot' => [...$snapshot, 'used_tokens' => $draft->templateVersion->validation_result['placeholders'] ?? []], 'inspection' => ['schema_version' => 1, 'artifact' => $preview->uuid, 'fingerprint' => $operation->payload['fingerprint'], 'actor_uuid' => $current->actor_uuid, 'kind' => 'docx'], 'actor_uuid' => $current->actor_uuid, 'actor_name' => $current->actor_name, 'issued_at' => now()]);
                    $artifact = LetterArtifact::query()->create(['letter_id' => $letter->id, 'letter_draft_id' => null, 'letter_version_id' => $version->id, 'letter_operation_id' => $current->id, 'kind' => 'docx', 'draft_revision' => $draft->revision, 'state_fingerprint' => $operation->payload['state'], 'disk' => config('letters.disk'), 'path' => $destination, 'sha256' => $output['sha256'], 'size' => $output['size'], 'font_fingerprint' => $preview->font_fingerprint, 'font_usage' => ['schema_version' => 1, ...$output['usage']], 'expires_at' => null]);
                    $version->update(['word_artifact_id' => $artifact->id]);
                    $letter->update(['status' => LetterStatusEnum::PUBLISHED, 'official_number' => $candidate['number'], 'official_date' => $draft->letter_date, 'current_version_id' => $version->id]);
                    $draft->templateVersion()->whereNull('first_used_at')->update(['first_used_at' => now()]);
                    $letter->audits()->create(['actor_uuid' => $current->actor_uuid, 'actor_name' => $current->actor_name, 'event' => 'letter.issued', 'description' => 'Surat diterbitkan dengan nomor ' . $candidate['number'] . '.', 'draft_revision' => $draft->revision, 'changes' => ['schema_version' => 1, 'entries' => [['label' => 'Nomor resmi', 'before' => null, 'after' => $candidate['number']]], 'operation' => $current->uuid, 'version' => $version->uuid, 'assignment' => $claim->uuid], 'occurred_at' => now()]);
                    $this->events->transition($current, Status::SUCCEEDED, 'Surat berhasil diterbitkan.', ['lease_token' => null, 'lease_expires_at' => null]);

                    return true;
                }, 1);
                if ($committed) {
                    return;
                }
                $disk->deleteDirectory($this->renderer->directory($operation, $lease));
            }
            throw ValidationException::withMessages(['number' => 'Urutan berubah selama persiapan dokumen. Nomor belum diambil; coba penerbitan kembali.']);
        } catch (ValidationException $exception) {
            $this->finish($operation, $lease, isset($exception->errors()['number']) ? Status::FAILED : Status::STALE, mb_substr(collect($exception->errors())->flatten()->first() ?? 'Penerbitan tertahan. Periksa draft kembali.', 0, 255));
        } catch (Throwable $exception) {
            $terminal = $operation->attempts >= config('letters.max_attempts');
            $this->finish($operation, $lease, $terminal ? Status::FAILED : Status::QUEUED, $terminal ? 'Penerbitan gagal. Nomor belum diambil; silakan coba lagi.' : 'Gangguan sementara. Penerbitan akan dicoba ulang.');
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
                $this->events->transition($operation, Status::FAILED, 'Penerbitan terhenti. Periksa status dan coba lagi.', ['lease_token' => null, 'lease_expires_at' => null]);
            }
        }, 5);
    }
}
