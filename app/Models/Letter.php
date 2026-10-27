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

    protected $fillable = ['cancelled_at', 'cancelled_by_uuid', 'cancelled_by_name', 'cancellation_reason', 'subject', 'letter_template_id', 'created_by', 'creator_uuid', 'creator_name', 'status', 'official_number', 'official_date', 'current_version_id'];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return ['cancelled_at' => 'immutable_datetime', 'status' => LetterStatusEnum::class, 'official_date' => 'immutable_date'];
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

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(LetterVersion::class, 'current_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(LetterVersion::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(LetterNumberAssignment::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(LetterAudit::class);
    }

    public function artifacts(): HasMany
    {
        return $this->hasMany(LetterArtifact::class);
    }

    public function operations(): HasMany
    {
        return $this->hasMany(LetterOperation::class);
    }
}
