<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

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
}
