<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ReferenceItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        collect([
            [
                'id' => 1,
                'uuid' => '019fce18-32af-72e9-aeba-5ddc9dd333d1',
                'reference_group_id' => 1,
                'content' => 'Laki-laki',
            ],
            [
                'id' => 2,
                'uuid' => '019fce18-32b5-71c9-be5e-193ca526e161',
                'reference_group_id' => 1,
                'content' => 'Perempuan',
            ],
            [
                'id' => 3,
                'uuid' => '019fce18-32bc-732c-952d-30e1c69bf769',
                'reference_group_id' => 2,
                'content' => 'A',
            ],
            [
                'id' => 4,
                'uuid' => '019fce18-32c1-70ed-95dd-1530633a891d',
                'reference_group_id' => 2,
                'content' => 'B',
            ],
            [
                'id' => 5,
                'uuid' => '019fce18-32ce-7079-bb80-cfc047f1b281',
                'reference_group_id' => 2,
                'content' => 'AB',
            ],
            [
                'id' => 6,
                'uuid' => '019fce18-32e3-7015-9110-a026c1b93062',
                'reference_group_id' => 2,
                'content' => 'O',
            ],
            [
                'id' => 7,
                'uuid' => '019fce18-32e3-7015-9110-a026c1b93062',
                'reference_group_id' => 3,
                'content' => 'Belum Menikah',
            ],
            [
                'id' => 8,
                'uuid' => '019fce18-32ea-71fc-abbf-5619b60e8ed7',
                'reference_group_id' => 3,
                'content' => 'Menikah',
            ],
            [
                'id' => 9,
                'uuid' => '019fce18-32f1-732f-8eb6-7a47bf222506',
                'reference_group_id' => 3,
                'content' => 'Cerai Hidup',
            ],
            [
                'id' => 10,
                'uuid' => '019fce18-32f7-73e3-a419-224769b95083',
                'reference_group_id' => 3,
                'content' => 'Cerai Mati',
            ],
            [
                'id' => 11,
                'uuid' => '019fce18-3304-73b5-b11a-68587c68037e',
                'reference_group_id' => 4,
                'content' => 'Islam',
            ],
            [
                'id' => 12,
                'uuid' => '019fce18-3312-7340-b7f1-f66345d501a4',
                'reference_group_id' => 4,
                'content' => 'Katolik',
            ],
            [
                'id' => 13,
                'uuid' => '019fce18-330b-70a1-9fa9-a9f7eceb9e6e',
                'reference_group_id' => 4,
                'content' => 'Protestan',
            ],
            [
                'id' => 14,
                'uuid' => '019fce18-3320-7236-99f6-814e442bbb67',
                'reference_group_id' => 4,
                'content' => 'Hindu',
            ],
            [
                'id' => 15,
                'uuid' => '019fce18-3327-7126-904c-3d9f09c544ce',
                'reference_group_id' => 4,
                'content' => 'Buddha',
            ],
            [
                'id' => 16,
                'uuid' => '019fce18-332e-7341-a8c7-58e51d0eeb52',
                'reference_group_id' => 4,
                'content' => 'Konghucu',
            ],
        ])->each(function ($item) {
            $model = \App\Models\MasterData\ReferenceItem::firstOrNew([
                'id' => $item['id'],
            ]);

            $model->forceFill($item)->save();
        });
    }
}
