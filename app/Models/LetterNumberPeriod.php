<?php

namespace App\Models;

use Database\Factories\LetterNumberPeriodFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UseFactory(LetterNumberPeriodFactory::class)]
class LetterNumberPeriod extends Model
{
    /** @use HasFactory<LetterNumberPeriodFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['letter_number_id', 'year', 'month', 'last_page_number', 'last_sequence_number'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return ['year' => 'integer', 'month' => 'integer', 'last_page_number' => 'integer', 'last_sequence_number' => 'integer'];
    }

    public function letterNumber(): BelongsTo
    {
        return $this->belongsTo(LetterNumber::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(LetterNumberAssignment::class);
    }

    /** @return array{page: int, sequence: int}|null */
    public function nextPosition(): ?array
    {
        if ($this->last_page_number === null) {
            return ['page' => 1, 'sequence' => 1];
        }
        if ($this->last_sequence_number < 99) {
            return ['page' => $this->last_page_number, 'sequence' => $this->last_sequence_number + 1];
        }

        return $this->last_page_number === 2147483647 ? null : ['page' => $this->last_page_number + 1, 'sequence' => 1];
    }
}
