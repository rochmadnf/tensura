<?php

namespace App\Models;

use App\Enums\LetterDraftKindEnum;
use App\Enums\LetterNumberingModeEnum;
use App\Enums\LetterStatusEnum;
use App\Models\MasterData\LetterTemplateVersion;
use Database\Factories\LetterDraftFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(LetterDraftFactory::class)]
class LetterDraft extends Model
{
    /** @use HasFactory<LetterDraftFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['base_version_id', 'subject', 'reason', 'letter_id', 'template_version_id', 'kind', 'letter_date', 'numbering_mode', 'number_year', 'number_month', 'page_number', 'sequence_number', 'revision', 'form_data', 'snapshot', 'updated_by', 'updater_uuid', 'updater_name'];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return ['kind' => LetterDraftKindEnum::class, 'numbering_mode' => LetterNumberingModeEnum::class, 'letter_date' => 'immutable_date', 'number_year' => 'integer', 'number_month' => 'integer', 'page_number' => 'integer', 'sequence_number' => 'integer', 'revision' => 'integer', 'form_data' => 'array', 'snapshot' => 'array'];
    }

    public function letter(): BelongsTo
    {
        return $this->belongsTo(Letter::class);
    }

    public function baseVersion(): BelongsTo
    {
        return $this->belongsTo(LetterVersion::class, 'base_version_id');
    }

    public function numberClassification(): LetterNumber
    {
        return $this->baseVersion?->assignment->period->letterNumber ?? $this->templateVersion->letterNumber;
    }

    public function isWorking(): bool
    {
        $letter = $this->letter;

        return match ($this->kind) {
            LetterDraftKindEnum::INITIAL => $letter->status === LetterStatusEnum::DRAFT,
            LetterDraftKindEnum::REVISION => $letter->status === LetterStatusEnum::PUBLISHED && $this->base_version_id === $letter->current_version_id,
            LetterDraftKindEnum::RECOVERY => $letter->status === LetterStatusEnum::CANCELLED && $this->base_version_id === $letter->current_version_id,
        };
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(LetterTemplateVersion::class, 'template_version_id');
    }
}
