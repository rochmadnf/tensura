<?php

namespace Database\Factories;

use App\Models\LetterNumber;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LetterNumber>
 */
class LetterNumberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'classification_name' => fake()->words(3, true),
            'classification_code' => fake()->unique()->numerify('###'),
            'page_number' => 1,
            'sequence_number' => 1,
        ];
    }
}
