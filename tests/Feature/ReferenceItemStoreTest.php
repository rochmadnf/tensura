<?php

namespace Tests\Feature;

use App\Models\MasterData\ReferenceGroup;
use App\Models\MasterData\ReferenceItem;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReferenceItemStoreTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_new_item_is_trimmed_and_appended_only_to_its_group_ignoring_system_fields(): void
    {
        $group = ReferenceGroup::factory()->create();
        ReferenceItem::factory()->for($group, 'group')->create(['sort_order' => 5]);
        $other = ReferenceItem::factory()->create(['sort_order' => 20]);
        $uuid = (string) Str::uuid();
        $this->actingAs(UserFactory::new()->create())->from(route('ref.edit', $group->uuid))
            ->post(route('ref.item.store', $group->uuid), ['content' => ' Baru ', 'id' => 999, 'uuid' => $uuid,
                'reference_group_id' => $other->reference_group_id, 'sort_order' => 999, 'created_at' => '2000-01-01 00:00:00'])
            ->assertRedirectToRoute('ref.edit', $group->uuid)->assertSessionHasNoErrors();
        $item = $group->items()->where('content', 'Baru')->sole();
        $this->assertSame(6, $item->sort_order);
        $this->assertNotSame(999, $item->id);
        $this->assertNotSame($uuid, $item->uuid);
        $this->assertTrue(Str::isUuid($item->uuid));
        $this->assertNotSame('2000-01-01 00:00:00', $item->getRawOriginal('created_at'));
        $this->assertSame(20, $other->fresh()->sort_order);
        $this->post(route('ref.item.store', $other->group->uuid), ['content' => 'Baru'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('reference_items', ['reference_group_id' => $other->reference_group_id, 'content' => 'Baru', 'sort_order' => 21]);
    }

    public function test_first_item_starts_at_one_and_accepts_maximum_length(): void
    {
        $group = ReferenceGroup::factory()->create();
        $this->actingAs(UserFactory::new()->create())->post(route('ref.item.store', $group->uuid), ['content' => str_repeat('A', 255)])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('reference_items', ['reference_group_id' => $group->id, 'sort_order' => 1, 'content' => str_repeat('A', 255)]);
    }

    public function test_duplicate_content_is_rejected_only_within_same_group(): void
    {
        $item = ReferenceItem::factory()->create(['content' => 'Sama']);
        $this->actingAs(UserFactory::new()->create())->post(route('ref.item.store', $item->group->uuid), ['content' => ' Sama '])->assertInvalid(['content']);
        $this->assertDatabaseCount('reference_items', 1);
    }

    #[DataProvider('invalidContent')]
    public function test_invalid_content_is_rejected_with_indonesian_message(mixed $content, string $message): void
    {
        $group = ReferenceGroup::factory()->create();
        $this->actingAs(UserFactory::new()->create())->post(route('ref.item.store', $group->uuid), ['content' => $content])->assertInvalid(['content' => $message]);
        $this->assertDatabaseCount('reference_items', 0);
    }

    /** @return array<string, array{mixed,string}> */
    public static function invalidContent(): array
    {
        return ['blank' => [' ', 'Konten Item wajib diisi.'], 'long' => [str_repeat('A', 256), 'Konten Item maksimal 255 karakter.'],
            'type' => [123, 'Konten Item harus berupa teks.'], 'array' => [['A'], 'Konten Item harus berupa teks.']];
    }

    public function test_store_rejects_numeric_and_unknown_group_ids(): void
    {
        $group = ReferenceGroup::factory()->create();
        $this->actingAs(UserFactory::new()->create());
        $this->post(route('ref.item.store', $group->id), ['content' => 'Baru'])->assertNotFound();
        $this->post(route('ref.item.store', (string) Str::uuid()), ['content' => 'Baru'])->assertNotFound();
        $this->assertDatabaseCount('reference_items', 0);
    }

    public function test_guest_cannot_store_item(): void
    {
        $group = ReferenceGroup::factory()->create();
        $this->post(route('ref.item.store', $group->uuid), ['content' => 'Baru'])->assertRedirectToRoute('login.index');
        $this->assertDatabaseCount('reference_items',0);
    }
}
