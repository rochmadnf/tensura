<?php

namespace Database\Factories;

use App\Enums\AsnStatusEnum;
use App\Enums\ContractStatusEnum;
use App\Models\MasterData\Employee;
use App\Models\MasterData\JobTitle;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Employee> */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'nik' => fake()->unique()->numerify('################'),
            'nip' => fake()->unique()->numerify('##################'),
            'name' => fake()->name(),
            'front_title' => null,
            'back_title' => null,
            'job_title_id' => JobTitle::factory(),
            'rank' => null,
            'grade' => null,
            'join_date' => fake()->dateTimeBetween('-30 years', 'today')->format('Y-m-d'),
            'email' => fake()->unique()->safeEmail(),
            'contract_status' => ContractStatusEnum::PNS,
            'status' => AsnStatusEnum::AKTIF,
        ];
    }
}
