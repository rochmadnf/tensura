<?php

namespace Tests\Feature;

use App\Models\MasterData\ReferenceGroup;
use App\Models\MasterData\ReferenceItem;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReferenceItemReorderTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_reorder_persists_complete_order_and_normalizes_gaps_without_affecting_other_groups(): void
    {
        $group = ReferenceGroup::factory()->create();
        $first = ReferenceItem::factory()
            ->for($group, 'group')
            ->create(['sort_order' => 4]);
        $second = ReferenceItem::factory()
            ->for($group, 'group')
            ->create(['sort_order' => 9]);
        $third = ReferenceItem::factory()
            ->for($group, 'group')
            ->create(['sort_order' => 12]);
        $other = ReferenceItem::factory()->create(['sort_order' => 20]);
        $this->actingAs(UserFactory::new()->create())
            ->from(route('ref.edit', $group->uuid))
            ->patch(route('ref.item.reorder', $group->uuid), ['ids' => [$third->uuid, $first->uuid, $second->uuid]])
            ->assertRedirectToRoute('ref.edit', $group->uuid)
            ->assertSessionHasNoErrors();
        $this->assertSame([$third->uuid, $first->uuid, $second->uuid], $group->items()->pluck('uuid')->all());
        $this->assertSame([1, 2, 3], $group->items()->pluck('sort_order')->all());
        $this->assertSame(20, $other->fresh()->sort_order);
    }

    #[DataProvider('invalidOrders')]
    public function test_invalid_reorder_returns_422_without_any_changes(string $kind): void
    {
        $group = ReferenceGroup::factory()->create();
        $first = ReferenceItem::factory()
            ->for($group, 'group')
            ->create(['sort_order' => 4]);
        $second = ReferenceItem::factory()
            ->for($group, 'group')
            ->create(['sort_order' => 9]);
        $foreign = ReferenceItem::factory()->create(['sort_order' => 20]);
        $ids = match ($kind) {
            'missing' => [$first->uuid],
            'extra' => [$first->uuid, $second->uuid, (string) Str::uuid()],
            'duplicate' => [$first->uuid, $first->uuid],
            'invalid' => [$first->uuid, 'invalid'],
            'foreign' => [$first->uuid, $foreign->uuid],
            'numeric' => [$first->id, $second->id],
            'empty' => [],
            'string' => 'invalid',
            'absent' => null,
            'associative' => ['first' => $first->uuid, 'second' => $second->uuid],
        };
        $response = $this->actingAs(UserFactory::new()->create())
            ->patchJson(route('ref.item.reorder', $group->uuid), ['ids' => $ids])
            ->assertUnprocessable();
        if (in_array($kind, ['missing', 'extra', 'foreign'])) {
            $response
                ->assertJsonValidationErrors(['ids'])
                ->assertJsonPath(
                    'errors.ids.0',
                    'Daftar item sudah berubah karena pengguna lain. Muat ulang data lalu coba urutkan kembali.',
                );
        }
        $this->assertSame([$first->uuid, $second->uuid], $group->items()->pluck('uuid')->all());
        $this->assertSame([4, 9], $group->items()->pluck('sort_order')->all());
        $this->assertSame(20, $foreign->fresh()->sort_order);
    }

    /** @return array<string, array{string}> */
    public static function invalidOrders(): array
    {
        return [
            'missing' => ['missing'],
            'extra' => ['extra'],
            'duplicate' => ['duplicate'],
            'invalid' => ['invalid'],
            'foreign' => ['foreign'],
            'numeric' => ['numeric'],
            'empty' => ['empty'],
            'string' => ['string'],
            'absent' => ['absent'],
            'associative' => ['associative'],
        ];
    }

    public function test_reorder_handles_single_item(): void
    {
        $item = ReferenceItem::factory()->create(['sort_order' => 5]);
        $this->actingAs(UserFactory::new()->create())
            ->patch(route('ref.item.reorder', $item->group->uuid), ['ids' => [$item->uuid]])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, $item->fresh()->sort_order);
    }

    public function test_reorder_rejects_unknown_or_numeric_group_and_guest(): void
    {
        $item = ReferenceItem::factory()->create(['sort_order' => 7]);
        $data = ['ids' => [$item->uuid]];
        $this->patch(route('ref.item.reorder', $item->group->uuid), $data)->assertRedirectToRoute('login.index');
        $this->actingAs(UserFactory::new()->create())
            ->patch(route('ref.item.reorder', $item->reference_group_id), $data)
            ->assertNotFound();
        $this->patch(route('ref.item.reorder', (string) Str::uuid()), $data)->assertNotFound();
        $this->assertSame(7, $item->fresh()->sort_order);
    }
}
