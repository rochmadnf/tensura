<?php

namespace Database\Factories;

use App\Models\MasterData\LetterTemplate;
use App\Models\MasterData\LetterTemplateAudit;
use App\Models\MasterData\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LetterTemplateAudit> */
class LetterTemplateAuditFactory extends Factory
{
    protected $model = LetterTemplateAudit::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['letter_template_id' => LetterTemplate::factory(), 'version_id' => null, 'actor_id' => UserFactory::new(), 'actor_uuid' => fn(array $attributes) => User::findOrFail($attributes['actor_id'])->uuid, 'actor_name' => fn(array $attributes) => User::findOrFail($attributes['actor_id'])->name, 'occurred_at' => now(), 'operation' => 'draft.created', 'description' => 'Draft awal dibuat.'];
    }
}
