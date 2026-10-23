<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferenceItem extends Model
{
    use HasUuids;

    protected $fillable = ['reference_group_id', 'content'];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ReferenceGroup::class, 'reference_group_id');
    }
}
