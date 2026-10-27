<?php

namespace Database\Factories;

use App\Models\LetterArtifact;
use App\Models\LetterOperation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LetterArtifact> */
class LetterArtifactFactory extends Factory
{
    public function definition(): array
    {
        return ['letter_operation_id' => LetterOperation::factory(), 'letter_id' => fn(array $attributes) => LetterOperation::findOrFail($attributes['letter_operation_id'])->letter_id, 'letter_draft_id' => fn(array $attributes) => LetterOperation::findOrFail($attributes['letter_operation_id'])->letter_draft_id, 'kind' => 'docx', 'draft_revision' => 1, 'state_fingerprint' => hash('sha256', 'state'), 'disk' => 'local', 'path' => 'letters/previews/' . fake()->uuid() . '.docx', 'sha256' => hash('sha256', 'docx'), 'size' => 1, 'font_fingerprint' => hash('sha256', 'font'), 'font_usage' => ['schema_version' => 1, 'requirements' => [], 'limitations' => []], 'expires_at' => now()->addDay()];
    }
}
