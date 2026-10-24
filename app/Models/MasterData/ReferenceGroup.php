<?php

namespace App\Models\MasterData;

use App\Models\Searchable;
use Database\Factories\ReferenceGroupFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UseFactory(ReferenceGroupFactory::class)]
class ReferenceGroup extends Model
{
    /** @use HasFactory<ReferenceGroupFactory> */
    use HasFactory, HasUuids, Searchable;

    protected $fillable = ['name', 'description'];

    /** @return non-empty-list<string> */
    protected function searchableColumns(): array
    {
        return ['name', 'description'];
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReferenceItem::class, 'reference_group_id')->orderBy('sort_order')->orderBy('id');
    }
}
