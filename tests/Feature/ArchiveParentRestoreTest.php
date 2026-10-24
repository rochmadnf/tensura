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
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ArchiveParentRestoreTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_parent_only_restores_original_identity_and_keeps_children_and_context(): void
    {
        [$archive, $actor, $group, $items] = $this->package();
        $children = $archive->records()->where('entity', ReferenceSnapshot::ITEM)->orderBy('id')->get();
        $input = $this->confirmation($archive, $actor, 'parent_only');

        $this->postJson(route('archives.restore', $archive->uuid), $input)->assertOk()
            ->assertJsonPath('data.mode', 'parent_only')
            ->assertJsonPath('data.target.uuid', $group->uuid)
            ->assertJsonPath('data.counts', ['requested' => 1, 'restored' => 1, 'conflicted' => 0, 'remaining' => 2])
            ->assertJsonPath('data.archive_exists', true)
            ->assertJsonMissingPath('data.fingerprint')
            ->assertJsonMissingPath('data.confirmation_token');

        $this->assertEquals($group->getAttributes(), ReferenceGroup::query()->sole()->getAttributes());
        $this->assertDatabaseCount('reference_items', 0);
        $this->assertSame($children->pluck('uuid')->all(), $archive->records()->orderBy('id')->pluck('uuid')->all());
        $this->assertEquals($items->first()->getAttributes(), $archive->records()->where('source_uuid', $items->first()->uuid)->sole()->payload);
        $this->assertTrue($archive->fresh()->context['parent_resolved']);
        $this->assertSame($group->uuid, $archive->fresh()->context['target']['uuid']);
        $this->assertSame($group->uuid, $archive->fresh()->context['parent']['source_uuid']);
        $this->assertSame(2, $archive->fresh()->revision);
    }

    public function test_package_restore_keeps_original_data_relationships_and_positions(): void
    {
        [$archive, $actor, $group, $items] = $this->package();
        $input = $this->confirmation($archive, $actor, 'parent_with_children');

        $this->postJson(route('archives.restore', $archive->uuid), $input)->assertOk()
            ->assertJsonPath('data.counts', ['requested' => 3, 'restored' => 3, 'conflicted' => 0, 'remaining' => 0])
            ->assertJsonPath('data.archive_exists', false)
            ->assertJsonPath('data.conflicts', []);

        $this->assertEquals($group->getAttributes(), ReferenceGroup::query()->sole()->getAttributes());
        foreach ($items as $item) {
            $this->assertEquals($item->getAttributes(), ReferenceItem::query()->whereKey($item->id)->firstOrFail()->getAttributes());
        }
        $this->assertSame([2, 9], $group->items()->pluck('sort_order')->all());
        $this->assertSame($items->pluck('uuid')->all(), $group->items()->pluck('uuid')->all());
        $this->assertModelMissing($archive);
        $this->assertDatabaseCount('archive_records', 0);
    }

    #[DataProvider('parentModes')]
    public function test_empty_parent_restore_cleans_up_the_package(string $mode): void
    {
        [$archive, $actor, $group] = $this->package(0);
        $input = $this->confirmation($archive, $actor, $mode);
        $this->postJson(route('archives.restore', $archive->uuid), $input)->assertOk()
            ->assertJsonPath('data.counts', ['requested' => 1, 'restored' => 1, 'conflicted' => 0, 'remaining' => 0])
            ->assertJsonPath('data.archive_exists', false);
        $this->assertModelExists($group);
        $this->assertModelMissing($archive);
        $this->assertDatabaseCount('archive_records', 0);
    }

    /** @return array<string, array{string}> */
    public static function parentModes(): array
    {
        return ['parent only' => ['parent_only'], 'parent with children' => ['parent_with_children']];
    }

    public function test_known_child_identity_conflict_restores_safe_subset_and_preserves_other_active_data(): void
    {
        [$archive, $actor, $group, $items] = $this->package();
        $other = ReferenceItem::factory()->create(['uuid' => $items->first()->uuid]);
        $otherAttributes = $other->getAttributes();
        $conflict = $archive->records()->where('source_uuid', $items->first()->uuid)->sole();
        $input = $this->confirmation($archive, $actor, 'parent_with_children');

        $this->postJson(route('archives.restore', $archive->uuid), $input)->assertOk()
            ->assertJsonPath('data.counts', ['requested' => 3, 'restored' => 2, 'conflicted' => 1, 'remaining' => 1])
            ->assertJsonPath('data.archive_exists', true)
            ->assertJsonPath('data.conflicts.0.record_uuid', $conflict->uuid)
            ->assertJsonPath('data.conflicts.0.conflicts.0.code', 'identity_conflict');

        $this->assertSame([$items->last()->uuid], $group->items()->pluck('uuid')->all());
        $this->assertSame([9], $group->items()->pluck('sort_order')->all());
        $this->assertEquals($otherAttributes, $other->fresh()->getAttributes());
        $this->assertModelExists($conflict);
        $this->assertSame($group->uuid, $archive->fresh()->context['target']['uuid']);
        $this->assertTrue($archive->fresh()->context['parent_resolved']);
        $this->assertSame(2, $archive->fresh()->revision);
        $this->assertDatabaseCount('archive_records', 1);
    }

    public function test_unsupported_children_remain_archived_while_parent_is_restored(): void
    {
        [$archive, $actor, $group] = $this->package();
        $archive->records()->where('entity', ReferenceSnapshot::ITEM)->update(['schema_version' => 99]);
        $input = $this->confirmation($archive, $actor, 'parent_with_children');

        $this->postJson(route('archives.restore', $archive->uuid), $input)->assertOk()
            ->assertJsonPath('data.counts', ['requested' => 3, 'restored' => 1, 'conflicted' => 2, 'remaining' => 2])
            ->assertJsonPath('data.conflicts.0.conflicts.0.code', 'unsupported_snapshot');

        $this->assertModelExists($group);
        $this->assertDatabaseCount('reference_items', 0);
        $this->assertSame([99, 99], $archive->records()->pluck('schema_version')->all());
        $this->assertTrue($archive->fresh()->context['parent_resolved']);
    }

    public function test_restore_preserves_nullable_timestamps(): void
    {
        $actor = UserFactory::new()->create();
        $group = ReferenceGroup::factory()->create();
        $item = ReferenceItem::factory()->for($group, 'group')->create();
        $group->timestamps = false;
        $group->forceFill(['created_at' => null, 'updated_at' => null])->save();
        $item->timestamps = false;
        $item->forceFill(['created_at' => null, 'updated_at' => null])->save();
        $archive = app(ArchiveReference::class)->group($group, $actor);
        $input = $this->confirmation($archive, $actor, 'parent_with_children');
        $this->postJson(route('archives.restore', $archive->uuid), $input)->assertOk();
        $this->assertNull($group->fresh()->created_at);
        $this->assertNull($group->fresh()->updated_at);
        $this->assertNull($item->fresh()->created_at);
        $this->assertNull($item->fresh()->updated_at);
    }

    #[DataProvider('groupConflicts')]
    public function test_new_group_conflict_after_preview_cancels_everything(string $column): void
    {
        [$archive, $actor, $group] = $this->package();
        $input = $this->confirmation($archive, $actor, 'parent_with_children');
        $other = ReferenceGroup::factory()->create([$column => $group->{$column}]);
        $otherAttributes = $other->getAttributes();

        $this->postJson(route('archives.restore', $archive->uuid), $input)->assertConflict()
            ->assertJsonPath('code', 'archive_changed');
        $this->assertEquals($otherAttributes, $other->fresh()->getAttributes());
        $this->assertDatabaseCount('reference_groups', 1);
        $this->assertDatabaseCount('reference_items', 0);
        $this->assertDatabaseCount('archive_records', 3);
        $this->assertFalse($archive->fresh()->context['parent_resolved']);
        $this->assertSame(1, $archive->fresh()->revision);
    }

    /** @return array<string, array{string}> */
    public static function groupConflicts(): array
    {
        return ['name' => ['name'], 'id' => ['id'], 'uuid' => ['uuid']];
    }

    public function test_new_child_conflict_after_preview_does_not_silently_change_safe_subset(): void
    {
        [$archive, $actor, $group, $items] = $this->package();
        $input = $this->confirmation($archive, $actor, 'parent_with_children');
        $other = ReferenceItem::factory()->create(['uuid' => $items->first()->uuid]);

        $this->postJson(route('archives.restore', $archive->uuid), $input)->assertConflict()
            ->assertJsonPath('code', 'archive_changed');
        $this->assertModelMissing($group);
        $this->assertModelExists($other);
        $this->assertDatabaseCount('archive_records', 3);
        $this->assertDatabaseCount('reference_items', 1);
    }

    public function test_changed_archive_revision_is_rejected_before_any_write(): void
    {
        [$archive, $actor, $group] = $this->package();
        $input = $this->confirmation($archive, $actor, 'parent_only');
        $archive->increment('revision');

        $this->postJson(route('archives.restore', $archive->uuid), $input)->assertConflict()
            ->assertJsonPath('code', 'archive_changed');
        $this->assertModelMissing($group);
        $this->assertDatabaseCount('archive_records', 3);
        $this->assertSame(2, $archive->fresh()->revision);
    }

    public function test_unique_violation_during_insert_rolls_back_and_requires_new_preview(): void
    {
        [$archive, $actor, $group, $items] = $this->package();
        $other = ReferenceItem::factory()->create();
        $input = $this->confirmation($archive, $actor, 'parent_with_children');
        ReferenceItem::creating(function (ReferenceItem $model) use ($items, $other): void {
            if ($model->uuid === $items->last()->uuid) {
                $model->uuid = $other->uuid;
            }
        });

        try {
            $this->postJson(route('archives.restore', $archive->uuid), $input)->assertConflict()
                ->assertJsonPath('code', 'archive_changed');
        } finally {
            ReferenceItem::getEventDispatcher()->forget('eloquent.creating: ' . ReferenceItem::class);
        }

        $this->assertModelMissing($group);
        $this->assertModelExists($other);
        $this->assertDatabaseCount('reference_items', 1);
        $this->assertDatabaseCount('archive_records', 3);
        $this->assertFalse($archive->fresh()->context['parent_resolved']);
        $this->assertSame(1, $archive->fresh()->revision);
    }

    public function test_failure_after_restoring_rows_rolls_back_active_data_and_archive_cleanup(): void
    {
        [$archive, $actor, $group, $items] = $this->package();
        $input = $this->confirmation($archive, $actor, 'parent_with_children');
        Exceptions::fake();
        ArchiveRecord::deleting(function (ArchiveRecord $record) use ($items): void {
            if ($record->source_uuid === $items->last()->uuid) {
                throw new RuntimeException('Simulasi gagal membersihkan child terakhir.');
            }
        });

        try {
            $this->postJson(route('archives.restore', $archive->uuid), $input)->assertInternalServerError();
        } finally {
            ArchiveRecord::getEventDispatcher()->forget('eloquent.deleting: ' . ArchiveRecord::class);
        }

        Exceptions::assertReported(RuntimeException::class);
        $this->assertModelMissing($group);
        $this->assertDatabaseCount('reference_items', 0);
        $this->assertDatabaseCount('archive_records', 3);
        $this->assertSame(1, $archive->fresh()->revision);
        $this->assertFalse($archive->fresh()->context['parent_resolved']);
    }

    public function test_cancelled_model_save_does_not_discard_archived_parent(): void
    {
        [$archive, $actor, $group] = $this->package();
        $input = $this->confirmation($archive, $actor, 'parent_only');
        Exceptions::fake();
        ReferenceGroup::creating(fn(): bool => false);

        try {
            $this->postJson(route('archives.restore', $archive->uuid), $input)->assertInternalServerError();
        } finally {
            ReferenceGroup::getEventDispatcher()->forget('eloquent.creating: ' . ReferenceGroup::class);
        }

        Exceptions::assertReported(RuntimeException::class);
        $this->assertModelMissing($group);
        $this->assertDatabaseCount('archive_records', 3);
        $this->assertSame(1, $archive->fresh()->revision);
    }

    public function test_invalid_token_wrong_actor_and_changed_mode_are_rejected(): void
    {
        [$archive, $actor, $group] = $this->package();
        $input = $this->confirmation($archive, $actor, 'parent_only');
        $this->postJson(route('archives.restore', $archive->uuid), [...$input, 'confirmation_token' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors('confirmation_token');
        $this->postJson(route('archives.restore', $archive->uuid), [...$input, 'mode' => 'parent_with_children'])
            ->assertUnprocessable()->assertJsonValidationErrors('confirmation_token');
        $this->actingAs(UserFactory::new()->create())->postJson(route('archives.restore', $archive->uuid), $input)
            ->assertUnprocessable()->assertJsonValidationErrors('confirmation_token');
        $this->assertModelMissing($group);
        $this->assertDatabaseCount('archive_records', 3);
    }

    public function test_other_logged_in_user_can_restore_with_their_own_preview(): void
    {
        [$archive, , $group] = $this->package();
        $actor = UserFactory::new()->create();
        $input = $this->confirmation($archive, $actor, 'parent_only');
        $this->postJson(route('archives.restore', $archive->uuid), $input)->assertOk();
        $this->assertModelExists($group);
    }

    public function test_client_payload_and_candidate_list_cannot_expand_or_change_restore(): void
    {
        [$archive, $actor, $group] = $this->package();
        $input = $this->confirmation($archive, $actor, 'parent_only');
        $child = $archive->records()->where('entity', ReferenceSnapshot::ITEM)->firstOrFail();

        $this->postJson(route('archives.restore', $archive->uuid), [
            ...$input,
            'payload' => ['name' => 'Nama dari request', 'id' => 9999],
            'candidates' => [['record_uuid' => $child->uuid, 'status' => 'ready']],
            'context' => ['parent_resolved' => false],
        ])->assertOk()->assertJsonPath('data.counts.restored', 1);

        $this->assertEquals($group->getAttributes(), ReferenceGroup::query()->sole()->getAttributes());
        $this->assertDatabaseCount('reference_items', 0);
        $this->assertDatabaseCount('archive_records', 2);
        $this->assertTrue($archive->fresh()->context['parent_resolved']);
    }

    #[DataProvider('unsupportedInputs')]
    public function test_future_modes_merge_and_delete_are_not_exposed(array $changes, string $field): void
    {
        [$archive, $actor, $group] = $this->package();
        $input = $this->confirmation($archive, $actor, 'parent_only');
        $this->postJson(route('archives.restore', $archive->uuid), [...$input, ...$changes])
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertModelMissing($group);
        $this->assertDatabaseCount('archive_records', 3);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function unsupportedInputs(): array
    {
        return [
            'all children' => [['mode' => 'children_all'], 'mode'],
            'single child' => [['mode' => 'child'], 'mode'],
            'selected children' => [['mode' => 'children_selected'], 'mode'],
            'merge' => [['target_group_uuid' => '018e6fa0-9382-7f17-a13c-33b147f24961'], 'target_group_uuid'],
            'delete' => [['action' => 'delete_forever'], 'action'],
            'record selection' => [['record_uuids' => ['018e6fa0-9382-7f17-a13c-33b147f24961']], 'record_uuids'],
            'missing token' => [['confirmation_token' => null], 'confirmation_token'],
        ];
    }

    #[DataProvider('parentModes')]
    public function test_repeated_confirmation_does_not_duplicate_data(string $mode): void
    {
        [$archive, $actor] = $this->package();
        $input = $this->confirmation($archive, $actor, $mode);
        $this->postJson(route('archives.restore', $archive->uuid), $input)->assertOk();
        $this->postJson(route('archives.restore', $archive->uuid), $input)->assertStatus($mode === 'parent_only' ? 409 : 404);
        $this->assertDatabaseCount('reference_groups', 1);
        $this->assertDatabaseCount('reference_items', $mode === 'parent_only' ? 0 : 2);
        $this->assertDatabaseCount('archive_records', $mode === 'parent_only' ? 2 : 0);
    }

    public function test_guests_unknown_archives_and_numeric_ids_are_rejected(): void
    {
        [$archive, $actor, $group] = $this->package();
        $input = ['action' => 'restore', 'mode' => 'parent_only', 'confirmation_token' => 'invalid'];
        $this->postJson(route('archives.restore', $archive->uuid), $input)->assertUnauthorized();
        $this->actingAs($actor)->postJson(route('archives.restore', fake()->uuid()), $input)->assertNotFound();
        $this->postJson(route('archives.restore', $archive->id), $input)->assertNotFound();
        $this->assertModelMissing($group);
        $this->assertDatabaseCount('archive_records', 3);
    }

    public function test_mismatched_parent_context_blocks_parent_and_children_in_preview(): void
    {
        [$archive, $actor] = $this->package();
        $archive->update(['context' => [...$archive->context, 'parent' => [...$archive->context['parent'], 'source_uuid' => fake()->uuid()]]]);
        $this->actingAs($actor)->postJson(route('archives.preview', $archive->uuid), ['action' => 'restore', 'mode' => 'parent_with_children'])
            ->assertOk()->assertJsonPath('data.can_confirm', false)
            ->assertJsonPath('data.candidates.0.conflicts.0.code', 'parent_mismatch')
            ->assertJsonPath('data.candidates.1.conflicts.0.code', 'parent_mismatch');
        $this->assertDatabaseCount('archive_records', 3);
        $this->assertDatabaseCount('reference_groups', 0);
    }

    /** @return array{Archive, User, ReferenceGroup, Collection<int, ReferenceItem>} */
    private function package(int $childCount = 2): array
    {
        $actor = UserFactory::new()->create();
        $group = ReferenceGroup::factory()->create(['created_at' => '2025-01-01 08:00:00', 'updated_at' => '2025-03-01 09:00:00']);
        $items = ReferenceItem::factory()->count($childCount)->for($group, 'group')
            ->sequence(['sort_order' => 2], ['sort_order' => 9])
            ->create(['created_at' => '2025-02-01 08:00:00', 'updated_at' => '2025-04-01 09:00:00']);
        $archive = app(ArchiveReference::class)->group($group, $actor);

        return [$archive, $actor, $group, $items];
    }

    /** @return array{action: string, mode: string, confirmation_token: string} */
    private function confirmation(Archive $archive, User $actor, string $mode): array
    {
        $response = $this->actingAs($actor)->postJson(route('archives.preview', $archive->uuid), ['action' => 'restore', 'mode' => $mode])
            ->assertOk()->assertJsonPath('data.can_confirm', true);
        $token = $response->json('data.confirmation_token');
        $this->assertIsString($token);

        return ['action' => 'restore', 'mode' => $mode, 'confirmation_token' => $token];
    }
}
