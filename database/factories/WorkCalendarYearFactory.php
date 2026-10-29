<?php

namespace Database\Factories;

use App\Models\WorkCalendarYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WorkCalendarYear> */
class WorkCalendarYearFactory extends Factory
{
    protected $model = WorkCalendarYear::class;

    public function definition(): array
    {
        return ['year' => fake()->unique()->numberBetween(1900, 2199), 'working_weekdays' => [1, 2, 3, 4, 5, 6], 'revision' => 1, 'work_revision' => 1];
    }

    public function ready(): static
    {
        return $this->state(['confirmed_work_revision' => 1, 'confirmed_at' => now()]);
    }
}
