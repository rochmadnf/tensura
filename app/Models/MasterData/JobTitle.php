<?php

namespace App\Models\MasterData;

use App\Enums\JobTitleGroupEnum;
use App\Models\Searchable;
use Database\Factories\JobTitleFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[UseFactory(JobTitleFactory::class)]
class JobTitle extends Model
{
    /** @use HasFactory<JobTitleFactory> */
    use HasFactory, HasUuids, Searchable;

    protected $fillable = ['name', 'group'];

    /** @return non-empty-list<string> */
    protected function searchableColumns(): array
    {
        return ['name'];
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /** @return array{group: class-string<JobTitleGroupEnum>} */
    protected function casts(): array
    {
        return [
            'group' => JobTitleGroupEnum::class,
        ];
    }
}
