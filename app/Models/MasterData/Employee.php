<?php

namespace App\Models\MasterData;

use App\Enums\AsnStatusEnum;
use App\Enums\ContractStatusEnum;
use App\Enums\StrStatusEnum;
use App\Models\Searchable;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(EmployeeFactory::class)]
class Employee extends Model
{
    public const int LAKI_LAKI = 1;

    public const int PEREMPUAN = 2;

    /** @use HasFactory<EmployeeFactory> */
    use HasFactory, HasUuids, Searchable;

    protected $fillable = [
        'nik',
        'nip',
        'name',
        'gender',
        'front_title',
        'back_title',
        'job_title_id',
        'rank',
        'grade',
        'join_date',
        'str_number',
        'str_status',
        'str_published_date',
        'sip_number',
        'sip_published_date',
        'sip_expired_date',
        'email',
        'contract_status',
        'status',
    ];

    /** @return non-empty-list<string> */
    protected function searchableColumns(): array
    {
        return ['name', 'nik', 'nip', 'email'];
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /** @return list<array{id: int, label: string}> */
    public static function genderOptions(): array
    {
        return [['id' => self::LAKI_LAKI, 'label' => 'Laki-laki'], ['id' => self::PEREMPUAN, 'label' => 'Perempuan']];
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'contract_status' => ContractStatusEnum::class,
            'status' => AsnStatusEnum::class,
            'join_date' => 'date',
            'gender' => 'integer',
            'str_status' => StrStatusEnum::class,
            'str_published_date' => 'date',
            'sip_published_date' => 'date',
            'sip_expired_date' => 'date',
        ];
    }

    /** @return BelongsTo<JobTitle, $this> */
    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class, 'job_title_id', 'id');
    }
}
