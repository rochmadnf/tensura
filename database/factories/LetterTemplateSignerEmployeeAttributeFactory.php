<?php

namespace Database\Factories;

use App\Models\MasterData\LetterTemplateSignerEmployeeAttribute;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LetterTemplateSignerEmployeeAttribute> */
class LetterTemplateSignerEmployeeAttributeFactory extends Factory
{
    protected $model = LetterTemplateSignerEmployeeAttribute::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['slot_id' => LetterTemplateSignerSlotFactory::new(), 'source_attribute' => 'nip', 'default_text' => '-', 'date_format' => null];
    }
}
