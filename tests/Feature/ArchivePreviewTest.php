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
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ArchivePreviewTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_parent_and_children_preview_is_read_only_and_hides_internal_state(): void
    {
        [$archive, $actor, $group] = $this->package();
        $this->actingAs($actor)->postJson(route('archives.preview', $archive->uuid), ['action' => 'restore', 'mode' => 'parent_with_children'])
            ->assertOk()
            ->assertJsonPath('data.counts', ['total' => 3, 'ready' => 3, 'conflicted' => 0])
            ->assertJsonPath('data.parent.uuid', $group->uuid)
            ->assertJsonPath('data.can_confirm', true)
            ->assertJsonCount(3, 'data.candidates')
            ->assertJsonMissingPath('data.fingerprint')
            ->assertJsonMissingPath('data.selection')
            ->assertJsonMissingPath('data.state')
            ->assertJsonStructure(['data' => ['confirmation_token']]);

        $this->assertDatabaseCount('archives', 1);
        $this->assertDatabaseCount('archive_records', 3);
        $this->assertDatabaseCount('reference_groups', 0);
        $this->assertDatabaseCount('reference_items', 0);
        $this->assertSame(1, $archive->fresh()->revision);
    }

    public function test_parent_only_preview_does_not_include_children(): void
    {
        [$archive, $actor] = $this->package();
        $root = $archive->records()->where('entity', ReferenceSnapshot::GROUP)->sole();
        $this->actingAs($actor)->postJson(route('archives.preview', $archive->uuid), ['action' => 'restore', 'mode' => 'parent_only'])
            ->assertOk()->assertJsonPath('data.counts.total', 1)->assertJsonPath('data.candidates.0.record_uuid', $root->uuid);
    }

    public function test_group_comparison_lists_archived_children_without_expanding_parent_only_restore(): void
    {
        [$archive, $actor, $group] = $this->package();
        $children = $archive->records()->where('entity', ReferenceSnapshot::ITEM)->orderBy('id')->get();
        $active = ReferenceGroup::factory()->create(['name' => $group->name, 'description' => 'Deskripsi aktif']);
        ReferenceItem::factory()->for($active, 'group')->create(['content' => 'Konten pembanding']);

        $this->actingAs($actor)->postJson(route('archives.preview', $archive->uuid), ['action' => 'restore', 'mode' => 'parent_only'])
            ->assertOk()->assertJsonCount(1, 'data.candidates')->assertJsonCount(2, 'data.parent.items')
            ->assertJsonPath('data.parent.items.0', ['record_uuid' => $children[0]->uuid, 'label' => $children[0]->label])
            ->assertJsonPath('data.matching_groups.0.description', 'Deskripsi aktif')
            ->assertJsonPath('data.matching_groups.0.items.0.content', 'Konten pembanding');

        $this->assertDatabaseCount('archive_records', 3);
    }

    #[DataProvider('childrenModes')]
    public function test_children_modes_use_active_parent_and_exact_selection(string $mode, int $count): void
    {
        [$archive, $actor, $group] = $this->package();
        ReferenceGroup::factory()->create($group->getAttributes());
        $archive->records()->where('entity', ReferenceSnapshot::GROUP)->delete();
        $archive->update(['context' => [...$archive->context, 'target' => ['entity' => ReferenceSnapshot::GROUP, 'uuid' => $group->uuid], 'parent_resolved' => true]]);
        $children = $archive->records()->orderBy('id')->get();
        $input = ['action' => 'restore', 'mode' => $mode];
        if ($mode !== 'children_all') {
            $input['record_uuids'] = $children->take($count)->pluck('uuid')->all();
        }

        $response = $this->actingAs($actor)->postJson(route('archives.preview', $archive->uuid), $input)
            ->assertOk()->assertJsonPath('data.counts', ['total' => $count, 'ready' => $count, 'conflicted' => 0])
            ->assertJsonPath('data.target.uuid', $group->uuid);

        $this->assertSame($children->take($count)->pluck('uuid')->all(), array_column($response->json('data.candidates'), 'record_uuid'));
        $this->assertDatabaseCount('reference_items', 0);
        $this->assertDatabaseCount('archive_records', 2);
    }

    /** @return array<string, array{string, int}> */
    public static function childrenModes(): array
    {
        return ['all' => ['children_all', 2], 'one' => ['child', 1], 'selected' => ['children_selected', 2]];
    }

    public function test_children_without_active_parent_are_blocked(): void
    {
        [$archive, $actor] = $this->package();
        $this->actingAs($actor)->postJson(route('archives.preview', $archive->uuid), ['action' => 'restore', 'mode' => 'children_all'])
            ->assertOk()->assertJsonPath('data.counts.ready', 0)
            ->assertJsonPath('data.candidates.0.conflicts.0.code', 'parent_missing')
            ->assertJsonPath('data.confirmation_token', null);
    }

    public function test_conflicting_group_blocks_package_and_exposes_comparison(): void
    {
        [$archive, $actor, $group] = $this->package();
        $active = ReferenceGroup::factory()->create(['name' => $group->name, 'description' => 'Grup aktif']);
        ReferenceItem::factory()->for($active, 'group')->create(['content' => 'Pembanding']);

        $this->actingAs($actor)->postJson(route('archives.preview', $archive->uuid), ['action' => 'restore', 'mode' => 'parent_with_children'])
            ->assertOk()->assertJsonPath('data.can_confirm', false)
            ->assertJsonPath('data.candidates.0.conflicts.0.code', 'name_conflict')
            ->assertJsonPath('data.candidates.1.conflicts.0.code', 'parent_blocked')
            ->assertJsonPath('data.matching_groups.0.uuid', $active->uuid)
            ->assertJsonPath('data.matching_groups.0.items.0.content', 'Pembanding');
    }

    public function test_explicit_merge_skips_duplicate_content_and_keeps_safe_children_ready(): void
    {
        [$archive, $actor, $group, $items] = $this->package();
        $target = ReferenceGroup::factory()->create(['name' => $group->name]);
        ReferenceItem::factory()->for($target, 'group')->create(['content' => $items->first()->content]);

        $this->actingAs($actor)->postJson(route('archives.preview', $archive->uuid), [
            'action' => 'restore', 'mode' => 'parent_with_children', 'target_group_uuid' => $target->uuid,
        ])->assertOk()->assertJsonPath('data.candidates.0.operation', 'merge')
            ->assertJsonPath('data.counts', ['total' => 3, 'ready' => 2, 'conflicted' => 1])
            ->assertJsonPath('data.conflicts.0.conflicts.0.code', 'content_conflict');

        $this->assertDatabaseCount('reference_items', 1);
        $this->assertDatabaseCount('archive_records', 3);
        $this->assertNull($archive->fresh()->context['target']);
    }

    #[DataProvider('identityColumns')]
    public function test_parent_identity_collision_is_detected(string $column): void
    {
        [$archive, $actor, $group] = $this->package();
        ReferenceGroup::factory()->create([$column => $group->{$column}]);

        $this->actingAs($actor)->postJson(route('archives.preview', $archive->uuid), ['action' => 'restore', 'mode' => 'parent_only'])
            ->assertOk()->assertJsonPath('data.candidates.0.conflicts.0.code', 'identity_conflict')
            ->assertJsonPath('data.confirmation_token', null);
    }

    #[DataProvider('identityColumns')]
    public function test_item_identity_collision_outside_target_group_is_detected(string $column): void
    {
        $actor = UserFactory::new()->create();
        $item = ReferenceItem::factory()->create();
        $archive = app(ArchiveReference::class)->item($item->group, $item, $actor);
        ReferenceItem::factory()->create([$column => $item->{$column}]);
        $record = $archive->records()->sole();

        $this->actingAs($actor)->postJson(route('archives.preview', $archive->uuid), [
            'action' => 'restore', 'mode' => 'child', 'record_uuids' => [$record->uuid],
        ])->assertOk()->assertJsonPath('data.candidates.0.conflicts.0.code', 'identity_conflict')
            ->assertJsonPath('data.can_confirm', false);
    }

    /** @return array<string, array{string}> */
    public static function identityColumns(): array
    {
        return ['id' => ['id'], 'uuid' => ['uuid']];
    }

    #[DataProvider('invalidSnapshots')]
    public function test_unsupported_snapshots_are_retained_and_can_still_be_deleted(string $change): void
    {
        [$archive, $actor] = $this->package();
        $root = $archive->records()->where('entity', ReferenceSnapshot::GROUP)->sole();
        match ($change) {
            'version' => $root->update(['schema_version' => 99]),
            'entity' => $root->update(['entity' => ReferenceGroup::class]),
            'payload' => $root->update(['payload' => [...$root->payload, 'unexpected' => true]]),
            'label' => $root->update(['label' => 'Label tidak sesuai']),
        };
        $mode = $change === 'entity' ? 'parent_with_children' : 'parent_only';
        if ($change === 'entity') {
            $this->actingAs($actor)->postJson(route('archives.preview', $archive->uuid), ['action' => 'restore', 'mode' => $mode])
                ->assertUnprocessable()->assertJsonValidationErrors('mode');
        } else {
            $this->actingAs($actor)->postJson(route('archives.preview', $archive->uuid), ['action' => 'restore', 'mode' => $mode])
                ->assertOk()->assertJsonPath('data.can_confirm', false)
                ->assertJsonPath('data.candidates.0.conflicts.0.code', 'unsupported_snapshot');
        }
        $this->postJson(route('archives.preview', $archive->uuid), ['action' => 'delete_forever', 'mode' => 'package'])
            ->assertOk()->assertJsonPath('data.counts.ready', 3)->assertJsonPath('data.can_confirm', true);
        $this->assertModelExists($root);
        $this->assertDatabaseCount('archive_records', 3);
    }

    /** @return array<string, array{string}> */
    public static function invalidSnapshots(): array
    {
        return ['version' => ['version'], 'entity' => ['entity'], 'payload' => ['payload'], 'label' => ['label']];
    }

    #[DataProvider('deleteModes')]
    public function test_delete_preview_targets_only_requested_archived_records(string $mode, int $count): void
    {
        [$archive, $actor] = $this->package();
        $input = ['action' => 'delete_forever', 'mode' => $mode];
        if ($mode !== 'package') {
            $input['record_uuids'] = $archive->records()->where('entity', ReferenceSnapshot::ITEM)->orderBy('id')->take($count)->pluck('uuid')->all();
        }
        $this->actingAs($actor)->postJson(route('archives.preview', $archive->uuid), $input)->assertOk()
            ->assertJsonPath('data.counts', ['total' => $count, 'ready' => $count, 'conflicted' => 0])
            ->assertJsonPath('data.candidates.0.operation', 'delete_forever');
        $this->assertDatabaseCount('archives', 1);
        $this->assertDatabaseCount('archive_records', 3);
    }

    /** @return array<string, array{string, int}> */
    public static function deleteModes(): array
    {
        return ['package' => ['package', 3], 'child' => ['child', 1], 'selected' => ['children_selected', 2]];
    }

    public function test_standalone_item_has_active_parent_and_can_be_previewed_by_other_logged_in_users(): void
    {
        $item = ReferenceItem::factory()->create();
        $archive = app(ArchiveReference::class)->item($item->group, $item, UserFactory::new()->create());
        $record = $archive->records()->sole();
        $this->actingAs(UserFactory::new()->create())->postJson(route('archives.preview', $archive->uuid), [
            'action' => 'restore', 'mode' => 'child', 'record_uuids' => [$record->uuid],
        ])->assertOk()->assertJsonPath('data.counts.ready', 1)->assertJsonPath('data.target.uuid', $item->group->uuid);
    }

    public function test_invalid_scope_cannot_select_parent_foreign_or_active_identifiers(): void
    {
        [$archive, $actor] = $this->package();
        $foreign = ArchiveRecord::factory()->create();
        $parent = $archive->records()->where('entity', ReferenceSnapshot::GROUP)->sole();
        foreach ([$foreign->uuid, $parent->uuid, $parent->source_uuid] as $uuid) {
            $this->actingAs($actor)->postJson(route('archives.preview', $archive->uuid), [
                'action' => 'delete_forever', 'mode' => 'child', 'record_uuids' => [$uuid],
            ])->assertUnprocessable()->assertJsonValidationErrors('record_uuids');
        }
        $this->assertDatabaseCount('archive_records', 4);
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_inputs_are_rejected_with_422(array $input, string $field): void
    {
        [$archive, $actor] = $this->package();
        $this->actingAs($actor)->postJson(route('archives.preview', $archive->uuid), $input)
            ->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidInputs(): array
    {
        return [
            'action' => [['action' => 'execute_sql', 'mode' => 'parent_only'], 'action'],
            'mode' => [['action' => 'restore', 'mode' => 'package'], 'mode'],
            'mixed action' => [['action' => 'delete_forever', 'mode' => 'parent_only'], 'mode'],
            'empty selection' => [['action' => 'restore', 'mode' => 'child'], 'record_uuids'],
            'malformed UUID' => [['action' => 'restore', 'mode' => 'child', 'record_uuids' => ['123']], 'record_uuids.0'],
            'extra selection' => [['action' => 'restore', 'mode' => 'parent_only', 'record_uuids' => ['018e6fa0-9382-7f17-a13c-33b147f24961']], 'record_uuids'],
            'purge target' => [['action' => 'delete_forever', 'mode' => 'package', 'target_group_uuid' => '018e6fa0-9382-7f17-a13c-33b147f24961'], 'target_group_uuid'],
            'unknown target' => [['action' => 'restore', 'mode' => 'parent_only', 'target_group_uuid' => '018e6fa0-9382-7f17-a13c-33b147f24961'], 'target_group_uuid'],
        ];
    }

    public function test_guests_and_invalid_archive_identifiers_are_rejected(): void
    {
        [$archive, $actor] = $this->package();
        $input = ['action' => 'restore', 'mode' => 'parent_only'];
        $this->postJson(route('archives.preview', $archive->uuid), $input)->assertUnauthorized();
        $this->actingAs($actor)->postJson(route('archives.preview', $archive->id), $input)->assertNotFound();
        $this->postJson(route('archives.preview', fake()->uuid()), $input)->assertNotFound();
    }

    public function test_duplicate_selection_and_multiple_items_in_single_mode_are_rejected(): void
    {
        [$archive, $actor] = $this->package();
        $ids = $archive->records()->where('entity', ReferenceSnapshot::ITEM)->pluck('uuid')->all();
        $this->actingAs($actor)->postJson(route('archives.preview', $archive->uuid), [
            'action' => 'delete_forever', 'mode' => 'child', 'record_uuids' => $ids,
        ])->assertUnprocessable()->assertJsonValidationErrors('record_uuids');
        $this->postJson(route('archives.preview', $archive->uuid), [
            'action' => 'delete_forever', 'mode' => 'children_selected', 'record_uuids' => [$ids[0], $ids[0]],
        ])->assertUnprocessable()->assertJsonValidationErrors('record_uuids.0');
    }

    public function test_completed_parent_and_standalone_item_reject_parent_modes(): void
    {
        $actor = UserFactory::new()->create();
        $item = ReferenceItem::factory()->create();
        $single = app(ArchiveReference::class)->item($item->group, $item, $actor);
        $this->actingAs($actor)->postJson(route('archives.preview', $single->uuid), [
            'action' => 'restore', 'mode' => 'parent_only',
        ])->assertUnprocessable()->assertJsonValidationErrors('mode');
        $this->postJson(route('archives.preview', $single->uuid), [
            'action' => 'restore', 'mode' => 'children_all',
        ])->assertUnprocessable()->assertJsonValidationErrors('mode');
        [$archive] = $this->package();
        $archive->records()->where('entity', ReferenceSnapshot::GROUP)->delete();
        $this->postJson(route('archives.preview', $archive->uuid), [
            'action' => 'restore', 'mode' => 'parent_only',
        ])->assertUnprocessable()->assertJsonValidationErrors('mode');
    }

    public function test_child_parent_mismatch_is_blocked_without_mutation(): void
    {
        [$archive, $actor] = $this->package();
        $child = $archive->records()->where('entity', ReferenceSnapshot::ITEM)->firstOrFail();
        $child->update(['parent_source_uuid' => fake()->uuid()]);
        $this->actingAs($actor)->postJson(route('archives.preview', $archive->uuid), [
            'action' => 'restore', 'mode' => 'parent_with_children',
        ])->assertOk()->assertJsonPath('data.counts.conflicted', 1)
            ->assertJsonPath('data.conflicts.0.conflicts.0.code', 'parent_mismatch');
        $this->assertModelExists($child);
    }

    /** @return array{Archive, User, ReferenceGroup, Collection<int, ReferenceItem>} */
    private function package(): array
    {
        $actor = UserFactory::new()->create();
        $group = ReferenceGroup::factory()->create();
        $items = ReferenceItem::factory()->count(2)->for($group, 'group')->create();
        $archive = app(ArchiveReference::class)->group($group, $actor);

        return [$archive, $actor, $group, $items];
    }
}
