<?php

namespace Database\Factories;

use App\Enums\ArchiveKindEnum;
use App\Models\Archive;
use App\Services\Archive\ReferenceSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Archive> */
class ArchiveFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'module' => 'reference',
            'kind' => ArchiveKindEnum::PACKAGE,
            'entity' => ReferenceSnapshot::GROUP,
            'label' => fake()->words(3, true),
            'archived_by' => UserFactory::new(),
            'archived_by_name' => fake()->name(),
            'archived_at' => now(),
            'revision' => 1,
            'context' => ['parent' => null, 'target' => null, 'parent_resolved' => false],
        ];
    }
}
