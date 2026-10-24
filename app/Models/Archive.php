<?php

namespace App\Models;

use App\Enums\ArchiveKindEnum;
use App\Models\MasterData\User;
use Database\Factories\ArchiveFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UseFactory(ArchiveFactory::class)]
class Archive extends Model
{
    /** @use HasFactory<ArchiveFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['module', 'kind', 'entity', 'label', 'archived_by', 'archived_by_name', 'archived_at', 'revision', 'context'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['kind' => ArchiveKindEnum::class, 'context' => 'array', 'revision' => 'integer', 'archived_at' => 'immutable_datetime'];
    }

    /** @return HasMany<ArchiveRecord, $this> */
    public function records(): HasMany
    {
        return $this->hasMany(ArchiveRecord::class);
    }

    /** @return BelongsTo<User, $this> */
    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }
}
