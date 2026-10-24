<?php

namespace App\Models\MasterData;

use Database\Factories\LetterTemplateSignerCandidateFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(LetterTemplateSignerCandidateFactory::class)]
class LetterTemplateSignerCandidate extends Model
{
    /** @use HasFactory<LetterTemplateSignerCandidateFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['slot_id', 'employee_id'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(LetterTemplateSignerSlot::class, 'slot_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
