<?php

namespace Tests\Feature;

use App\Models\Archive;
use App\Models\ArchiveRecord;
use App\Models\MasterData\ReferenceGroup;
use App\Models\MasterData\ReferenceItem;
use App\Models\MasterData\User;
use App\Services\Archive\ArchiveReference;
use App\Services\Archive\PreviewArchive;
use App\Services\Archive\ReferenceSnapshot;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ArchiveDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_package_deletion_preserves_active_data_and_other_archives(): void
    {
        [$archive, $actor] = $this->package();
        $other = Archive::factory()->has(ArchiveRecord::factory(), 'records')->create();
        $active = ReferenceItem::factory()->create();
        $attributes = $active->fresh()->getAttributes();
        $input = $this->confirmation($archive, $actor, 'package');

        $this->deleteJson(route('archives.destroy', $archive->uuid), $input)->assertOk()
            ->assertJsonPath('data.counts', ['requested' => 3, 'deleted' => 3, 'remaining' => 0])
            ->assertJsonPath('data.archive_exists', false);

        $this->assertModelMissing($archive);
        $this->assertModelExists($other);
        $this->assertDatabaseCount('archive_records', 1);
        $this->assertSame($attributes, $active->fresh()->getAttributes());
        $this->assertModelExists($active->group);
        $this->deleteJson(route('archives.destroy', $archive->uuid), $input)->assertNotFound();
        $this->assertDatabaseCount('archive_records', 1);
    }

    public function test_selected_children_preserve_parent_context_and_unselected_records(): void
    {
        [$archive, $actor] = $this->package(3);
        $children = $archive->records()->where('entity', ReferenceSnapshot::ITEM)->orderBy('id')->get();
        $context = $archive->context;
        $input = $this->confirmation($archive, $actor, 'children_selected', [$children[0]->uuid, $children[2]->uuid]);

        $this->deleteJson(route('archives.destroy', $archive->uuid), [...$input, 'candidates' => $children->toArray()])->assertOk()
            ->assertJsonPath('data.counts', ['requested' => 2, 'deleted' => 2, 'remaining' => 2])
            ->assertJsonPath('data.archive_exists', true);

        $this->assertModelMissing($children[0]);
        $this->assertModelMissing($children[2]);
        $this->assertModelExists($children[1]);
        $this->assertSame(PreviewArchive::canonicalJson($context), PreviewArchive::canonicalJson($archive->fresh()->context));
        $this->assertSame(2, $archive->fresh()->revision);
        $this->deleteJson(route('archives.destroy', $archive->uuid), $input)->assertConflict()->assertJsonPath('code', 'archive_changed');
        $this->assertDatabaseCount('archive_records', 2);
    }

    public function test_last_child_deletion_keeps_unresolved_parent(): void
    {
        [$archive, $actor] = $this->package(1);
        $child = $archive->records()->where('entity', ReferenceSnapshot::ITEM)->sole();
        $input = $this->confirmation($archive, $actor, 'child', [$child->uuid]);

        $this->deleteJson(route('archives.destroy', $archive->uuid), $input)->assertOk()->assertJsonPath('data.counts.remaining', 1);

        $this->assertModelExists($archive);
        $this->assertSame(ReferenceSnapshot::GROUP, $archive->records()->sole()->entity);
        $this->assertFalse($archive->fresh()->context['parent_resolved']);
    }

    public function test_last_child_after_parent_restore_cleans_package_and_preserves_parent(): void
    {
        [$archive, $actor, $group] = $this->package(1);
        $restore = $this->actingAs($actor)->postJson(route('archives.preview', $archive->uuid), ['action' => 'restore', 'mode' => 'parent_only'])->json('data.confirmation_token');
        $this->postJson(route('archives.restore', $archive->uuid), ['action' => 'restore', 'mode' => 'parent_only', 'confirmation_token' => $restore])->assertOk();
        $child = $archive->records()->sole();
        $input = $this->confirmation($archive, $actor, 'child', [$child->uuid]);

        $this->deleteJson(route('archives.destroy', $archive->uuid), $input)->assertOk()->assertJsonPath('data.archive_exists', false);

        $this->assertModelMissing($archive);
        $this->assertModelExists($group);
        $this->assertDatabaseCount('archive_records', 0);
    }

    public function test_standalone_item_with_unsupported_snapshot_can_be_deleted(): void
    {
        $actor = UserFactory::new()->create();
        $item = ReferenceItem::factory()->create();
        $group = $item->group;
        $archive = app(ArchiveReference::class)->item($group, $item, $actor);
        $record = $archive->records()->sole();
        $record->update(['schema_version' => 99]);
        $input = $this->confirmation($archive, $actor, 'child', [$record->uuid]);

        $this->deleteJson(route('archives.destroy', $archive->uuid), $input)->assertOk()->assertJsonPath('data.counts.deleted', 1);

        $this->assertModelMissing($archive);
        $this->assertModelExists($group);
        $this->assertModelMissing($item);
    }

    #[DataProvider('changedConditions')]
    public function test_changed_conditions_return_409_without_deletion(string $change): void
    {
        [$archive, $actor] = $this->package();
        $input = $this->confirmation($archive, $actor, 'package');
        match ($change) {
            'revision' => $archive->increment('revision'),
            'record' => $archive->records()->firstOrFail()->update(['label' => 'Berubah']),
            'added' => ArchiveRecord::factory()->for($archive)->create(),
            'removed' => $archive->records()->firstOrFail()->delete(),
        };
        $before = $archive->records()->orderBy('id')->get()->toArray();

        $this->deleteJson(route('archives.destroy', $archive->uuid), $input)->assertConflict()->assertJsonPath('code', 'archive_changed');

        $this->assertSame($before, $archive->records()->orderBy('id')->get()->toArray());
        $this->assertModelExists($archive);
    }

    public static function changedConditions(): array
    {
        return ['revision' => ['revision'], 'record' => ['record'], 'added' => ['added'], 'removed' => ['removed']];
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_scope_or_confirmation_returns_422(array $changes, string $field): void
    {
        [$archive, $actor] = $this->package();
        $input = $this->confirmation($archive, $actor, 'package');

        $this->deleteJson(route('archives.destroy', $archive->uuid), [...$input, ...$changes])
            ->assertUnprocessable()->assertJsonValidationErrors($field);

        $this->assertDatabaseCount('archive_records', 3);
        $this->assertSame(1, $archive->fresh()->revision);
    }

    public static function invalidInputs(): array
    {
        return [
            'action' => [['action' => 'restore'], 'action'],
            'parent' => [['mode' => 'parent_only'], 'mode'],
            'empty' => [['mode' => 'children_selected'], 'record_uuids'],
            'package selection' => [['record_uuids' => ['018e6fa0-9382-7f17-a13c-33b147f24961']], 'record_uuids'],
            'target' => [['target_group_uuid' => '018e6fa0-9382-7f17-a13c-33b147f24961'], 'target_group_uuid'],
            'missing token' => [['confirmation_token' => null], 'confirmation_token'],
            'invalid token' => [['confirmation_token' => 'invalid'], 'confirmation_token'],
        ];
    }

    public function test_token_cannot_change_actor_archive_action_or_selected_records(): void
    {
        [$archive, $actor] = $this->package();
        $children = $archive->records()->where('entity', ReferenceSnapshot::ITEM)->get();
        $input = $this->confirmation($archive, $actor, 'child', [$children[0]->uuid]);
        $this->deleteJson(route('archives.destroy', $archive->uuid), [...$input, 'record_uuids' => [$children[1]->uuid]])->assertUnprocessable()->assertJsonValidationErrors('confirmation_token');
        $other = Archive::factory()->create();
        $this->deleteJson(route('archives.destroy', $other->uuid), $input)->assertUnprocessable()->assertJsonValidationErrors('confirmation_token');
        $this->actingAs(UserFactory::new()->create())->deleteJson(route('archives.destroy', $archive->uuid), $input)->assertUnprocessable()->assertJsonValidationErrors('confirmation_token');
        $restore = $this->actingAs($actor)->postJson(route('archives.preview', $archive->uuid), ['action' => 'restore', 'mode' => 'parent_only'])->json('data.confirmation_token');
        $this->deleteJson(route('archives.destroy', $archive->uuid), ['action' => 'delete_forever', 'mode' => 'package', 'confirmation_token' => $restore])->assertUnprocessable()->assertJsonValidationErrors('confirmation_token');
        $this->assertDatabaseCount('archive_records', 3);
    }

    public function test_parent_or_foreign_record_cannot_be_selected_as_child(): void
    {
        [$archive, $actor] = $this->package();
        $parent = $archive->records()->where('entity', ReferenceSnapshot::GROUP)->sole();
        $foreign = ArchiveRecord::factory()->create();
        foreach ([$parent->uuid, $foreign->uuid] as $uuid) {
            $this->actingAs($actor)->deleteJson(route('archives.destroy', $archive->uuid), ['action' => 'delete_forever', 'mode' => 'child', 'record_uuids' => [$uuid], 'confirmation_token' => 'invalid'])
                ->assertUnprocessable();
            $this->postJson(route('archives.preview', $archive->uuid), ['action' => 'delete_forever', 'mode' => 'child', 'record_uuids' => [$uuid]])->assertUnprocessable()->assertJsonValidationErrors('record_uuids');
        }
        $this->assertDatabaseCount('archive_records', 4);
    }

    #[DataProvider('failurePoints')]
    public function test_failure_rolls_back_all_deletions_and_revision(string $failure): void
    {
        [$archive, $actor] = $this->package();
        $input = $this->confirmation($archive, $actor, 'package');
        $lastId = $archive->records()->max('id');
        Exceptions::fake();
        if ($failure === 'record') {
            ArchiveRecord::deleting(fn(ArchiveRecord $record): bool => $record->id !== $lastId);
        } else {
            Archive::deleting(fn(): bool => false);
        }
        try {
            $this->deleteJson(route('archives.destroy', $archive->uuid), $input)->assertInternalServerError();
        } finally {
            ArchiveRecord::flushEventListeners();
            Archive::flushEventListeners();
        }
        Exceptions::assertReported(RuntimeException::class);
        $this->assertDatabaseCount('archive_records', 3);
        $this->assertSame(1, $archive->fresh()->revision);
    }

    public static function failurePoints(): array
    {
        return ['record' => ['record'], 'package' => ['package']];
    }

    public function test_guests_numeric_ids_and_unknown_archives_are_rejected(): void
    {
        [$archive, $actor] = $this->package();
        $this->deleteJson(route('archives.destroy', $archive->uuid))->assertUnauthorized();
        $this->actingAs($actor)->deleteJson(route('archives.destroy', $archive->id))->assertNotFound();
        $this->deleteJson(route('archives.destroy', fake()->uuid()))->assertNotFound();
        $this->assertDatabaseCount('archive_records', 3);
    }

    /** @return array{Archive, User, ReferenceGroup} */
    private function package(int $children = 2): array
    {
        $actor = UserFactory::new()->create();
        $group = ReferenceGroup::factory()->create();
        ReferenceItem::factory()->count($children)->for($group, 'group')->create();

        return [app(ArchiveReference::class)->group($group, $actor), $actor, $group];
    }

    /** @param list<string> $uuids
     * @return array<string, mixed>
     */
    private function confirmation(Archive $archive, User $actor, string $mode, array $uuids = []): array
    {
        $input = ['action' => 'delete_forever', 'mode' => $mode, 'record_uuids' => $uuids];
        $response = $this->actingAs($actor)->postJson(route('archives.preview', $archive->uuid), $input)->assertOk()->assertJsonPath('data.can_confirm', true);

        return [...$input, 'confirmation_token' => $response->json('data.confirmation_token')];
    }
}
