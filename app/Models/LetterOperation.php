<?php

namespace App\Models;

use App\Enums\LetterOperationStatusEnum;
use Database\Factories\LetterOperationFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[UseFactory(LetterOperationFactory::class)]
class LetterOperation extends Model
{
    /** @use HasFactory<LetterOperationFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['letter_id', 'letter_draft_id', 'source_artifact_id', 'actor_uuid', 'actor_name', 'kind', 'idempotency_key', 'request_hash', 'draft_revision', 'status', 'status_revision', 'attempts', 'lease_token', 'lease_expires_at', 'finished_at', 'message', 'payload'];

    protected function casts(): array
    {
        return ['status' => LetterOperationStatusEnum::class, 'draft_revision' => 'integer', 'status_revision' => 'integer', 'attempts' => 'integer', 'lease_expires_at' => 'immutable_datetime', 'finished_at' => 'immutable_datetime', 'payload' => 'array'];
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

    public function artifact(): HasOne
    {
        return $this->hasOne(LetterArtifact::class);
    }

    public function sourceArtifact(): BelongsTo
    {
        return $this->belongsTo(LetterArtifact::class, 'source_artifact_id');
    }
}
