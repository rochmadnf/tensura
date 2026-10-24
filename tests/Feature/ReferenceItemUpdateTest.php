<?php

namespace Tests\Feature;

use App\Models\MasterData\ReferenceItem;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReferenceItemUpdateTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_update_accepts_own_content_and_ignores_all_system_fields(): void
    {
        $item = ReferenceItem::factory()->create(['content' => 'Lama', 'sort_order' => 7]);
        $other = ReferenceItem::factory()->create();
        $this->actingAs(UserFactory::new()->create())
            ->from(route('ref.edit', $item->group->uuid))
            ->patch(route('ref.item.update', [$item->group->uuid, $item->uuid]), [
                'content' => ' Lama ',
                'id' => 999,
                'uuid' => (string) Str::uuid(),
                'reference_group_id' => $other->reference_group_id,
                'sort_order' => 999,
                'created_at' => '2000-01-01 00:00:00',
            ])
            ->assertRedirectToRoute('ref.edit', $item->group->uuid)
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('reference_items', [
            'id' => $item->id,
            'uuid' => $item->uuid,
            'reference_group_id' => $item->reference_group_id,
            'content' => 'Lama',
            'sort_order' => 7,
            'created_at' => $item->getRawOriginal('created_at'),
        ]);
        $this->assertDatabaseCount('reference_items', 2);
    }

    public function test_update_changes_content_and_permits_same_content_in_another_group(): void
    {
        $item = ReferenceItem::factory()->create();
        ReferenceItem::factory()->create(['content' => 'Baru']);
        $this->actingAs(UserFactory::new()->create())
            ->patch(route('ref.item.update', [$item->group->uuid, $item->uuid]), ['content' => ' Baru '])
            ->assertSessionHasNoErrors();
        $this->assertSame('Baru', $item->fresh()->content);
    }

    public function test_update_rejects_content_already_in_same_group(): void
    {
        $item = ReferenceItem::factory()->create();
        ReferenceItem::factory()
            ->for($item->group, 'group')
            ->create(['content' => 'Duplikat']);
        $this->actingAs(UserFactory::new()->create())
            ->patch(route('ref.item.update', [$item->group->uuid, $item->uuid]), ['content' => 'Duplikat'])
            ->assertInvalid(['content']);
        $this->assertSame($item->content, $item->fresh()->content);
    }

    #[DataProvider('invalidContent')]
    public function test_update_rejects_invalid_content(mixed $content): void
    {
        $item = ReferenceItem::factory()->create();
        $this->actingAs(UserFactory::new()->create())
            ->patch(route('ref.item.update', [$item->group->uuid, $item->uuid]), ['content' => $content])
            ->assertInvalid(['content']);
        $this->assertSame($item->content, $item->fresh()->content);
    }

    /** @return array<string, array{mixed}> */
    public static function invalidContent(): array
    {
        return ['blank' => [' '], 'long' => [str_repeat('A', 256)], 'type' => [123]];
    }

    #[DataProvider('invalidIdentifiers')]
    public function test_update_rejects_foreign_item_unknown_uuid_and_numeric_ids(string $kind): void
    {
        $item = ReferenceItem::factory()->create();
        $other = ReferenceItem::factory()->create();
        $parent =
            $kind === 'numeric group'
                ? $item->reference_group_id
                : ($kind === 'unknown group'
                    ? (string) Str::uuid()
                    : $item->group->uuid);
        $id = match ($kind) {
            'foreign' => $other->uuid,
            'unknown' => (string) Str::uuid(),
            'numeric' => $item->id,
            default => $item->uuid,
        };
        $this->actingAs(UserFactory::new()->create())
            ->patch(route('ref.item.update', [$parent, $id]), ['content' => 'Baru'])
            ->assertNotFound();
        $this->assertSame($item->content, $item->fresh()->content);
        $this->assertSame($other->content, $other->fresh()->content);
    }

    /** @return array<string, array{string}> */
    public static function invalidIdentifiers(): array
    {
        return [
            'foreign' => ['foreign'],
            'unknown' => ['unknown'],
            'numeric' => ['numeric'],
            'numeric group' => ['numeric group'],
            'unknown group' => ['unknown group'],
        ];
    }

    public function test_guest_cannot_update_item(): void
    {
        $item = ReferenceItem::factory()->create();
        $this->patch(route('ref.item.update', [$item->group->uuid, $item->uuid]), [
            'content' => 'Baru',
        ])->assertRedirectToRoute('login.index');
        $this->assertSame($item->content, $item->fresh()->content);
    }
}
