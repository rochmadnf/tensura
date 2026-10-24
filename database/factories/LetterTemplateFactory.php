<?php

namespace Database\Factories;

use App\Models\MasterData\LetterTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LetterTemplate> */
class LetterTemplateFactory extends Factory
{
    protected $model = LetterTemplate::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['name' => fake()->unique()->numerify('Templat ########'), 'is_active' => true];
    }
}
