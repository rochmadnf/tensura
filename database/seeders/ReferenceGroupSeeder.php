<?php

namespace Database\Seeders;

use App\Models\MasterData\ReferenceGroup;
use Illuminate\Database\Seeder;

class ReferenceGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        collect([
            [
                'id' => 1,
                'uuid' => '019fce18-328e-71fa-bd7e-05e2d481ea19',
                'name' => 'Jenis Kelamin',
                'description' => null,
            ],
            [
                'id' => 2,
                'uuid' => '019fce18-3293-7268-9a7f-4be631547064',
                'name' => 'Golongan Darah',
                'description' => null,
            ],
            [
                'id' => 3,
                'uuid' => '019fce18-329a-71b3-b176-490d24e49f5b',
                'name' => 'Status Pernikahan',
                'description' => null,
            ],
            [
                'id' => 4,
                'uuid' => '019fce18-329e-7055-8b23-71b187d7ec7f',
                'name' => 'Agama',
                'description' => null,
            ],
        ])->each(function ($item) {
            $model = ReferenceGroup::firstOrNew([
                'id' => $item['id'],
            ]);

            $model->forceFill($item)->save();
        });
    }
}
