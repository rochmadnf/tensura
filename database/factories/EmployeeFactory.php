<?php

namespace Database\Factories;

use App\Enums\AsnStatusEnum;
use App\Enums\ContractStatusEnum;
use App\Enums\StrStatusEnum;
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
            'gender' => fake()->randomElement([Employee::LAKI_LAKI, Employee::PEREMPUAN]),
            'front_title' => null,
            'back_title' => null,
            'job_title_id' => JobTitle::factory(),
            'rank' => null,
            'grade' => null,
            'join_date' => fake()->dateTimeBetween('-30 years', 'today')->format('Y-m-d'),
            'str_number' => null,
            'str_status' => null,
            'str_published_date' => null,
            'sip_number' => null,
            'sip_published_date' => null,
            'sip_expired_date' => null,
            'email' => fake()->unique()->safeEmail(),
            'contract_status' => ContractStatusEnum::PNS,
            'status' => AsnStatusEnum::AKTIF,
        ];
    }

    public function withStr(): static
    {
        return $this->state(
            fn(array $attributes): array => [
                'str_number' => fake()->unique()->numerify('################'),
                'str_status' => fake()->randomElement(StrStatusEnum::cases()),
                'str_published_date' => today()->subYear()->toDateString(),
            ],
        );
    }

    public function withSip(): static
    {
        return $this->state(
            fn(array $attributes): array => [
                'sip_number' => fake()->unique()->bothify('SIP/####/????'),
                'sip_published_date' => today()->subYear()->toDateString(),
                'sip_expired_date' => today()->addYears(4)->toDateString(),
            ],
        );
    }
}
