<?php

namespace App\Models\MasterData;

use App\Enums\AsnStatusEnum;
use App\Enums\ContractStatusEnum;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasUuids;
    protected $fillable = [
        'nik',
        'nip',
        'name',
        'front_title',
        'back_title',
        'job_title_id',
        'rank', // pangkat
        'grade', // golongan
        'email',
        'contract_status', // 'PNS', 'PPPK', 'PPPK Paruh Waktu', 'Kontrak BLUD'
        'status',
    ];

    public function uniqueIds()
    {
        return ['uuid'];
    }

    public function casts()
    {
        return [
            'contract_status' => ContractStatusEnum::class,
            'status' => AsnStatusEnum::class,
        ];
    }

    public function jobTitle()
    {
        return $this->belongsTo(JobTitle::class, 'job_title_id', 'id');
    }
}
