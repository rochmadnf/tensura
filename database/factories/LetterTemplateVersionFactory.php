<?php

namespace Database\Factories;

use App\Enums\LetterTemplateVersionStatusEnum;
use App\Models\MasterData\LetterTemplate;
use App\Models\MasterData\LetterTemplateVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LetterTemplateVersion> */
class LetterTemplateVersionFactory extends Factory
{
    protected $model = LetterTemplateVersion::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['letter_template_id' => LetterTemplate::factory(), 'version_number' => 1, 'letter_number_id' => LetterNumberFactory::new(), 'status' => LetterTemplateVersionStatusEnum::DRAFT];
    }
}
