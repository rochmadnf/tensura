<?php

namespace Database\Factories;

use App\Models\MasterData\LetterTemplateSignerSlot;
use App\Models\MasterData\LetterTemplateVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LetterTemplateSignerSlot> */
class LetterTemplateSignerSlotFactory extends Factory
{
    protected $model = LetterTemplateSignerSlot::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['version_id' => LetterTemplateVersion::factory(), 'sort_order' => 1];
    }
}
