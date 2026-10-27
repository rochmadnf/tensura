<?php

namespace App\Models;

use App\Models\MasterData\LetterTemplateVersion;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LetterNumber extends Model
{
    use HasUuids, Searchable;

    protected $fillable = ['classification_name', 'classification_code', 'page_number', 'sequence_number'];

    /** @return non-empty-list<string> */
    protected function searchableColumns(): array
    {
        return ['classification_name', 'classification_code'];
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function templateVersions(): HasMany
    {
        return $this->hasMany(LetterTemplateVersion::class, 'letter_number_id');
    }

    public function periods(): HasMany
    {
        return $this->hasMany(LetterNumberPeriod::class);
    }

    public function needsPeriodInitialization(): bool
    {
        return array_key_exists('page_number', $this->getAttributes()) && $this->initial_period_id === null && ($this->page_number !== null || $this->sequence_number !== null);
    }
}
