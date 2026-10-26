<?php

namespace App\Models;

use Database\Factories\LetterNumberAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UseFactory(LetterNumberAssignmentFactory::class)]
class LetterNumberAssignment extends Model
{
    /** @use HasFactory<LetterNumberAssignmentFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['letter_id', 'letter_number_period_id', 'page_number', 'sequence_number', 'mode', 'claimed_at', 'released_at'];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return ['page_number' => 'integer', 'sequence_number' => 'integer', 'mode' => 'integer', 'claimed_at' => 'immutable_datetime', 'released_at' => 'immutable_datetime'];
    }

    public function letter(): BelongsTo
    {
        return $this->belongsTo(Letter::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(LetterVersion::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(LetterNumberPeriod::class, 'letter_number_period_id');
    }
}
