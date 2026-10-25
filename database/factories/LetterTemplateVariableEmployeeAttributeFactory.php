<?php

namespace Database\Factories;

use App\Models\MasterData\LetterTemplateVariableEmployeeAttribute;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LetterTemplateVariableEmployeeAttribute> */
class LetterTemplateVariableEmployeeAttributeFactory extends Factory
{
    protected $model = LetterTemplateVariableEmployeeAttribute::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['variable_id' => LetterTemplateVariableFactory::new(), 'source_attribute' => 'nip', 'default_text' => '-', 'date_format' => null];
    }
}
