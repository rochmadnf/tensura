<?php

namespace App\Http\Resources\MasterData;

use App\Enums\LetterTemplateVersionStatusEnum;
use App\Models\MasterData\LetterTemplateAudit;
use App\Models\MasterData\LetterTemplateVersion;
use App\Services\LetterTemplate\LetterTemplatePublicationReadiness;
use App\Services\LetterTemplate\LetterTemplateVersionState;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LetterTemplateVersion */
class LetterTemplateLifecycleResource extends JsonResource
{
    /**
     * @return array{
     *   id: string, number: int, timezone: string, status: int, status_label: string, is_current: bool, editable: bool,
     *   published_at: ?string, first_used_at: ?string, classification: array{id: string, code: string, name: string},
     *   version_token: string, readiness: array{ready: bool, checks: list<array{key: string, label: string, passed: bool, message: string}>},
     *   can_start_draft: bool, draft_mode: string, can_archive: bool, draft: ?array{id: string, number: int},
     *   versions: list<array{id: string, number: int, status: int, status_label: string, is_current: bool, created_at: ?string, published_at: ?string, first_used_at: ?string}>,
     *   audits: list<array{id: string, version: ?int, actor: string, occurred_at: string, operation: string, description: string}>
     * }
     */
    public function toArray(Request $request): array
    {
        $template = $this->template;
        $editable = $this->status === LetterTemplateVersionStatusEnum::DRAFT && $this->first_used_at === null;
        $classification = $this->letterNumber;
        $readiness = $editable ? app(LetterTemplatePublicationReadiness::class)->inspect($this->resource) : ['ready' => false, 'checks' => []];

        return [
            'id' => $this->uuid, 'number' => $this->version_number, 'timezone' => config('app.timezone'), 'status' => $this->status->value, 'status_label' => $this->status->label(),
            'is_current' => $template->active_version_id === $this->id, 'editable' => $editable,
            'published_at' => $this->published_at?->toIso8601String(), 'first_used_at' => $this->first_used_at?->toIso8601String(),
            'classification' => ['id' => $classification->uuid, 'code' => $classification->classification_code, 'name' => $classification->classification_name],
            'version_token' => app(LetterTemplateVersionState::class)->token($this->resource),
            'readiness' => $readiness,
            'can_start_draft' => $template->draftVersion === null && in_array($this->status, [LetterTemplateVersionStatusEnum::PUBLISHED, LetterTemplateVersionStatusEnum::ARCHIVED], true),
            'draft_mode' => $this->status === LetterTemplateVersionStatusEnum::PUBLISHED && $this->first_used_at === null ? 'reopen' : 'copy',
            'can_archive' => $editable,
            'draft' => $template->draftVersion === null ? null : ['id' => $template->draftVersion->uuid, 'number' => $template->draftVersion->version_number],
            'versions' => $template->versions->map(fn(LetterTemplateVersion $version): array => [
                'id' => $version->uuid, 'number' => $version->version_number, 'status' => $version->status->value, 'status_label' => $version->status->label(),
                'is_current' => $template->active_version_id === $version->id,
                'created_at' => $version->created_at?->toIso8601String(), 'published_at' => $version->published_at?->toIso8601String(), 'first_used_at' => $version->first_used_at?->toIso8601String(),
            ])->all(),
            'audits' => $template->audits->map(fn(LetterTemplateAudit $audit): array => [
                'id' => $audit->uuid, 'version' => $audit->version?->version_number,
                'actor' => $audit->actor_name, 'occurred_at' => $audit->occurred_at->toIso8601String(),
                'operation' => $audit->operation, 'description' => $audit->description,
            ])->all(),
        ];
    }
}
