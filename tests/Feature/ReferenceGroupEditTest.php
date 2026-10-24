<?php

namespace Tests\Feature;

use App\Models\MasterData\ReferenceGroup;
use App\Models\MasterData\ReferenceItem;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReferenceGroupEditTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_edit_exposes_nested_items_sorted_by_position_then_id(): void
    {
        $group = ReferenceGroup::factory()->create();
        $last = ReferenceItem::factory()->for($group, 'group')->create(['sort_order' => 9]);
        $first = ReferenceItem::factory()->for($group, 'group')->create(['sort_order' => 2]);
        $second = ReferenceItem::factory()->for($group, 'group')->create(['sort_order' => 2]);
        ReferenceItem::factory()->create();
        $this->actingAs(UserFactory::new()->create())->withoutVite()->get(route('ref.edit', $group->uuid))->assertOk()
            ->assertInertia(fn(Assert $page) => $page->component('reference/edit')
                ->where('page.id', 'a3063dcc-2750-45e0-8ea7-c859c50bd3fb')
                ->where('resource.data.id', $group->uuid)->where('resource.data.items_count', 3)
                ->where('resource.data.items', [
                    ['id' => $first->uuid, 'content' => $first->content, 'sort_order' => 2],
                    ['id' => $second->uuid, 'content' => $second->content, 'sort_order' => 2],
                    ['id' => $last->uuid, 'content' => $last->content, 'sort_order' => 9],
                ]));
    }

    public function test_update_accepts_own_name_ignores_system_fields_and_stays_on_edit(): void
    {
        $group = ReferenceGroup::factory()->create();
        $this->actingAs(UserFactory::new()->create())->patch(route('ref.update', $group->uuid), [
            'name' => $group->name, 'description' => 'Diperbarui', 'id' => 999, 'uuid' => (string) Str::uuid(),
            'reference_group_id' => 999, 'sort_order' => 999, 'created_at' => '2000-01-01 00:00:00',
        ])->assertRedirectToRoute('ref.edit', $group->uuid)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('reference_groups', ['id' => $group->id, 'uuid' => $group->uuid,
            'name' => $group->name, 'description' => 'Diperbarui', 'created_at' => $group->getRawOriginal('created_at')]);
        $this->assertDatabaseCount('reference_groups', 1);
    }

    public function test_update_saves_a_new_name_and_can_clear_description(): void
    {
        $group = ReferenceGroup::factory()->create(['name' => 'Nama Lama', 'description' => 'Keterangan']);
        $this->actingAs(UserFactory::new()->create())->patch(route('ref.update', $group->uuid), [
            'name' => ' Nama Baru ', 'description' => '',
        ])->assertRedirectToRoute('ref.edit', $group->uuid)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('reference_groups', ['id' => $group->id, 'name' => 'Nama Baru', 'description' => null]);
    }

    public function test_update_rejects_another_groups_name(): void
    {
        $group = ReferenceGroup::factory()->create();
        $other = ReferenceGroup::factory()->create();
        $this->actingAs(UserFactory::new()->create())->patch(route('ref.update', $group->uuid), ['name' => $other->name])->assertInvalid(['name']);
        $this->assertSame($group->name, $group->fresh()->name);
    }

    #[DataProvider('invalidData')]
    public function test_update_rejects_invalid_fields(array $data, string $field): void
    {
        $group = ReferenceGroup::factory()->create();
        $this->actingAs(UserFactory::new()->create())->patch(route('ref.update', $group->uuid), [...['name' => 'Nama Baru'], ...$data])->assertInvalid([$field]);
        $this->assertSame($group->name, $group->fresh()->name);
    }

    /** @return array<string, array{array<string,mixed>, string}> */
    public static function invalidData(): array
    {
        return ['required' => [['name' => ''], 'name'], 'short' => [['name' => 'AB'], 'name'], 'long' => [['name' => str_repeat('A', 101)], 'name'],
            'type' => [['name' => []], 'name'], 'description long' => [['description' => str_repeat('A', 256)], 'description'], 'description type' => [['description' => 123], 'description']];
    }

    #[DataProvider('invalidIdentifiers')]
    public function test_edit_and_update_reject_unknown_uuid_and_numeric_id(bool $numeric): void
    {
        $group = ReferenceGroup::factory()->create();
        $id = $numeric ? $group->id : (string) Str::uuid();
        $this->actingAs(UserFactory::new()->create())->withoutVite();
        $this->get(route('ref.edit', $id))->assertNotFound();
        $this->patch(route('ref.update', $id), ['name' => 'Nama Baru'])->assertNotFound();
        $this->assertSame($group->name, $group->fresh()->name);
    }

    /** @return array<string, array{bool}> */
    public static function invalidIdentifiers(): array
    {
        return ['numeric' => [true], 'unknown' => [false]];
    }

    public function test_guests_cannot_edit_or_update_groups(): void
    {
        $group = ReferenceGroup::factory()->create();
        $this->get(route('ref.edit', $group->uuid))->assertRedirectToRoute('login.index');
        $this->patch(route('ref.update', $group->uuid), ['name' => 'Nama Baru'])->assertRedirectToRoute('login.index');
        $this->assertSame($group->name, $group->fresh()->name);
    }
}
