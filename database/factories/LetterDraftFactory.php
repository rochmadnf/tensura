<?php

namespace Database\Factories;

use App\Models\Letter;
use App\Models\LetterDraft;
use App\Models\MasterData\LetterTemplateVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LetterDraft> */
class LetterDraftFactory extends Factory
{
    public function definition(): array
    {
        return ['letter_id' => Letter::factory(), 'template_version_id' => fn(array $attributes) => LetterTemplateVersion::factory()->create(['letter_template_id' => Letter::findOrFail($attributes['letter_id'])->letter_template_id])->id, 'kind' => 1, 'letter_date' => '2026-10-26', 'numbering_mode' => 1, 'number_year' => 2026, 'number_month' => 10, 'revision' => 1, 'form_data' => ['schema_version' => 1, 'values' => [], 'signers' => []], 'snapshot' => ['schema_version' => 1, 'complete' => true, 'values' => [], 'rows' => [], 'masters' => ['employees' => [], 'references' => []]], 'updater_uuid' => fake()->uuid(), 'updater_name' => fake()->name()];
    }
}
