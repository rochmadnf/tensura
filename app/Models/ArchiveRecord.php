<?php

namespace App\Models;

use Database\Factories\ArchiveRecordFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(ArchiveRecordFactory::class)]
class ArchiveRecord extends Model
{
    /** @use HasFactory<ArchiveRecordFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['entity', 'source_key', 'source_uuid', 'parent_entity', 'parent_source_key', 'parent_source_uuid', 'label', 'schema_version', 'payload'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['payload' => 'array', 'schema_version' => 'integer'];
    }

    /** @return BelongsTo<Archive, $this> */
    public function archive(): BelongsTo
    {
        return $this->belongsTo(Archive::class);
    }
}
