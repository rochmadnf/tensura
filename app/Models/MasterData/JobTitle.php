<?php

namespace App\Models\MasterData;

use App\Enums\JobTitleGroupEnum;
use Illuminate\Database\Eloquent\Model;

class JobTitle extends Model
{
    protected $fillable = ['name', 'group'];

    public function casts()
    {
        return [
            'group' => JobTitleGroupEnum::class,
        ];
    }
}
