<?php

namespace App\Services\Letter;

use App\Enums\LetterDraftKindEnum;
use App\Enums\LetterStatusEnum;
use App\Enums\LetterTemplateVersionStatusEnum;
use App\Models\Letter;
use App\Models\LetterDraft;
use App\Models\MasterData\LetterTemplate;
use App\Models\MasterData\LetterTemplateVersion;
use App\Models\MasterData\User;
use App\Services\LetterTemplate\LetterTemplateConfigurationHash;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ManageLetterDraft
{
    public function __construct(private LetterDraftRules $rules, private LetterDraftSnapshot $snapshots, private LetterDraftState $state, private AdjustLetterDraftTemplate $adjustments, private LetterTemplateConfigurationHash $configurationHash) {}

    /** @param array{template: string, template_version: string, subject: string, letter_date: string, numbering_mode: int, number_year: int, number_month: int, page_number?: int, sequence_number?: int, values: array<string, string|list<string>|null>, signers: array<string, string>} $data */
    public function create(array $data, User $actor): Letter
    {
        return DB::transaction(function () use ($data, $actor): Letter {
            $template = LetterTemplate::query()->where('uuid', $data['template'])->sharedLock()->first();
            if ($template === null || !$template->is_active || $template->active_version_id === null) {
                throw ValidationException::withMessages(['template' => 'Pilih templat aktif yang memiliki versi terbit.']);
            }
            $version = $template->versions()->whereKey($template->active_version_id)->sharedLock()->firstOrFail();
            if ($version->uuid !== $data['template_version'] || $version->status !== LetterTemplateVersionStatusEnum::PUBLISHED) {
                throw ValidationException::withMessages(['template_version' => 'Versi templat aktif berubah. Pilih kembali templat terbaru.']);
            }
            $data = $this->validate($version, $data);
            $snapshot = $this->snapshot($version, $data);
            $letter = Letter::query()->create(['subject' => $data['subject'], 'letter_template_id' => $template->id, 'created_by' => $actor->id, 'creator_uuid' => $actor->uuid, 'creator_name' => $actor->name, 'status' => LetterStatusEnum::DRAFT]);
            $draft = $letter->draft()->create([...$this->attributes($data, $snapshot, $actor), 'template_version_id' => $version->id, 'kind' => LetterDraftKindEnum::INITIAL, 'revision' => 1]);
            $this->audit($letter, $draft, $actor, 'draft.created', 'Draft surat dibuat dari templat versi ' . $version->version_number . '.', []);

            return $letter;
        }, 5);
    }

    /** @param array{revision: int, template_version: string, subject: string, letter_date: string, numbering_mode: int, number_year: int, number_month: int, page_number?: int, sequence_number?: int, values: array<string, string|list<string>|null>, signers: array<string, string>} $data */
    public function update(Letter $letter, array $data, User $actor): void
    {
        DB::transaction(function () use ($letter, $data, $actor): void {
            [$letter, $draft] = $this->lock($letter, (int) $data['revision']);
            $version = $draft->templateVersion;
            if ($version->uuid !== $data['template_version']) {
                throw ValidationException::withMessages(['template_version' => 'Versi templat draft berubah. Muat versi terbaru sebelum menyimpan.']);
            }
            $data = $this->validate($version, $data);
            $snapshot = $this->snapshot($version, $data, $draft->snapshot);
            $changes = $this->state->changes([
                'Perihal' => $letter->subject, 'Tanggal Surat' => $draft->letter_date->toDateString(),
                'Mode nomor' => $draft->numbering_mode->value === 1 ? 'Otomatis' : 'Manual',
                'Periode nomor' => $draft->number_year . '-' . $draft->number_month,
                'Halaman' => $draft->page_number === null ? null : (string) $draft->page_number,
                'Nomor urut' => $draft->sequence_number === null ? null : (string) $draft->sequence_number,
                ...$draft->snapshot['values'],
            ], [
                'Perihal' => $data['subject'], 'Tanggal Surat' => $data['letter_date'],
                'Mode nomor' => (int) $data['numbering_mode'] === 1 ? 'Otomatis' : 'Manual',
                'Periode nomor' => $data['number_year'] . '-' . $data['number_month'],
                'Halaman' => isset($data['page_number']) ? (string) $data['page_number'] : null,
                'Nomor urut' => isset($data['sequence_number']) ? (string) $data['sequence_number'] : null,
                ...$snapshot['values'],
            ]);
            $letter->subject = $data['subject'];
            $letter->touch();
            $draft->fill([...$this->attributes($data, $snapshot, $actor), 'revision' => $draft->revision + 1])->save();
            $this->audit($letter, $draft, $actor, 'draft.saved', 'Isian draft disimpan; pemeriksaan dokumen perlu diulang.', $changes);
        }, 5);
    }

    public function previewChange(Letter $letter, int $revision, string $kind): array
    {
        return DB::transaction(function () use ($letter, $revision, $kind): array {
            [$letter, $draft] = $this->lock($letter, $revision);
            $plan = $this->plan($letter, $draft, $kind);

            return ['revision' => $draft->revision, 'target_version' => $plan['version']->version_number, 'changes' => $plan['changes'], 'change_token' => $this->changeToken($letter, $draft, $kind, $plan), 'complete' => $plan['snapshot']['complete']];
        }, 5);
    }

    /** @param array{revision: int, change_token: string, confirmed: bool} $data */
    public function applyChange(Letter $letter, array $data, User $actor, string $kind): void
    {
        DB::transaction(function () use ($letter, $data, $actor, $kind): void {
            [$letter, $draft] = $this->lock($letter, (int) $data['revision']);
            $plan = $this->plan($letter, $draft, $kind);
            if (!hash_equals($this->changeToken($letter, $draft, $kind, $plan), $data['change_token'])) {
                throw ValidationException::withMessages(['change_token' => 'Data atau versi templat berubah sejak ringkasan dibuka. Periksa ringkasan terbaru.']);
            }
            $draft->fill(['template_version_id' => $plan['version']->id, 'form_data' => ['schema_version' => 1, ...$plan['data']], 'snapshot' => $plan['snapshot'], 'revision' => $draft->revision + 1, 'updated_by' => $actor->id, 'updater_uuid' => $actor->uuid, 'updater_name' => $actor->name])->save();
            $letter->touch();
            $this->audit($letter, $draft, $actor, $kind === 'template' ? 'template.adjusted' : 'masters.refreshed', $kind === 'template' ? 'Draft disesuaikan ke templat versi ' . $plan['version']->version_number . '; lengkapi isian dan periksa ulang.' : 'Nilai master draft diperbarui secara eksplisit; pemeriksaan perlu diulang.', $plan['changes']);
        }, 5);
    }

    public function destroy(Letter $letter, int $revision): void
    {
        DB::transaction(function () use ($letter, $revision): void {
            [$letter] = $this->lock($letter, $revision);
            $letter->delete();
        }, 5);
    }

    /** Lock ordering: template, letter, draft. Template lifecycle operations lock the same parent first. */
    private function lock(Letter $letter, int $revision): array
    {
        LetterTemplate::query()->whereKey($letter->letter_template_id)->sharedLock()->firstOrFail();
        $letter = Letter::query()->whereKey($letter->id)->lockForUpdate()->firstOrFail();
        $draft = $letter->draft()->lockForUpdate()->firstOrFail();
        $this->state->assertEditable($letter, $draft, $revision);

        return [$letter, $draft];
    }

    private function plan(Letter $letter, LetterDraft $draft, string $kind): array
    {
        $version = $draft->templateVersion;
        $data = ['values' => $draft->form_data['values'], 'signers' => $draft->form_data['signers']];
        $changes = [];
        if ($kind === 'template') {
            $template = $letter->template;
            if (!$template->is_active || $template->active_version_id === null || $template->active_version_id === $version->id) {
                throw ValidationException::withMessages(['draft' => 'Belum ada versi aktif berbeda yang dapat digunakan.']);
            }
            $target = $template->versions()->whereKey($template->active_version_id)->where('status', LetterTemplateVersionStatusEnum::PUBLISHED)->sharedLock()->firstOrFail();
            ['data' => $data, 'changes' => $changes] = $this->adjustments->map($version, $target, $data);
            $version = $target;
        }
        $snapshot = $this->snapshots->build($version, $data, $draft->letter_date, $kind === 'masters' ? [] : $draft->snapshot, allowIncomplete: $kind === 'template');
        if ($kind === 'masters') {
            $changes = $this->state->changes($draft->snapshot['values'] ?? [], $snapshot['values']);
        }

        return ['version' => $version, 'data' => $data, 'snapshot' => $snapshot, 'changes' => $changes];
    }

    private function changeToken(Letter $letter, LetterDraft $draft, string $kind, array $plan): string
    {
        return $this->state->token(['letter' => $letter->uuid, 'revision' => $draft->revision, 'kind' => $kind, 'target' => $plan['version']->uuid, 'configuration' => $this->configurationHash->hash($plan['version']), 'data' => $plan['data'], 'snapshot' => $plan['snapshot'], 'changes' => $plan['changes']]);
    }

    private function validate(LetterTemplateVersion $version, array $data): array
    {
        $validator = Validator::make($data, [...$this->rules->identity(), ...$this->rules->fields($version)], [], $this->rules->attributes($version));
        $validator->after(fn($validator) => $this->rules->checkPeriod($validator, $data));

        return $validator->validate();
    }

    private function snapshot(LetterTemplateVersion $version, array $data, array $previous = []): array
    {
        return $this->snapshots->build($version, ['values' => $data['values'], 'signers' => $data['signers']], CarbonImmutable::parse($data['letter_date'], config('app.timezone')), $previous);
    }

    private function attributes(array $data, array $snapshot, User $actor): array
    {
        return [...collect($data)->only(['letter_date', 'numbering_mode', 'number_year', 'number_month'])->all(), 'page_number' => $data['page_number'] ?? null, 'sequence_number' => $data['sequence_number'] ?? null, 'form_data' => ['schema_version' => 1, 'values' => $data['values'], 'signers' => $data['signers']], 'snapshot' => $snapshot, 'updated_by' => $actor->id, 'updater_uuid' => $actor->uuid, 'updater_name' => $actor->name];
    }

    private function audit(Letter $letter, LetterDraft $draft, User $actor, string $event, string $description, array $changes): void
    {
        $letter->audits()->create(['actor_id' => $actor->id, 'actor_uuid' => $actor->uuid, 'actor_name' => $actor->name, 'event' => $event, 'description' => $description, 'draft_revision' => $draft->revision, 'changes' => ['schema_version' => 1, 'entries' => $changes], 'occurred_at' => now()]);
    }
}
