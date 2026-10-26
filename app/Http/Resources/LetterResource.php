<?php

namespace App\Http\Resources;

use App\Enums\LetterDraftKindEnum;
use App\Enums\LetterStatusEnum;
use App\Models\Letter;
use App\Services\Letter\LetterFormDefinition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Letter */
class LetterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $draft = $this->draft;
        $detail = $request->routeIs('ltr.show', 'ltr.edit');
        $editable = $this->status === LetterStatusEnum::DRAFT && $draft?->kind === LetterDraftKindEnum::INITIAL;
        $current = $draft?->template_version_id === $this->template->active_version_id;
        $reasons = [];
        if (!$this->template->is_active || $this->template->active_version_id === null) {
            $reasons[] = 'Templat nonaktif atau belum memiliki versi aktif. Penerbitan tertahan.';
        }
        if (!$current) {
            $reasons[] = 'Versi templat aktif berubah. Sesuaikan draft secara eksplisit sebelum pemeriksaan/penerbitan.';
        }
        if (!($draft?->snapshot['complete'] ?? false)) {
            $reasons[] = 'Lengkapi isian yang berubah atau ditambahkan pada versi templat.';
        }

        return [
            'id' => $this->uuid, 'subject' => $this->subject, 'number' => $this->official_number,
            'template' => ['id' => $this->template->uuid, 'name' => $this->template->name, 'active' => $this->template->is_active],
            'letter_date' => $draft?->letter_date->toDateString(), 'status' => $this->status->value, 'status_label' => $this->status->label(),
            'creator' => $this->creator_name, 'creator_id' => $this->creator_uuid, 'updated_at' => $this->updated_at->toIso8601String(),
            'revision' => $draft?->revision, 'editable' => $editable,
            'draft' => $this->when($detail && $draft !== null, fn(): array => [
                'id' => $draft->uuid, 'revision' => $draft->revision, 'template_version' => $draft->templateVersion->uuid, 'version_number' => $draft->templateVersion->version_number,
                'letter_date' => $draft->letter_date->toDateString(), 'numbering_mode' => $draft->numbering_mode->value, 'number_year' => $draft->number_year, 'number_month' => $draft->number_month, 'page_number' => $draft->page_number, 'sequence_number' => $draft->sequence_number,
                'values' => $draft->form_data['values'], 'signers' => $draft->form_data['signers'], 'complete' => $draft->snapshot['complete'] ?? false,
                'resolved' => array_filter($draft->snapshot['values'] ?? [], fn(string $token): bool => in_array(preg_replace('/#\d+$/', '', $token), $draft->templateVersion->validation_result['placeholders'] ?? [], true), ARRAY_FILTER_USE_KEY), 'updated_by' => $draft->updater_name,
                'current_template' => $current, 'can_adjust' => $editable && !$current && $this->template->is_active && $this->template->active_version_id !== null,
                'can_refresh' => $editable && ($draft->snapshot['complete'] ?? false), 'reasons' => $reasons,
            ]),
            'configuration' => $this->when($detail && $draft !== null, fn(): array => app(LetterFormDefinition::class)->make($draft->templateVersion, $draft->snapshot)),
        ];
    }
}
