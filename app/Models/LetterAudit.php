<?php

namespace App\Models;

use Database\Factories\LetterAuditFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(LetterAuditFactory::class)]
class LetterAudit extends Model
{
    /** @use HasFactory<LetterAuditFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['letter_id', 'actor_id', 'actor_uuid', 'actor_name', 'event', 'description', 'draft_revision', 'changes', 'occurred_at'];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return ['draft_revision' => 'integer', 'changes' => 'array', 'occurred_at' => 'datetime'];
    }

    public function letter(): BelongsTo
    {
        return $this->belongsTo(Letter::class);
    }
}
