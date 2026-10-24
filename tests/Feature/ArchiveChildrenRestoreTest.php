<?php

namespace Tests\Feature;

use App\Models\Archive;
use App\Models\ArchiveRecord;
use App\Models\MasterData\ReferenceGroup;
use App\Models\MasterData\ReferenceItem;
use App\Models\MasterData\User;
use App\Services\Archive\ArchiveReference;
use App\Services\Archive\ReferenceSnapshot;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ArchiveChildrenRestoreTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[DataProvider('childrenModes')]
    public function test_children_modes_append_only_requested_items_in_original_relative_order(string $mode, array $positions): void
    {
        [$archive, $actor, $group, $items] = $this->package();
        $this->restore($archive, $actor, 'parent_only');
        $existing = ReferenceItem::factory()->for($group, 'group')->create(['sort_order' => 7]);
        $selected = collect($positions)->map(fn(int $position): ReferenceItem => $items[$position])->sortBy('sort_order')->values();
        $ids = $archive->records()->whereIn('source_uuid', $selected->pluck('uuid')->all())->pluck('uuid')->all();
        $selection = $mode === 'children_all' ? [] : ['record_uuids' => array_reverse($ids)];

        $this->restore($archive, $actor, $mode, $selection)
            ->assertJsonPath('data.counts', ['requested' => count($positions), 'restored' => count($positions), 'conflicted' => 0, 'remaining' => 3 - count($positions)])
            ->assertJsonPath('data.archive_exists', count($positions) !== 3);

        $this->assertSame([$existing->uuid, ...$selected->pluck('uuid')->all()], $group->items()->pluck('uuid')->all());
        foreach ($selected as $index => $item) {
            $this->assertEquals([...$item->getAttributes(), 'sort_order' => 8 + $index], $item->fresh()->getAttributes());
        }
        $this->assertDatabaseCount('archive_records', 3 - count($positions));
    }

    /** @return array<string, array{string, list<int>}> */
    public static function childrenModes(): array
    {
        return ['all' => ['children_all', [0, 1, 2]], 'single' => ['child', [0]], 'selected' => ['children_selected', [0, 2]]];
    }

    public function test_merge_skips_duplicates_and_remembers_destination_for_retry(): void
    {
        [$archive, $actor, $source, $items] = $this->package();
        $target = ReferenceGroup::factory()->create(['name' => $source->name, 'description' => 'Deskripsi aktif']);
        $existing = ReferenceItem::factory()->for($target, 'group')->create(['content' => $items[0]->content, 'sort_order' => 9]);
        $targetAttributes = $target->getAttributes();

        $this->restore($archive, $actor, 'parent_with_children', ['target_group_uuid' => $target->uuid])
            ->assertJsonPath('data.counts', ['requested' => 4, 'restored' => 3, 'conflicted' => 1, 'remaining' => 1])
            ->assertJsonPath('data.conflicts.0.conflicts.0.code', 'content_conflict');

        $this->assertModelMissing($source);
        $this->assertEquals($targetAttributes, $target->fresh()->getAttributes());
        $this->assertSame([$existing->uuid, $items[1]->uuid, $items[2]->uuid], $target->items()->pluck('uuid')->all());
        $this->assertSame([9, 10, 11], $target->items()->pluck('sort_order')->all());
        $this->assertSame($target->uuid, $archive->fresh()->context['target']['uuid']);
        $this->assertTrue($archive->fresh()->context['parent_resolved']);
        $this->assertSame($source->uuid, $archive->fresh()->context['parent']['source_uuid']);
        $this->assertEquals([...$items[1]->getAttributes(), 'reference_group_id' => $target->id, 'sort_order' => 10], $items[1]->fresh()->getAttributes());

        $existing->update(['content' => 'Konflik telah diselesaikan']);
        $this->restore($archive, $actor, 'children_all')->assertJsonPath('data.counts.remaining', 0);
        $this->assertSame(12, $items[0]->fresh()->sort_order);
        $this->assertSame($target->id, $items[0]->fresh()->reference_group_id);
        $this->assertModelMissing($archive);
        $this->assertModelMissing($source);
    }

    public function test_parent_only_merge_resolves_parent_without_touching_children_or_target(): void
    {
        [$archive, $actor, $source] = $this->package();
        $target = ReferenceGroup::factory()->create(['name' => $source->name]);
        $before = $archive->records()->where('entity', ReferenceSnapshot::ITEM)->orderBy('id')->get()->map->getAttributes()->all();
        $this->restore($archive, $actor, 'parent_only', ['target_group_uuid' => $target->uuid])
            ->assertJsonPath('data.counts.restored', 1)->assertJsonPath('data.counts.remaining', 3);
        $this->assertSame($before, $archive->records()->orderBy('id')->get()->map->getAttributes()->all());
        $this->assertTrue($archive->fresh()->context['parent_resolved']);
        $this->assertDatabaseCount('reference_groups', 1);
        $this->assertDatabaseCount('reference_items', 0);

        $this->restore($archive, $actor, 'children_all')->assertJsonPath('data.archive_exists', false);
        $this->assertSame([1, 2, 3], $target->items()->pluck('sort_order')->all());
    }

    public function test_children_only_into_explicit_target_keeps_unselected_parent_snapshot(): void
    {
        [$archive, $actor, $source] = $this->package();
        $target = ReferenceGroup::factory()->create();
        $root = $archive->records()->where('entity', ReferenceSnapshot::GROUP)->sole();
        $this->restore($archive, $actor, 'children_all', ['target_group_uuid' => $target->uuid])
            ->assertJsonPath('data.counts.remaining', 1);

        $this->assertModelExists($root);
        $this->assertFalse($archive->fresh()->context['parent_resolved']);
        $this->assertSame($target->uuid, $archive->fresh()->context['target']['uuid']);
        $this->assertModelMissing($source);
        $this->assertSame(3, $target->items()->count());
        $this->restore($archive, $actor, 'parent_only')->assertJsonPath('data.target.uuid', $source->uuid);
        $this->assertSame(0, $source->items()->count());
        $this->assertSame(3, $target->items()->count());
        $this->assertModelMissing($archive);
    }

    #[DataProvider('standaloneTargets')]
    public function test_standalone_item_can_restore_to_original_or_explicit_parent(bool $otherTarget): void
    {
        $actor = UserFactory::new()->create();
        $item = ReferenceItem::factory()->create(['sort_order' => 30, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-02-01 00:00:00']);
        $source = $item->group;
        $archive = app(ArchiveReference::class)->item($source, $item, $actor);
        $target = $otherTarget ? ReferenceGroup::factory()->create() : $source;
        ReferenceItem::factory()->for($target, 'group')->create(['sort_order' => 6]);
        $record = $archive->records()->sole();
        $input = ['record_uuids' => [$record->uuid]];
        if ($otherTarget) {
            $input['target_group_uuid'] = $target->uuid;
        }
        $this->restore($archive, $actor, 'child', $input)->assertJsonPath('data.archive_exists', false);
        $this->assertEquals([...$item->getAttributes(), 'reference_group_id' => $target->id, 'sort_order' => 7], $item->fresh()->getAttributes());
        $this->assertModelMissing($archive);
        $this->assertModelExists($source);
    }

    /** @return array<string, array{bool}> */
    public static function standaloneTargets(): array
    {
        return ['original' => [false], 'another active parent' => [true]];
    }

    public function test_deleted_destination_requires_new_preview_and_explicit_retargeting(): void
    {
        [$archive, $actor, $source] = $this->package();
        $this->restore($archive, $actor, 'parent_only');
        $input = $this->confirmation($archive, $actor, 'children_all');
        $parentArchive = app(ArchiveReference::class)->group($source->fresh(), $actor);

        $this->postJson(route('archives.restore', $archive->uuid), $input)->assertConflict()->assertJsonPath('code', 'archive_changed');
        $this->postJson(route('archives.preview', $archive->uuid), ['action' => 'restore', 'mode' => 'children_all'])
            ->assertOk()->assertJsonPath('data.can_confirm', false)->assertJsonPath('data.candidates.0.conflicts.0.code', 'parent_missing');
        $this->assertSame(3, $archive->records()->count());
        $target = ReferenceGroup::factory()->create();
        $this->restore($archive, $actor, 'children_all', ['target_group_uuid' => $target->uuid])->assertJsonPath('data.archive_exists', false);
        $this->assertModelExists($parentArchive);
        $this->assertSame(3, $target->items()->count());
    }

    public function test_new_content_conflict_after_preview_cancels_entire_selection(): void
    {
        [$archive, $actor, $group, $items] = $this->package();
        $this->restore($archive, $actor, 'parent_only');
        $input = $this->confirmation($archive, $actor, 'children_all');
        $other = ReferenceItem::factory()->for($group, 'group')->create(['content' => $items[0]->content]);
        $this->postJson(route('archives.restore', $archive->uuid), $input)->assertConflict()->assertJsonPath('code', 'archive_changed');
        $this->assertSame([$other->uuid], $group->items()->pluck('uuid')->all());
        $this->assertSame(3, $archive->records()->count());
        $this->assertSame(2, $archive->fresh()->revision);
    }

    public function test_child_failure_rolls_back_appended_data_and_cleanup(): void
    {
        [$archive, $actor, $group, $items] = $this->package();
        $this->restore($archive, $actor, 'parent_only');
        $existing = ReferenceItem::factory()->for($group, 'group')->create(['sort_order' => 8]);
        $input = $this->confirmation($archive, $actor, 'children_all');
        Exceptions::fake();
        ArchiveRecord::deleting(function (ArchiveRecord $record) use ($items): void {
            if ($record->source_uuid === $items[0]->uuid) {
                throw new RuntimeException('Gagal membersihkan child terakhir.');
            }
        });
        try {
            $this->postJson(route('archives.restore', $archive->uuid), $input)->assertInternalServerError();
        } finally {
            ArchiveRecord::getEventDispatcher()->forget('eloquent.deleting: ' . ArchiveRecord::class);
        }
        Exceptions::assertReported(RuntimeException::class);
        $this->assertSame([$existing->uuid], $group->items()->pluck('uuid')->all());
        $this->assertSame(3, $archive->records()->count());
        $this->assertSame(2, $archive->fresh()->revision);
    }

    public function test_replaying_selected_children_token_cannot_restore_them_twice(): void
    {
        [$archive, $actor, $group] = $this->package();
        $this->restore($archive, $actor, 'parent_only');
        $record = $archive->records()->orderBy('id')->firstOrFail();
        $input = $this->confirmation($archive, $actor, 'child', ['record_uuids' => [$record->uuid]]);
        $this->postJson(route('archives.restore', $archive->uuid), $input)->assertOk();
        $this->postJson(route('archives.restore', $archive->uuid), $input)->assertConflict();
        $this->assertSame(1, $group->items()->count());
        $this->assertSame(2, $archive->records()->count());
    }

    public function test_changing_merge_target_without_new_preview_is_rejected(): void
    {
        [$archive, $actor, $source] = $this->package();
        $target = ReferenceGroup::factory()->create();
        $other = ReferenceGroup::factory()->create();
        $input = $this->confirmation($archive, $actor, 'parent_with_children', ['target_group_uuid' => $target->uuid]);
        $this->postJson(route('archives.restore', $archive->uuid), [...$input, 'target_group_uuid' => $other->uuid])
            ->assertUnprocessable()->assertJsonValidationErrors('confirmation_token');
        $this->assertModelMissing($source);
        $this->assertSame(4, $archive->records()->count());
        $this->assertDatabaseCount('reference_items', 0);
    }

    /** @return array{Archive, User, ReferenceGroup, Collection<int, ReferenceItem>} */
    private function package(): array
    {
        $actor = UserFactory::new()->create();
        $group = ReferenceGroup::factory()->create();
        $items = ReferenceItem::factory()->count(3)->for($group, 'group')->sequence(['sort_order' => 30], ['sort_order' => 10], ['sort_order' => 20])->create();

        return [app(ArchiveReference::class)->group($group, $actor), $actor, $group, $items];
    }

    /** @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function confirmation(Archive $archive, User $actor, string $mode, array $extra = []): array
    {
        $selection = ['action' => 'restore', 'mode' => $mode, ...$extra];
        $response = $this->actingAs($actor)->postJson(route('archives.preview', $archive->uuid), $selection)
            ->assertOk()->assertJsonPath('data.can_confirm', true);

        return [...$selection, 'confirmation_token' => $response->json('data.confirmation_token')];
    }

    /** @param array<string, mixed> $extra */
    private function restore(Archive $archive, User $actor, string $mode, array $extra = []): TestResponse
    {
        return $this->postJson(route('archives.restore', $archive->uuid), $this->confirmation($archive, $actor, $mode, $extra))->assertOk();
    }
}
