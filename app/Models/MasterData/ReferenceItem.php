<?php

namespace App\Models\MasterData;

use Database\Factories\ReferenceItemFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(ReferenceItemFactory::class)]
class ReferenceItem extends Model
{
    /** @use HasFactory<ReferenceItemFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['reference_group_id', 'content', 'sort_order'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ReferenceGroup::class, 'reference_group_id');
    }
}
