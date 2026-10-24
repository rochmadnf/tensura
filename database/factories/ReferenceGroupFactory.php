<?php

namespace Database\Factories;

use App\Models\MasterData\ReferenceGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ReferenceGroup> */
class ReferenceGroupFactory extends Factory
{
    protected $model = ReferenceGroup::class;

    /** @return array{name: string, description: string|null} */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->numerify('Referensi ########'),
            'description' => null,
        ];
    }
}
