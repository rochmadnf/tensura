<?php

namespace Database\Factories;

use App\Enums\LetterStatusEnum;
use App\Models\Letter;
use App\Models\MasterData\LetterTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Letter> */
class LetterFactory extends Factory
{
    public function definition(): array
    {
        return ['subject' => fake()->sentence(4), 'letter_template_id' => LetterTemplate::factory(), 'created_by' => null, 'creator_uuid' => fake()->uuid(), 'creator_name' => fake()->name(), 'status' => LetterStatusEnum::DRAFT];
    }
}
