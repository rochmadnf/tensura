<?php

namespace Database\Factories;

use App\Enums\AsnStatusEnum;
use App\Models\MasterData\Employee;
use App\Models\MasterData\LetterTemplateSignerCandidate;
use App\Models\MasterData\LetterTemplateSignerSlot;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LetterTemplateSignerCandidate> */
class LetterTemplateSignerCandidateFactory extends Factory
{
    protected $model = LetterTemplateSignerCandidate::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['slot_id' => LetterTemplateSignerSlot::factory(), 'employee_id' => Employee::factory()->state(['status' => AsnStatusEnum::AKTIF])];
    }
}
