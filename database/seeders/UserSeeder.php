<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        collect([
            [
                'username' => 'rochmadnf',
                'name' => 'Rochmad',
                'email' => 'rochmadnf@gmail.com',
                'password' => bcrypt('password'),
            ],
        ])->each(function ($user) {
            \App\Models\MasterData\User::create($user);
        });
    }
}
