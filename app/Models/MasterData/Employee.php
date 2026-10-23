<?php

namespace App\Models\MasterData;

use App\Enums\AsnStatusEnum;
use App\Enums\ContractStatusEnum;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(EmployeeFactory::class)]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'nik',
        'nip',
        'name',
        'front_title',
        'back_title',
        'job_title_id',
        'rank',
        'grade',
        'join_date',
        'email',
        'contract_status',
        'status',
    ];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /** @return array{contract_status: class-string<ContractStatusEnum>, status: class-string<AsnStatusEnum>, join_date: string} */
    protected function casts(): array
    {
        return [
            'contract_status' => ContractStatusEnum::class,
            'status' => AsnStatusEnum::class,
            'join_date' => 'date',
        ];
    }

    /** @return BelongsTo<JobTitle, $this> */
    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class, 'job_title_id', 'id');
    }
}
