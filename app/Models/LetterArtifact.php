<?php

namespace App\Models;

use Database\Factories\LetterArtifactFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UseFactory(LetterArtifactFactory::class)]
class LetterArtifact extends Model
{
    /** @use HasFactory<LetterArtifactFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['letter_id', 'letter_draft_id', 'letter_version_id', 'source_artifact_id', 'letter_operation_id', 'kind', 'draft_revision', 'state_fingerprint', 'disk', 'path', 'sha256', 'size', 'font_fingerprint', 'font_usage', 'expires_at'];

    public function sourceArtifact(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_artifact_id');
    }

    protected function casts(): array
    {
        return ['draft_revision' => 'integer', 'size' => 'integer', 'font_usage' => 'array', 'expires_at' => 'immutable_datetime'];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function letter(): BelongsTo
    {
        return $this->belongsTo(Letter::class);
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(LetterDraft::class, 'letter_draft_id');
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(LetterOperation::class, 'letter_operation_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(LetterVersion::class, 'letter_version_id');
    }

    public function checks(): HasMany
    {
        return $this->hasMany(LetterPreviewCheck::class);
    }
}
