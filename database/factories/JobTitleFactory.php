<?php

namespace Database\Factories;

use App\Enums\JobTitleGroupEnum;
use App\Models\MasterData\JobTitle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobTitle>
 */
class JobTitleFactory extends Factory
{
    protected $model = JobTitle::class;

    /**
     * Define the model's default state.
     *
     * @return array{name: string, group: JobTitleGroupEnum}
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->numerify('Jabatan #####'),
            'group' => fake()->randomElement(JobTitleGroupEnum::cases()),
        ];
    }
}
