<?php

namespace App\Models\MasterData;

use Database\Factories\LetterTemplateVariableColumnFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(LetterTemplateVariableColumnFactory::class)]
class LetterTemplateVariableColumn extends Model
{
    /** @use HasFactory<LetterTemplateVariableColumnFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['variable_id', 'source_attribute', 'placeholder', 'sort_order'];

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

    public function variable(): BelongsTo
    {
        return $this->belongsTo(LetterTemplateVariable::class, 'variable_id');
    }
}
