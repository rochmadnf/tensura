<?php

namespace Database\Factories;

use App\Enums\LetterTemplateOptionSourceEnum;
use App\Enums\LetterTemplateVariableTypeEnum;
use App\Models\MasterData\LetterTemplateVariable;
use App\Models\MasterData\LetterTemplateVariableColumn;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LetterTemplateVariableColumn> */
class LetterTemplateVariableColumnFactory extends Factory
{
    protected $model = LetterTemplateVariableColumn::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['variable_id' => LetterTemplateVariable::factory()->state(['type' => LetterTemplateVariableTypeEnum::COMBOBOX, 'option_source' => LetterTemplateOptionSourceEnum::EMPLOYEE, 'is_multiple' => true]), 'source_attribute' => 'name', 'placeholder' => fake()->unique()->lexify('pegawai_????????'), 'sort_order' => 0];
    }
}
