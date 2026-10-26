<?php

namespace App\Models;

use App\Enums\LetterStatusEnum;
use App\Models\MasterData\LetterTemplate;
use App\Models\MasterData\User;
use Database\Factories\LetterFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[UseFactory(LetterFactory::class)]
class Letter extends Model
{
    /** @use HasFactory<LetterFactory> */
    use HasFactory, HasUuids, Searchable;

    protected $fillable = ['subject', 'letter_template_id', 'created_by', 'creator_uuid', 'creator_name', 'status', 'official_number'];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return ['status' => LetterStatusEnum::class];
    }

    protected function searchableColumns(): array
    {
        return ['subject', 'official_number'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(LetterTemplate::class, 'letter_template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function draft(): HasOne
    {
        return $this->hasOne(LetterDraft::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(LetterAudit::class);
    }
}
