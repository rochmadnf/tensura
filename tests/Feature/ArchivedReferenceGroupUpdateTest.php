<?php

namespace Tests\Feature;

use App\Models\Archive;
use App\Models\MasterData\ReferenceGroup;
use App\Models\MasterData\ReferenceItem;
use App\Models\MasterData\User;
use App\Services\Archive\ArchiveReference;
use App\Services\Archive\ReferenceSnapshot;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ArchivedReferenceGroupUpdateTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_rename_updates_only_name_and_metadata_and_invalidates_old_token(): void
    {
        [$archive, $actor, $group] = $this->package();
        $root = $archive->records()->where('entity', ReferenceSnapshot::GROUP)->sole();
        $original = $root->payload;
        $children = $archive->records()->where('entity', ReferenceSnapshot::ITEM)->get()->map->getAttributes()->all();
        $preview = $this->actingAs($actor)->postJson(route('archives.preview', $archive->uuid), ['action' => 'restore', 'mode' => 'parent_with_children'])->assertOk();

        $this->patchJson(route('archives.reference-group.update', $archive->uuid), [
            'name' => '  Nama Grup Baru  ', 'description' => 'Tidak boleh berubah', 'payload' => ['id' => 9999],
        ])->assertOk()->assertJsonPath('data.group.name', 'Nama Grup Baru')->assertJsonPath('data.revision', 2);
        $this->assertEquals([...$original, 'name' => 'Nama Grup Baru'], $root->fresh()->payload);
        $this->assertSame('Nama Grup Baru', $root->fresh()->label);
        $this->assertSame('Nama Grup Baru', $archive->fresh()->label);
        $this->assertSame('Nama Grup Baru', $archive->fresh()->context['parent']['label']);
        $this->assertSame($children, $archive->records()->where('entity', ReferenceSnapshot::ITEM)->get()->map->getAttributes()->all());

        $this->postJson(route('archives.restore', $archive->uuid), [
            'action' => 'restore', 'mode' => 'parent_with_children', 'confirmation_token' => $preview->json('data.confirmation_token'),
        ])->assertConflict()->assertJsonPath('code', 'archive_changed');

        $fresh = $this->postJson(route('archives.preview', $archive->uuid), ['action' => 'restore', 'mode' => 'parent_with_children'])->assertOk();
        $this->postJson(route('archives.restore', $archive->uuid), [
            'action' => 'restore', 'mode' => 'parent_with_children', 'confirmation_token' => $fresh->json('data.confirmation_token'),
        ])->assertOk();
        $this->assertEquals([...$group->getAttributes(), 'name' => 'Nama Grup Baru'], $group->fresh()->getAttributes());
    }

    public function test_rename_resolves_active_name_conflict_without_editing_active_group(): void
    {
        [$archive, $actor, $group] = $this->package();
        $active = ReferenceGroup::factory()->create(['name' => $group->name]);
        $this->actingAs($actor)->postJson(route('archives.preview', $archive->uuid), ['action' => 'restore', 'mode' => 'parent_only'])
            ->assertOk()->assertJsonPath('data.can_confirm', false);
        $this->patchJson(route('archives.reference-group.update', $archive->uuid), ['name' => 'Nama Alternatif'])->assertOk();
        $this->postJson(route('archives.preview', $archive->uuid), ['action' => 'restore', 'mode' => 'parent_only'])
            ->assertOk()->assertJsonPath('data.can_confirm', true);
        $this->assertSame($group->name, $active->fresh()->name);
        $this->assertModelMissing($group);
    }

    #[DataProvider('invalidNames')]
    public function test_invalid_names_are_rejected_without_updating_archive(mixed $name): void
    {
        [$archive, $actor] = $this->package();
        $this->actingAs($actor)->patchJson(route('archives.reference-group.update', $archive->uuid), ['name' => $name])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->assertSame(1, $archive->fresh()->revision);
    }

    /** @return array<string, array{mixed}> */
    public static function invalidNames(): array
    {
        return ['empty' => [''], 'short' => ['ab'], 'long' => [str_repeat('a', 101)], 'array' => [['name']], 'missing' => [null]];
    }

    public function test_active_name_collision_is_rejected_but_other_archived_names_are_allowed(): void
    {
        [$archive, $actor] = $this->package();
        $active = ReferenceGroup::factory()->create(['name' => 'Nama Aktif']);
        $this->actingAs($actor)->patchJson(route('archives.reference-group.update', $archive->uuid), ['name' => $active->name])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
        $otherGroup = ReferenceGroup::factory()->create(['name' => 'Nama Arsip Lain']);
        app(ArchiveReference::class)->group($otherGroup, $actor);
        $this->patchJson(route('archives.reference-group.update', $archive->uuid), ['name' => $otherGroup->name])->assertOk();
        $this->assertSame(2, $archive->fresh()->revision);
    }

    public function test_completed_parent_and_unsupported_snapshot_cannot_be_renamed(): void
    {
        [$archive, $actor] = $this->package();
        $root = $archive->records()->where('entity', ReferenceSnapshot::GROUP)->sole();
        $root->update(['schema_version' => 99]);
        $this->actingAs($actor)->patchJson(route('archives.reference-group.update', $archive->uuid), ['name' => 'Nama Baru'])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
        $root->delete();
        $this->patchJson(route('archives.reference-group.update', $archive->uuid), ['name' => 'Nama Baru'])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->assertSame(1, $archive->fresh()->revision);
    }

    public function test_failed_context_update_rolls_back_snapshot_rename(): void
    {
        [$archive, $actor] = $this->package();
        $root = $archive->records()->where('entity', ReferenceSnapshot::GROUP)->sole();
        $old = $root->payload;
        Exceptions::fake();
        Archive::updating(function (): void {
            throw new RuntimeException('Kegagalan konteks.');
        });
        try {
            $this->actingAs($actor)->patchJson(route('archives.reference-group.update', $archive->uuid), ['name' => 'Nama Baru'])->assertInternalServerError();
        } finally {
            Archive::getEventDispatcher()->forget('eloquent.updating: ' . Archive::class);
        }
        Exceptions::assertReported(RuntimeException::class);
        $this->assertEquals($old, $root->fresh()->payload);
        $this->assertSame($old['name'], $archive->fresh()->label);
        $this->assertSame(1, $archive->fresh()->revision);
    }

    public function test_guests_and_invalid_ids_are_rejected_and_other_users_can_rename(): void
    {
        [$archive] = $this->package();
        $this->patchJson(route('archives.reference-group.update', $archive->uuid), ['name' => 'Nama Baru'])->assertUnauthorized();
        $this->actingAs(UserFactory::new()->create())->patchJson(route('archives.reference-group.update', $archive->id), ['name' => 'Nama Baru'])->assertNotFound();
        $this->patchJson(route('archives.reference-group.update', fake()->uuid()), ['name' => 'Nama Baru'])->assertNotFound();
        $this->patchJson(route('archives.reference-group.update', $archive->uuid), ['name' => 'Nama Baru'])->assertOk();
    }

    /** @return array{Archive, User, ReferenceGroup} */
    private function package(): array
    {
        $actor = UserFactory::new()->create();
        $group = ReferenceGroup::factory()->create(['description' => 'Deskripsi asli', 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-02-01 00:00:00']);
        ReferenceItem::factory()->for($group, 'group')->create();

        return [app(ArchiveReference::class)->group($group, $actor), $actor, $group];
    }
}
