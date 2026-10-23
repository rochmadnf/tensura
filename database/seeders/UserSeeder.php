<?php

namespace Database\Seeders;

use App\Models\MasterData\User;
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
            User::create($user);
        });
    }
}
