<?php

namespace Database\Factories;

use App\Models\LetterArtifact;
use App\Models\LetterPreviewCheck;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LetterPreviewCheck> */
class LetterPreviewCheckFactory extends Factory
{
    public function definition(): array
    {
        return ['letter_artifact_id' => LetterArtifact::factory(), 'actor_uuid' => fake()->uuid(), 'actor_name' => fake()->name(), 'preview_kind' => 'docx', 'fingerprint' => hash('sha256', 'evidence'), 'rendered_at' => now(), 'confirmed_at' => null];
    }
}
