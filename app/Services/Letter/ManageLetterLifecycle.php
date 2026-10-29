<?php

namespace App\Services\Letter;

use App\Enums\LetterDraftKindEnum as Kind;
use App\Enums\LetterOperationStatusEnum as OperationStatus;
use App\Enums\LetterStatusEnum as Status;
use App\Enums\LetterTemplateVersionStatusEnum;
use App\Models\Letter;
use App\Models\LetterNumber;
use App\Models\LetterNumberAssignment;
use App\Models\MasterData\LetterTemplate;
use App\Models\MasterData\User;
use App\Services\LetterNumber\AllocateLetterNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageLetterLifecycle
{
    public function __construct(private LetterDraftState $state, private LetterDraftSnapshot $snapshots, private AdjustLetterDraftTemplate $adjustments, private LetterOperationEvents $events) {}

    public function token(Letter $letter): string
    {
        $draft = $letter->draft;

        return $this->state->token(['letter' => $letter->uuid, 'status' => $letter->status->value, 'version' => $letter->current_version_id, 'draft' => $draft?->uuid, 'revision' => $draft?->revision, 'cancelled_at' => $letter->cancelled_at?->toIso8601String()]);
    }

    private function lock(Letter $letter, bool $calendar = false): Letter
    {
        LetterTemplate::query()->whereKey($letter->letter_template_id)->sharedLock()->firstOrFail();
        if ($calendar) {
            $base = $letter->fresh()->currentVersion;
            app(LetterCalendarState::class)->lock($base?->snapshot ?? [], $base?->snapshot['inputs']['values'] ?? []);
        }
        $letter = Letter::query()->whereKey($letter->id)->lockForUpdate()->firstOrFail();
        $letter->setRelation('draft', $letter->draft()->lockForUpdate()->first());

        return $letter;
    }

    public function start(Letter $letter, array $data, User $actor, Kind $kind): void
    {
        DB::transaction(function () use ($letter, $data, $actor, $kind): void {
            $letter = $this->lock($letter, true);
            $base = $letter->currentVersion;
            if ($base === null || $base->uuid !== $data['base_version'] || $letter->status !== ($kind === Kind::REVISION ? Status::PUBLISHED : Status::CANCELLED)) {
                throw ValidationException::withMessages(['lifecycle' => 'Versi resmi atau status surat berubah. Muat ulang halaman.']);
            }
            if ($letter->draft?->isWorking()) {
                return;
            }
            $this->assertToken($letter, $data);
            $target = $letter->template->activeVersion;
            if (!$letter->template->is_active || $target === null || $target->status !== LetterTemplateVersionStatusEnum::PUBLISHED) {
                throw ValidationException::withMessages(['lifecycle' => 'Templat belum mempunyai versi aktif yang dapat digunakan.']);
            }
            $revision = max($letter->draft?->revision ?? 0, (int) $letter->audits()->max('draft_revision'), (int) $letter->operations()->max('draft_revision')) + 1;
            if ($revision > 2147483647) {
                throw ValidationException::withMessages(['lifecycle' => 'Batas versi draft tercapai.']);
            }
            $inputs = $base->snapshot['inputs'] ?? $letter->draft?->form_data;
            if (!is_array($inputs)) {
                throw ValidationException::withMessages(['lifecycle' => 'Snapshot isian versi asal tidak tersedia.']);
            }
            $mapped = $target->id === $base->template_version_id ? ['data' => $inputs, 'changes' => []] : $this->adjustments->map($base->templateVersion, $target, $inputs);
            $snapshot = $this->snapshots->build($target, $mapped['data'], $base->letter_date, $base->snapshot, allowIncomplete: true);
            if (isset($snapshot['values'])) {
                $snapshot['values']['nomor_surat'] = app(LetterSystemFields::class)->exampleNumber($base->assignment->period->letterNumber->classification_code, $base->letter_date);
            }
            $this->staleOperations($letter);
            $letter->draft?->delete();
            $claim = $base->assignment;
            $letter->draft()->create(['base_version_id' => $base->id, 'template_version_id' => $target->id, 'subject' => $base->subject, 'kind' => $kind, 'reason' => null, 'revision' => $revision, 'letter_date' => $base->letter_date, 'numbering_mode' => 2, 'number_year' => $base->number_year, 'number_month' => $base->number_month, 'page_number' => $claim->page_number, 'sequence_number' => $claim->sequence_number, 'form_data' => ['schema_version' => 1, ...$mapped['data']], 'snapshot' => $snapshot, 'updated_by' => $actor->id, 'updater_uuid' => $actor->uuid, 'updater_name' => $actor->name]);
            $this->audit($letter, $actor, $kind === Kind::REVISION ? 'revision.started' : 'recovery.started', $kind === Kind::REVISION ? 'Draft revisi dibuat dari versi resmi terakhir.' : 'Draft pemulihan dibuat; surat tetap berstatus Batal.', ['entries' => $mapped['changes'], 'base_version' => $base->uuid]);
        }, 5);
    }

    public function discard(Letter $letter, array $data, User $actor): void
    {
        DB::transaction(function () use ($letter, $data, $actor): void {
            $letter = $this->lock($letter);
            $this->assertToken($letter, $data);
            if (!$letter->draft?->isWorking() || $letter->draft->kind === Kind::INITIAL) {
                throw ValidationException::withMessages(['lifecycle' => 'Tidak ada draft revisi/pemulihan yang dapat dibuang.']);
            }
            $this->staleOperations($letter);
            $this->audit($letter, $actor, 'draft.discarded', 'Draft revisi/pemulihan dibuang; versi resmi dan riwayat dipertahankan.');
            $letter->draft->delete();
            $letter->touch();
        }, 5);
    }

    public function cancel(Letter $letter, array $data, User $actor): void
    {
        DB::transaction(function () use ($letter, $data, $actor): void {
            $letter = $this->lock($letter);
            if ($letter->status === Status::CANCELLED && $letter->currentVersion?->uuid === $data['base_version']) {
                return;
            }
            $this->assertToken($letter, $data);
            if ($letter->status !== Status::PUBLISHED || $letter->currentVersion?->uuid !== $data['base_version']) {
                throw ValidationException::withMessages(['lifecycle' => 'Surat ini tidak dapat dibatalkan pada status sekarang.']);
            }
            $claim = $letter->currentVersion->assignment;
            LetterNumber::query()->whereKey($claim->period->letter_number_id)->lockForUpdate()->firstOrFail();
            $claim = LetterNumberAssignment::query()->whereKey($claim->id)->lockForUpdate()->firstOrFail();
            if ($claim->released_at !== null) {
                throw ValidationException::withMessages(['lifecycle' => 'Klaim nomor tidak lagi aktif.']);
            }
            $this->staleOperations($letter);
            $this->audit($letter, $actor, 'letter.cancelled', 'Surat dibatalkan: ' . mb_substr(trim($data['reason']), 0, 180), ['reason' => trim($data['reason']), 'version' => $letter->currentVersion->uuid, 'assignment' => $claim->uuid]);
            $letter->draft?->delete();
            $claim->update(['released_at' => now()]);
            $letter->update(['status' => Status::CANCELLED, 'cancelled_at' => now(), 'cancelled_by_uuid' => $actor->uuid, 'cancelled_by_name' => $actor->name, 'cancellation_reason' => trim($data['reason'])]);
            $others = LetterNumberAssignment::query()->where('letter_number_period_id', $claim->letter_number_period_id)->where('page_number', $claim->page_number)->where('sequence_number', $claim->sequence_number)->where('letter_id', '!=', $letter->id)->whereNotNull('released_at')->get()->unique('letter_id');
            foreach ($others as $other) {
                $this->audit($other->letter, $actor, 'number.available', 'Nomor tersedia kembali setelah pembatalan surat “' . mb_substr($letter->subject, 0, 100) . '”.', ['related_letter' => $letter->uuid]);
            }
        }, 5);
    }

    private function assertToken(Letter $letter, array $data): void
    {
        if (!hash_equals($this->token($letter), $data['state_token'])) {
            throw ValidationException::withMessages(['lifecycle' => 'Status atau draft berubah sejak tindakan dibuka. Muat ulang halaman.']);
        }
    }

    private function staleOperations(Letter $letter): void
    {
        foreach ($letter->operations()->whereIn('kind', ['preview', 'issue', 'pdf'])->whereNotNull('letter_draft_id')->whereIn('status', [OperationStatus::QUEUED, OperationStatus::RUNNING])->orderBy('id')->lockForUpdate()->get() as $operation) {
            $this->events->transition($operation, OperationStatus::STALE, 'Draft ditutup. Hasil pekerjaan ini tidak akan dipasang.', ['lease_token' => null, 'lease_expires_at' => null]);
        }
    }

    private function audit(Letter $letter, User $actor, string $event, string $description, array $changes = []): void
    {
        $letter->audits()->create(['actor_uuid' => $actor->uuid, 'actor_name' => $actor->name, 'event' => $event, 'description' => mb_substr($description, 0, 255), 'draft_revision' => $letter->draft?->revision ?? 0, 'changes' => ['schema_version' => 1, 'entries' => [], ...$changes], 'occurred_at' => now()]);
    }

    public function detail(Letter $letter): array
    {
        $claim = $letter->currentVersion?->assignment;
        $owner = $letter->status === Status::CANCELLED && $claim !== null ? LetterNumberAssignment::query()->where('letter_number_period_id', $claim->letter_number_period_id)->where('page_number', $claim->page_number)->where('sequence_number', $claim->sequence_number)->whereNull('released_at')->where('letter_id', '!=', $letter->id)->with('letter')->first() : null;

        return ['number_suggestion' => $owner === null || $claim === null ? null : app(AllocateLetterNumber::class)->suggestAfter($claim, $letter->currentVersion->letter_date), 'state_token' => $this->token($letter), 'base_version' => $letter->currentVersion?->uuid, 'has_draft' => $letter->draft?->isWorking() ?? false, 'draft_kind' => $letter->draft?->isWorking() ? $letter->draft->kind->value : null, 'cancelled_at' => $letter->cancelled_at?->toIso8601String(), 'cancelled_by' => $letter->cancelled_by_name, 'reason' => $letter->cancellation_reason, 'number_owner' => $owner === null ? null : ['id' => $owner->letter->uuid, 'subject' => $owner->letter->subject]];
    }
}
