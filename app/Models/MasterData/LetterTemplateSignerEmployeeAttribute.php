<?php

namespace App\Models\MasterData;

use Database\Factories\LetterTemplateSignerEmployeeAttributeFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(LetterTemplateSignerEmployeeAttributeFactory::class)]
class LetterTemplateSignerEmployeeAttribute extends Model
{
    /** @use HasFactory<LetterTemplateSignerEmployeeAttributeFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['slot_id', 'source_attribute', 'default_text', 'date_format'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(LetterTemplateSignerSlot::class, 'slot_id');
    }
}
