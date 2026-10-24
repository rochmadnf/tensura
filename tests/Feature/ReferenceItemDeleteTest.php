<?php

namespace Tests\Feature;

use App\Models\MasterData\ReferenceGroup;
use App\Models\MasterData\ReferenceItem;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReferenceItemDeleteTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_delete_removes_only_selected_item_preserving_other_positions(): void
    {
        $group = ReferenceGroup::factory()->create();
        $first = ReferenceItem::factory()->for($group, 'group')->create(['sort_order' => 1]);
        $selected = ReferenceItem::factory()->for($group, 'group')->create(['sort_order' => 2]);
        $last = ReferenceItem::factory()->for($group, 'group')->create(['sort_order' => 5]);
        $other = ReferenceItem::factory()->create();
        $this->actingAs(UserFactory::new()->create())->from(route('ref.edit', $group->uuid))->delete(route('ref.item.destroy', [$group->uuid, $selected->uuid]))
            ->assertRedirectToRoute('ref.edit', $group->uuid);
        $this->assertModelMissing($selected);
        $this->assertModelExists($other);
        $this->assertModelExists($group);
        $this->assertSame([$first->uuid, $last->uuid], $group->items()->pluck('uuid')->all());
        $this->assertSame([1, 5], $group->items()->pluck('sort_order')->all());
        $this->assertDatabaseCount('reference_items', 3);
    }

    #[DataProvider('invalidIdentifiers')]
    public function test_delete_rejects_foreign_item_unknown_uuid_and_numeric_ids(string $kind): void
    {
        $item = ReferenceItem::factory()->create();
        $other = ReferenceItem::factory()->create();
        $parent = $kind === 'numeric group' ? $item->reference_group_id : ($kind === 'unknown group' ? (string) Str::uuid() : $item->group->uuid);
        $id = match ($kind) {
            'foreign' => $other->uuid, 'unknown' => (string) Str::uuid(), 'numeric' => $item->id, default => $item->uuid
        };
        $this->actingAs(UserFactory::new()->create())->delete(route('ref.item.destroy', [$parent, $id]))->assertNotFound();
        $this->assertModelExists($item);
        $this->assertModelExists($other);
    }

    /** @return array<string, array{string}> */
    public static function invalidIdentifiers(): array
    {
        return ['foreign' => ['foreign'], 'unknown' => ['unknown'], 'numeric' => ['numeric'], 'numeric group' => ['numeric group'], 'unknown group' => ['unknown group']];
    }

    public function test_guest_cannot_delete_item(): void
    {
        $item = ReferenceItem::factory()->create();
        $this->delete(route('ref.item.destroy', [$item->group->uuid, $item->uuid]))->assertRedirectToRoute('login.index');
        $this->assertModelExists($item);
    }
}
