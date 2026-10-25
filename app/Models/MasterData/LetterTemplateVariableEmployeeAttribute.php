<?php

namespace App\Models\MasterData;

use Database\Factories\LetterTemplateVariableEmployeeAttributeFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(LetterTemplateVariableEmployeeAttributeFactory::class)]
class LetterTemplateVariableEmployeeAttribute extends Model
{
    /** @use HasFactory<LetterTemplateVariableEmployeeAttributeFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['variable_id', 'source_attribute', 'default_text', 'date_format'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function variable(): BelongsTo
    {
        return $this->belongsTo(LetterTemplateVariable::class, 'variable_id');
    }
}
