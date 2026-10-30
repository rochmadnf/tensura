<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LetterNumberSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        collect([
        ['classification_name' => 'Surat Tugas', 'classification_code' => '094'],
        ['classification_name' => 'Surat Keluar', 'classification_code' => '445'],
        ])->each(function ($item) {
            \App\Models\LetterNumber::create($item);
        });
    }
}
