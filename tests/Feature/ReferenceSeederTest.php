<?php

namespace Tests\Feature;

use App\Models\MasterData\ReferenceGroup;
use App\Models\MasterData\ReferenceItem;
use Database\Seeders\ReferenceGroupSeeder;
use Database\Seeders\ReferenceItemSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReferenceSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_seeded_uuids_are_unique_and_positions_are_sequential_within_each_group(): void
    {
        $this->seed([ReferenceGroupSeeder::class, ReferenceItemSeeder::class]);
        $uuids = ReferenceGroup::query()->pluck('uuid')->concat(ReferenceItem::query()->pluck('uuid'));
        $this->assertCount(20, $uuids);
        $this->assertCount(20, $uuids->unique());
        foreach ($uuids as $uuid) {
            $this->assertTrue(Str::isUuid($uuid));
        }
        $groups = ReferenceGroup::query()->orderBy('id')->with('items')->get();
        $this->assertSame([2, 4, 4, 6], $groups->map(fn(ReferenceGroup $group): int => $group->items->count())->all());
        foreach ($groups as $group) {
            $this->assertSame(range(1, $group->items->count()), $group->items->pluck('sort_order')->all());
        }
    }

    public function test_reseeding_preserves_user_order_and_identifiers(): void
    {
        $this->seed([ReferenceGroupSeeder::class, ReferenceItemSeeder::class]);
        ReferenceItem::query()->where('id', 1)->update(['sort_order' => 2]);
        ReferenceItem::query()->where('id', 2)->update(['sort_order' => 1]);
        $before = ReferenceItem::query()->orderBy('id')->get(['id', 'uuid', 'reference_group_id', 'content', 'sort_order'])->toArray();
        $this->seed([ReferenceGroupSeeder::class, ReferenceItemSeeder::class]);
        $this->assertSame($before, ReferenceItem::query()->orderBy('id')->get(['id', 'uuid', 'reference_group_id', 'content', 'sort_order'])->toArray());
        $this->assertDatabaseCount('reference_items', 16);
    }
}
