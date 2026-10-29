<?php

namespace Database\Factories;

use App\Models\CalendarEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CalendarEvent> */
class CalendarEventFactory extends Factory
{
    protected $model = CalendarEvent::class;

    public function definition(): array
    {
        $date = fake()->dateTimeBetween('2026-01-01', '2026-12-31')->format('Y-m-d');

        return ['revision' => 1, 'title' => fake()->sentence(3), 'kind' => 'activity', 'start_date' => $date, 'end_date' => $date, 'all_day' => true, 'origin' => 'manual'];
    }
}
