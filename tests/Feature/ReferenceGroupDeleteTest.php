<?php

namespace Tests\Feature;

use App\Models\MasterData\ReferenceGroup;
use App\Models\MasterData\ReferenceItem;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReferenceGroupDeleteTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_delete_cascades_only_selected_groups_items(): void
    {
        $group = ReferenceGroup::factory()->create();
        $items = ReferenceItem::factory()->count(2)->for($group, 'group')->create();
        $other = ReferenceItem::factory()->create();
        $this->actingAs(UserFactory::new()->create())->delete(route('ref.destroy', $group->uuid))->assertRedirectToRoute('ref.index');
        $this->assertModelMissing($group);
        foreach ($items as $item) {
            $this->assertModelMissing($item);
        }
        $this->assertModelExists($other);
        $this->assertModelExists($other->group);
        $this->assertDatabaseCount('reference_items', 1);
    }

    #[DataProvider('invalidIdentifiers')]
    public function test_delete_rejects_unknown_uuid_and_numeric_id(bool $numeric): void
    {
        $group = ReferenceGroup::factory()->create();
        $this->actingAs(UserFactory::new()->create())->delete(route('ref.destroy', $numeric ? $group->id : (string) Str::uuid()))->assertNotFound();
        $this->assertModelExists($group);
    }

    /** @return array<string, array{bool}> */
    public static function invalidIdentifiers(): array
    {
        return ['numeric' => [true], 'unknown' => [false]];
    }

    public function test_guest_cannot_delete_group(): void
    {
        $group = ReferenceGroup::factory()->create();
        $this->delete(route('ref.destroy', $group->uuid))->assertRedirectToRoute('login.index');
        $this->assertModelExists($group);
    }
}
