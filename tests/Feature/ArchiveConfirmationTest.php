<?php

namespace Tests\Feature;

use App\Exceptions\ArchiveChangedException;
use App\Models\Archive;
use App\Models\ArchiveRecord;
use App\Models\MasterData\ReferenceGroup;
use App\Models\MasterData\ReferenceItem;
use App\Models\MasterData\User;
use App\Services\Archive\ArchiveConfirmation;
use App\Services\Archive\ArchiveReference;
use App\Services\Archive\PreviewArchive;
use App\Services\Archive\ReferenceSnapshot;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ArchiveConfirmationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_token_verifies_unchanged_selection_without_mutation(): void
    {
        [$archive, $actor] = $this->package();
        $selection = ['action' => 'restore', 'mode' => 'parent_with_children'];
        $preview = app(PreviewArchive::class)->build($archive, $selection);
        $service = app(ArchiveConfirmation::class);
        $token = $service->issue($archive, $actor, $preview);

        $verified = $service->verify($token, $archive, $actor, $selection);

        $this->assertSame($preview['fingerprint'], $verified['fingerprint']);
        $this->assertDatabaseCount('reference_groups', 0);
        $this->assertDatabaseCount('archive_records', 3);
    }

    #[DataProvider('wrongBindings')]
    public function test_token_rejects_changed_user_archive_action_mode_selection_or_target(string $binding): void
    {
        [$archive, $actor] = $this->package();
        $children = $archive->records()->where('entity', ReferenceSnapshot::ITEM)->orderBy('id')->get();
        $target = ReferenceGroup::factory()->create();
        $selection = ['action' => 'restore', 'mode' => 'child', 'record_uuids' => [$children->first()->uuid], 'target_group_uuid' => $target->uuid];
        $service = app(ArchiveConfirmation::class);
        $token = $service->issue($archive, $actor, app(PreviewArchive::class)->build($archive, $selection));
        switch ($binding) {
            case 'actor':
                $actor = UserFactory::new()->create();
                break;
            case 'archive':
                $archive = Archive::factory()->create();
                break;
            case 'action':
                $selection['action'] = 'delete_forever';
                $selection['target_group_uuid'] = null;
                break;
            case 'mode':
                $selection['mode'] = 'children_selected';
                break;
            case 'selection':
                $selection['record_uuids'] = [$children->last()->uuid];
                break;
            case 'target':
                $selection['target_group_uuid'] = ReferenceGroup::factory()->create()->uuid;
                break;
        }
        $this->expectException(ValidationException::class);
        $service->verify($token, $archive, $actor, $selection);
    }

    /** @return array<string, array{string}> */
    public static function wrongBindings(): array
    {
        return array_combine(['actor', 'archive', 'action', 'mode', 'selection', 'target'], array_map(fn(string $value): array => [$value], ['actor', 'archive', 'action', 'mode', 'selection', 'target']));
    }

    #[DataProvider('invalidTokens')]
    public function test_invalid_tokens_are_rejected(string $token): void
    {
        [$archive, $actor] = $this->package();
        $this->expectException(ValidationException::class);
        app(ArchiveConfirmation::class)->verify($token, $archive, $actor, ['action' => 'restore', 'mode' => 'parent_only']);
    }

    /** @return array<string, array{string}> */
    public static function invalidTokens(): array
    {
        return ['plain text' => ['invalid'], 'empty' => ['']];
    }

    public function test_encrypted_invalid_envelope_is_rejected_without_php_object_deserialization(): void
    {
        [$archive, $actor] = $this->package();
        $this->expectException(ValidationException::class);
        app(ArchiveConfirmation::class)->verify(Crypt::encryptString('null'), $archive, $actor, ['action' => 'restore', 'mode' => 'parent_only']);
    }

    #[DataProvider('archiveChanges')]
    public function test_archive_changes_require_new_preview(string $change): void
    {
        [$archive, $actor] = $this->package();
        $selection = ['action' => 'delete_forever', 'mode' => 'package'];
        $service = app(ArchiveConfirmation::class);
        $token = $service->issue($archive, $actor, app(PreviewArchive::class)->build($archive, $selection));
        $record = $archive->records()->orderBy('id')->firstOrFail();
        switch ($change) {
            case 'revision':
                $archive->increment('revision');
                break;
            case 'record':
                $record->update(['label' => 'Berubah']);
                break;
            case 'removed':
                $record->delete();
                break;
            case 'added':
                ArchiveRecord::factory()->for($archive)->create();
                break;
            case 'package removed':
                $archive->delete();
                break;
        }
        $this->expectException(ArchiveChangedException::class);
        $service->verify($token, $archive, $actor, $selection);
    }

    /** @return array<string, array{string}> */
    public static function archiveChanges(): array
    {
        return ['revision' => ['revision'], 'record' => ['record'], 'removed' => ['removed'], 'added' => ['added'], 'package removed' => ['package removed']];
    }

    public function test_new_conflicting_active_group_invalidates_restore_confirmation(): void
    {
        [$archive, $actor, $group] = $this->package();
        $selection = ['action' => 'restore', 'mode' => 'parent_with_children'];
        $service = app(ArchiveConfirmation::class);
        $token = $service->issue($archive, $actor, app(PreviewArchive::class)->build($archive, $selection));
        ReferenceGroup::factory()->create(['name' => $group->name]);

        $this->expectException(ArchiveChangedException::class);
        $service->verify($token, $archive, $actor, $selection);
    }

    public function test_target_item_changes_invalidate_preview_even_with_same_timestamp(): void
    {
        $this->freezeTime();
        [$archive, $actor] = $this->package();
        $target = ReferenceGroup::factory()->create();
        $active = ReferenceItem::factory()->for($target, 'group')->create();
        $selection = ['action' => 'restore', 'mode' => 'children_all', 'target_group_uuid' => $target->uuid];
        $service = app(ArchiveConfirmation::class);
        $token = $service->issue($archive, $actor, app(PreviewArchive::class)->build($archive, $selection));
        $active->update(['sort_order' => 20]);

        $this->expectException(ArchiveChangedException::class);
        $service->verify($token, $archive, $actor, $selection);
    }

    public function test_changed_selection_membership_is_a_stale_confirmation(): void
    {
        [$archive, $actor] = $this->package();
        $record = $archive->records()->where('entity', ReferenceSnapshot::ITEM)->firstOrFail();
        $selection = ['action' => 'delete_forever', 'mode' => 'child', 'record_uuids' => [$record->uuid]];
        $service = app(ArchiveConfirmation::class);
        $token = $service->issue($archive, $actor, app(PreviewArchive::class)->build($archive, $selection));
        $record->delete();

        $this->expectException(ArchiveChangedException::class);
        $service->verify($token, $archive, $actor, $selection);
    }

    public function test_json_key_order_and_input_selection_order_do_not_change_confirmation(): void
    {
        $this->freezeTime();
        [$archive, $actor] = $this->package();
        $ids = $archive->records()->where('entity', ReferenceSnapshot::ITEM)->pluck('uuid')->all();
        $selection = ['action' => 'delete_forever', 'mode' => 'children_selected', 'record_uuids' => $ids];
        $service = app(ArchiveConfirmation::class);
        $plan = app(PreviewArchive::class)->build($archive, $selection);
        $token = $service->issue($archive, $actor, $plan);
        $archive->update(['context' => array_reverse($archive->context, true)]);
        foreach ($archive->records as $record) {
            $record->update(['payload' => array_reverse($record->payload, true)]);
        }
        $selection['record_uuids'] = array_reverse($ids);
        $this->assertSame($plan['fingerprint'], $service->verify($token, $archive, $actor, $selection)['fingerprint']);
    }

    public function test_unrelated_active_changes_do_not_invalidate_delete_confirmation(): void
    {
        [$archive, $actor] = $this->package();
        $selection = ['action' => 'delete_forever', 'mode' => 'package'];
        $service = app(ArchiveConfirmation::class);
        $token = $service->issue($archive, $actor, app(PreviewArchive::class)->build($archive, $selection));
        ReferenceItem::factory()->create();

        $this->assertTrue($service->verify($token, $archive, $actor, $selection)['can_confirm']);
    }

    public function test_known_conflicts_allow_confirmation_of_the_safe_subset(): void
    {
        [$archive, $actor] = $this->package();
        $child = $archive->records()->where('entity', ReferenceSnapshot::ITEM)->firstOrFail();
        $target = ReferenceGroup::factory()->create();
        ReferenceItem::factory()->for($target, 'group')->create(['content' => $child->payload['content']]);
        $selection = ['action' => 'restore', 'mode' => 'children_all', 'target_group_uuid' => $target->uuid];
        $plan = app(PreviewArchive::class)->build($archive, $selection);
        $service = app(ArchiveConfirmation::class);
        $token = $service->issue($archive, $actor, $plan);
        $this->assertSame(['total' => 2, 'ready' => 1, 'conflicted' => 1], $service->verify($token, $archive, $actor, $selection)['counts']);
        $this->assertDatabaseCount('archive_records', 3);
    }

    public function test_changed_exception_has_the_expected_json_contract(): void
    {
        $response = (new ArchiveChangedException())->render(request());
        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('archive_changed', $response->getData(true)['code']);
    }

    /** @return array{Archive, User, ReferenceGroup} */
    private function package(): array
    {
        $actor = UserFactory::new()->create();
        $group = ReferenceGroup::factory()->create();
        ReferenceItem::factory()->count(2)->for($group, 'group')->create();
        $archive = app(ArchiveReference::class)->group($group, $actor);

        return [$archive, $actor, $group];
    }
}
