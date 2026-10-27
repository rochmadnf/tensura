<?php

namespace Database\Factories;

use App\Models\Letter;
use App\Models\LetterAudit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LetterAudit> */
class LetterAuditFactory extends Factory
{
    public function definition(): array
    {
        return ['letter_id' => Letter::factory(), 'actor_uuid' => fake()->uuid(), 'actor_name' => fake()->name(), 'event' => 'draft.saved', 'description' => 'Draft disimpan.', 'draft_revision' => 1, 'changes' => ['schema_version' => 1, 'entries' => []], 'occurred_at' => now()];
    }
}
