<?php

namespace Tests\Feature;

use App\Enums\ArchiveKindEnum;
use App\Models\Archive;
use App\Models\ArchiveRecord;
use App\Models\MasterData\ReferenceGroup;
use App\Models\MasterData\ReferenceItem;
use App\Services\Archive\ArchiveReference;
use App\Services\Archive\ReferenceSnapshot;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use RuntimeException;
use Tests\TestCase;

class ReferenceArchiveTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_deleting_group_captures_all_chunks_before_cascade(): void
    {
        $this->freezeTime();
        $actor = UserFactory::new()->create();
        $group = ReferenceGroup::factory()->create(['description' => 'Snapshot lengkap']);
        $items = ReferenceItem::factory()->count(205)->for($group, 'group')->create();
        $other = ReferenceItem::factory()->create();

        $this->actingAs($actor)->delete(route('ref.destroy', $group->uuid))->assertRedirectToRoute('ref.index');

        $archive = Archive::query()->sole();
        $this->assertSame(ArchiveKindEnum::PACKAGE, $archive->kind);
        $this->assertSame($actor->id, $archive->archived_by);
        $this->assertSame($actor->name, $archive->archived_by_name);
        $this->assertSame(now()->toDateTimeString(), $archive->archived_at->toDateTimeString());
        $this->assertSame($group->uuid, $archive->context['parent']['source_uuid']);
        $this->assertNull($archive->context['target']);
        $this->assertFalse($archive->context['parent_resolved']);
        $this->assertSame(206, $archive->records()->count());
        $this->assertEquals($group->getAttributes(), $archive->records()->where('entity', ReferenceSnapshot::GROUP)->sole()->payload);
        $last = $archive->records()->where('source_uuid', $items->last()->uuid)->sole();
        $this->assertEquals($items->last()->getAttributes(), $last->payload);
        $this->assertSame($group->uuid, $last->parent_source_uuid);
        $this->assertModelMissing($group);
        $this->assertDatabaseCount('reference_items', 1);
        $this->assertModelExists($other);
    }

    public function test_item_archive_stays_separate_when_parent_is_later_archived(): void
    {
        $actor = UserFactory::new()->create();
        $group = ReferenceGroup::factory()->create();
        $item = ReferenceItem::factory()->for($group, 'group')->create(['sort_order' => 4]);
        $remaining = ReferenceItem::factory()->for($group, 'group')->create(['sort_order' => 9]);

        $this->actingAs($actor)->from(route('ref.edit', $group->uuid))
            ->delete(route('ref.item.destroy', [$group->uuid, $item->uuid]))
            ->assertRedirectToRoute('ref.edit', $group->uuid);

        $single = Archive::query()->sole();
        $this->assertSame(ArchiveKindEnum::RECORD, $single->kind);
        $this->assertSame($group->uuid, $single->context['target']['uuid']);
        $this->assertTrue($single->context['parent_resolved']);
        $this->assertEquals($item->getAttributes(), $single->records()->sole()->payload);
        $this->assertSame(9, $remaining->fresh()->sort_order);

        $this->delete(route('ref.destroy', $group->uuid))->assertRedirectToRoute('ref.index');
        $package = Archive::query()->where('kind', ArchiveKindEnum::PACKAGE)->sole();
        $this->assertSame(2, $package->records()->count());
        $this->assertSame(1, $single->records()->count());
        $this->assertFalse($package->records()->where('source_uuid', $item->uuid)->exists());
        $this->assertDatabaseCount('archives', 2);
        $this->assertDatabaseCount('archive_records', 3);
    }

    public function test_snapshot_failure_rolls_back_package_and_preserves_active_data(): void
    {
        $group = ReferenceGroup::factory()->create();
        $item = ReferenceItem::factory()->for($group, 'group')->create();
        $actor = UserFactory::new()->create();
        Exceptions::fake();
        ArchiveRecord::creating(function (ArchiveRecord $record): void {
            if ($record->entity === ReferenceSnapshot::ITEM) {
                throw new RuntimeException('Simulasi kegagalan penyimpanan child.');
            }
        });

        try {
            $this->actingAs($actor)->delete(route('ref.destroy', $group->uuid))->assertInternalServerError();
        } finally {
            ArchiveRecord::getEventDispatcher()->forget('eloquent.creating: ' . ArchiveRecord::class);
        }

        Exceptions::assertReported(RuntimeException::class);
        $this->assertModelExists($group);
        $this->assertModelExists($item);
        $this->assertDatabaseCount('archives', 0);
        $this->assertDatabaseCount('archive_records', 0);
    }

    public function test_repeated_delete_does_not_create_another_archive(): void
    {
        $group = ReferenceGroup::factory()->create();
        $this->actingAs(UserFactory::new()->create())->delete(route('ref.destroy', $group->uuid))->assertRedirect();
        $this->delete(route('ref.destroy', $group->uuid))->assertNotFound();
        $this->assertDatabaseCount('archives', 1);
        $this->assertDatabaseCount('archive_records', 1);
    }

    public function test_service_rechecks_item_parent_even_without_route_binding(): void
    {
        $group = ReferenceGroup::factory()->create();
        $foreign = ReferenceItem::factory()->create();
        $actor = UserFactory::new()->create();

        try {
            app(ArchiveReference::class)->item($group, $foreign, $actor);
            $this->fail('Foreign item should not be archived.');
        } catch (ModelNotFoundException) {
            $this->assertModelExists($foreign);
            $this->assertDatabaseCount('archives', 0);
        }
    }
}
