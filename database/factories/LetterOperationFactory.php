<?php

namespace Database\Factories;

use App\Models\LetterDraft;
use App\Models\LetterOperation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LetterOperation> */
class LetterOperationFactory extends Factory
{
    public function definition(): array
    {
        return ['letter_draft_id' => LetterDraft::factory(), 'letter_id' => fn(array $attributes) => LetterDraft::findOrFail($attributes['letter_draft_id'])->letter_id, 'actor_uuid' => fake()->uuid(), 'actor_name' => fake()->name(), 'kind' => 'preview', 'idempotency_key' => fake()->uuid(), 'request_hash' => hash('sha256', 'request'), 'draft_revision' => 1, 'status' => 1, 'status_revision' => 1, 'attempts' => 0, 'payload' => ['schema_version' => 1]];
    }
}
