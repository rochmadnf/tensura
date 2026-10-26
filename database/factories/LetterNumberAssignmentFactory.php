<?php

namespace Database\Factories;

use App\Models\Letter;
use App\Models\LetterNumberAssignment;
use App\Models\LetterNumberPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LetterNumberAssignment> */
class LetterNumberAssignmentFactory extends Factory
{
    public function definition(): array
    {
        return ['letter_id' => Letter::factory(), 'letter_number_period_id' => LetterNumberPeriod::factory(), 'page_number' => 1, 'sequence_number' => 1, 'mode' => 1, 'claimed_at' => now(), 'released_at' => null];
    }
}
