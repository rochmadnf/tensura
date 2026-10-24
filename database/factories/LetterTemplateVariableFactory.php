<?php

namespace Database\Factories;

use App\Enums\LetterTemplateVariableTypeEnum;
use App\Models\MasterData\LetterTemplateVariable;
use App\Models\MasterData\LetterTemplateVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LetterTemplateVariable> */
class LetterTemplateVariableFactory extends Factory
{
    protected $model = LetterTemplateVariable::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['version_id' => LetterTemplateVersion::factory(), 'name' => fake()->unique()->lexify('variabel_????????'), 'label' => 'Keterangan', 'type' => LetterTemplateVariableTypeEnum::SHORT_TEXT, 'is_required' => false, 'sort_order' => 0, 'option_source' => null, 'reference_group_id' => null, 'is_multiple' => false];
    }
}
