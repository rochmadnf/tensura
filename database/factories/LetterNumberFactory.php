<?php

namespace Database\Factories;

use App\Models\LetterNumber;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Schema;

/**
 * @extends Factory<LetterNumber>
 */
class LetterNumberFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterMaking(function (LetterNumber $number): void {
            if (!Schema::hasColumn('letter_numbers', 'page_number')) {
                unset($number->page_number, $number->sequence_number);
            }
        });
    }

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
