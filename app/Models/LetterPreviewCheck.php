<?php

namespace App\Models;

use Database\Factories\LetterPreviewCheckFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(LetterPreviewCheckFactory::class)]
class LetterPreviewCheck extends Model
{
    /** @use HasFactory<LetterPreviewCheckFactory> */
    use HasFactory;

    protected $fillable = ['letter_artifact_id', 'actor_uuid', 'actor_name', 'preview_kind', 'fingerprint', 'rendered_at', 'confirmed_at'];

    protected function casts(): array
    {
        return ['rendered_at' => 'immutable_datetime', 'confirmed_at' => 'immutable_datetime'];
    }

    public function artifact(): BelongsTo
    {
        return $this->belongsTo(LetterArtifact::class, 'letter_artifact_id');
    }
}
