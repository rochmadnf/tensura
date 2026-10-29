<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkCalendarYear extends Model
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
        return ['working_weekdays' => 'array', 'year' => 'integer', 'revision' => 'integer', 'work_revision' => 'integer', 'confirmed_work_revision' => 'integer', 'confirmed_at' => 'immutable_datetime'];
    }

    public function ready(): bool
    {
        return $this->confirmed_work_revision === $this->work_revision;
    }

    public function publicData(): array
    {
        return ['id' => $this->uuid, 'year' => $this->year, 'revision' => $this->revision, 'work_revision' => $this->work_revision, 'status' => $this->ready() ? 'ready' : 'draft', 'working_weekdays' => $this->working_weekdays, 'confirmed_at' => $this->confirmed_at?->toIso8601String(), 'confirmed_by' => $this->confirmed_by_uuid === null ? null : ['id' => $this->confirmed_by_uuid, 'name' => $this->confirmed_by_name]];
    }
}
