<?php

namespace App\Models\MasterData;

use Database\Factories\LetterTemplateAuditFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(LetterTemplateAuditFactory::class)]
class LetterTemplateAudit extends Model
{
    /** @use HasFactory<LetterTemplateAuditFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['letter_template_id', 'version_id', 'actor_id', 'actor_uuid', 'actor_name', 'occurred_at', 'operation', 'description', 'changes', 'validation_details'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'changes' => 'array',
            'validation_details' => 'array',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(LetterTemplate::class, 'letter_template_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(LetterTemplateVersion::class, 'version_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
