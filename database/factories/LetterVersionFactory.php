<?php

namespace Database\Factories;

use App\Models\LetterNumberAssignment;
use App\Models\LetterOperation;
use App\Models\LetterVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LetterVersion> */
class LetterVersionFactory extends Factory
{
    public function definition(): array
    {
        return ['letter_operation_id' => LetterOperation::factory()->state(['kind' => 'issue']), 'letter_id' => fn(array $attributes) => LetterOperation::findOrFail($attributes['letter_operation_id'])->letter_id, 'template_version_id' => fn(array $attributes) => LetterOperation::findOrFail($attributes['letter_operation_id'])->draft->template_version_id, 'letter_number_assignment_id' => fn(array $attributes) => LetterNumberAssignment::factory()->create(['letter_id' => $attributes['letter_id']])->id, 'version_number' => 1, 'subject' => fake()->sentence(), 'number' => '445/01.01/PKM-KMJ/X/2026', 'letter_date' => '2026-10-26', 'number_year' => 2026, 'number_month' => 10, 'snapshot' => ['schema_version' => 1, 'values' => [], 'rows' => []], 'inspection' => ['schema_version' => 1], 'actor_uuid' => fake()->uuid(), 'actor_name' => fake()->name(), 'issued_at' => now()];
    }
}
