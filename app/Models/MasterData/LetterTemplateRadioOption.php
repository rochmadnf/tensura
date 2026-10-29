<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LetterTemplateRadioOption extends Model
{
    use HasUuids;

    protected $fillable = ['code', 'label', 'sort_order'];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function variable(): BelongsTo
    {
        return $this->belongsTo(LetterTemplateVariable::class, 'variable_id');
    }
}
