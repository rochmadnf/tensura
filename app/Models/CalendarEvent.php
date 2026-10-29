<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CalendarEvent extends Model
{
    use HasFactory;
    use HasUuids;

    protected $guarded = ['id', 'uuid'];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return ['revision' => 'integer', 'all_day' => 'boolean', 'imported_at' => 'immutable_datetime'];
    }

    public function publicData(array $revisions = []): array
    {
        return ['id' => $this->uuid, ...$this->only(['revision', 'title', 'kind', 'start_date', 'end_date', 'all_day', 'start_time', 'end_time', 'location', 'description', 'origin', 'source_url']), 'imported_at' => $this->imported_at?->toIso8601String(), 'calendar_revisions' => (object) $revisions];
    }
}
