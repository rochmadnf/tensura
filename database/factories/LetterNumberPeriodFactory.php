<?php

namespace Database\Factories;

use App\Models\LetterNumberPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LetterNumberPeriod>
 */
class LetterNumberPeriodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'letter_number_id' => LetterNumberFactory::new(),
            'year' => 2026,
            'month' => 10,
            'last_page_number' => null,
            'last_sequence_number' => null,
        ];
    }
}
