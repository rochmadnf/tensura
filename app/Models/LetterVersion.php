<?php

namespace App\Models;

use App\Models\MasterData\LetterTemplateVersion;
use Database\Factories\LetterVersionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(LetterVersionFactory::class)]
class LetterVersion extends Model
{
    /** @use HasFactory<LetterVersionFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['reason', 'letter_id', 'version_number', 'template_version_id', 'letter_number_assignment_id', 'letter_operation_id', 'word_artifact_id', 'subject', 'number', 'letter_date', 'number_year', 'number_month', 'snapshot', 'inspection', 'actor_uuid', 'actor_name', 'issued_at'];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return ['version_number' => 'integer', 'number_year' => 'integer', 'number_month' => 'integer', 'snapshot' => 'array', 'inspection' => 'array', 'letter_date' => 'immutable_date', 'issued_at' => 'immutable_datetime'];
    }

    public function letter(): BelongsTo
    {
        return $this->belongsTo(Letter::class);
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(LetterTemplateVersion::class, 'template_version_id');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(LetterNumberAssignment::class, 'letter_number_assignment_id');
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(LetterOperation::class, 'letter_operation_id');
    }

    public function wordArtifact(): BelongsTo
    {
        return $this->belongsTo(LetterArtifact::class, 'word_artifact_id');
    }
}
