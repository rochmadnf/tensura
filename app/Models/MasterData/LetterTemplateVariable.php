<?php

namespace App\Models\MasterData;

use App\Enums\LetterTemplateOptionSourceEnum;
use App\Enums\LetterTemplateVariableTypeEnum;
use Database\Factories\LetterTemplateVariableFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UseFactory(LetterTemplateVariableFactory::class)]
class LetterTemplateVariable extends Model
{
    /** @use HasFactory<LetterTemplateVariableFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['version_id', 'name', 'label', 'type', 'is_required', 'sort_order', 'option_source', 'reference_group_id', 'is_multiple', 'default_text'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return [
            'type' => LetterTemplateVariableTypeEnum::class,
            'option_source' => LetterTemplateOptionSourceEnum::class,
            'is_required' => 'boolean',
            'is_multiple' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(LetterTemplateVersion::class, 'version_id');
    }

    public function referenceGroup(): BelongsTo
    {
        return $this->belongsTo(ReferenceGroup::class, 'reference_group_id');
    }

    public function columns(): HasMany
    {
        return $this->hasMany(LetterTemplateVariableColumn::class, 'variable_id')->orderBy('sort_order')->orderBy('id');
    }

    public function employeeAttributes(): HasMany
    {
        return $this->hasMany(LetterTemplateVariableEmployeeAttribute::class, 'variable_id')->orderBy('source_attribute');
    }
}
