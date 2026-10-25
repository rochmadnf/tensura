<?php

namespace App\Models\MasterData;

use Database\Factories\LetterTemplateSignerSlotFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UseFactory(LetterTemplateSignerSlotFactory::class)]
class LetterTemplateSignerSlot extends Model
{
    /** @use HasFactory<LetterTemplateSignerSlotFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['version_id', 'sort_order'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(LetterTemplateVersion::class, 'version_id');
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(LetterTemplateSignerCandidate::class, 'slot_id');
    }

    public function employeeAttributes(): HasMany
    {
        return $this->hasMany(LetterTemplateSignerEmployeeAttribute::class, 'slot_id')->orderBy('source_attribute');
    }
}
