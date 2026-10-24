<?php

namespace Database\Factories;

use App\Models\MasterData\ReferenceGroup;
use App\Models\MasterData\ReferenceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ReferenceItem> */
class ReferenceItemFactory extends Factory
{
    protected $model = ReferenceItem::class;

    /** @return array{reference_group_id: ReferenceGroupFactory, content: string, sort_order: \Closure} */
    public function definition(): array
    {
        return [
            'reference_group_id' => ReferenceGroup::factory(),
            'content' => fake()->unique()->numerify('Item ########'),
            'sort_order' => fn(array $attributes): int => (int) ReferenceItem::query()
                ->where('reference_group_id', $attributes['reference_group_id'])
                ->max('sort_order') + 1,
        ];
    }
}
