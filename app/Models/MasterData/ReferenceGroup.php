<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReferenceGroup extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'description'];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReferenceItem::class, 'reference_group_id');
    }
}
