<?php

namespace Tests\Feature;

use App\Models\MasterData\ReferenceGroup;
use App\Models\MasterData\ReferenceItem;
use App\Services\Archive\ArchiveReference;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ArchiveDatabaseCompatibilityTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[DataProvider('collationPairs')]
    public function test_group_conflicts_follow_the_database_collation(string $archivedName, string $activeName): void
    {
        $actor = UserFactory::new()->create();
        $source = ReferenceGroup::factory()->create(['name' => $archivedName]);
        $archive = app(ArchiveReference::class)->group($source, $actor);
        $active = ReferenceGroup::factory()->create(['name' => $activeName]);

        $preview = $this->actingAs($actor)->postJson(route('archives.preview', $archive->uuid), [
            'action' => 'restore', 'mode' => 'parent_only',
        ])->assertOk();

        $conflicts = DB::getDriverName() === 'mysql';
        $preview->assertJsonPath('data.can_confirm', !$conflicts)
            ->assertJsonPath('data.counts.conflicted', $conflicts ? 1 : 0);
        if ($conflicts) {
            $preview->assertJsonPath('data.candidates.0.conflicts.0.code', 'name_conflict');
            $this->assertModelExists($archive);
            $this->assertModelMissing($source);
        } else {
            $this->postJson(route('archives.restore', $archive->uuid), [
                'action' => 'restore', 'mode' => 'parent_only',
                'confirmation_token' => $preview->json('data.confirmation_token'),
            ])->assertOk()->assertJsonPath('data.counts.restored', 1);
            $this->assertDatabaseHas('reference_groups', ['uuid' => $source->uuid, 'name' => $archivedName]);
            $this->assertModelMissing($archive);
        }
        $this->assertDatabaseHas('reference_groups', ['uuid' => $active->uuid, 'name' => $activeName]);
    }

    #[DataProvider('collationPairs')]
    public function test_item_conflicts_and_partial_restore_follow_the_database_collation(string $archivedContent, string $activeContent): void
    {
        $actor = UserFactory::new()->create();
        $source = ReferenceGroup::factory()->create();
        $item = ReferenceItem::factory()->for($source, 'group')->create(['content' => $archivedContent, 'sort_order' => 1]);
        $safe = ReferenceItem::factory()->for($source, 'group')->create(['content' => 'Aman dipulihkan', 'sort_order' => 2]);
        $archive = app(ArchiveReference::class)->group($source, $actor);
        $target = ReferenceGroup::factory()->create();
        $active = ReferenceItem::factory()->for($target, 'group')->create(['content' => $activeContent, 'sort_order' => 8]);
        $input = ['action' => 'restore', 'mode' => 'children_all', 'target_group_uuid' => $target->uuid];

        $preview = $this->actingAs($actor)->postJson(route('archives.preview', $archive->uuid), $input)->assertOk();
        $conflicts = DB::getDriverName() === 'mysql';
        $preview->assertJsonPath('data.counts.conflicted', $conflicts ? 1 : 0);
        $this->postJson(route('archives.restore', $archive->uuid), [
            ...$input, 'confirmation_token' => $preview->json('data.confirmation_token'),
        ])->assertOk()->assertJsonPath('data.counts.restored', $conflicts ? 1 : 2)
            ->assertJsonPath('data.counts.remaining', $conflicts ? 2 : 1);

        $this->assertDatabaseHas('reference_items', ['uuid' => $active->uuid, 'content' => $activeContent, 'sort_order' => 8]);
        $this->assertDatabaseHas('reference_items', ['uuid' => $safe->uuid, 'reference_group_id' => $target->id, 'sort_order' => $conflicts ? 9 : 10]);
        $this->assertDatabaseCount('archive_records', $conflicts ? 2 : 1);
        $this->assertSame(!$conflicts, ReferenceItem::query()->where('uuid', $item->uuid)->exists());
        $this->assertModelExists($archive);
    }

    /** @return array<string, array{string, string}> */
    public static function collationPairs(): array
    {
        return [
            'case' => ['Satuan', 'SATUAN'],
            'accent' => ['Café', 'Cafe'],
            'trailing space' => ['Satuan ', 'Satuan'],
        ];
    }

    public function test_json_round_trip_and_nested_key_order_preserve_confirmation_and_original_snapshot(): void
    {
        $this->freezeTime();
        $actor = UserFactory::new()->create();
        $group = ReferenceGroup::factory()->create(['name' => 'Bahasa 日本語', 'description' => null]);
        $item = ReferenceItem::factory()->for($group, 'group')->create(['content' => 'Baris "kutip" \\ 100%_ 🗃️', 'sort_order' => 17]);
        $archive = app(ArchiveReference::class)->group($group, $actor);
        $archive->update(['context' => [...$archive->context, 'metadata' => ['z' => ['null' => null, 'empty' => [], 'text' => '日本語'], 'a' => true]]]);
        $input = ['action' => 'restore', 'mode' => 'parent_with_children'];
        $preview = $this->actingAs($actor)->postJson(route('archives.preview', $archive->uuid), $input)->assertOk();
        $context = $archive->fresh()->context;
        $context['metadata']['z'] = array_reverse($context['metadata']['z'], true);
        $context['metadata'] = array_reverse($context['metadata'], true);
        DB::table('archives')->where('id', $archive->id)->update([
            'context' => json_encode(array_reverse($context, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);
        foreach ($archive->records as $record) {
            DB::table('archive_records')->where('id', $record->id)->update([
                'payload' => json_encode(array_reverse($record->payload, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ]);
        }

        $this->postJson(route('archives.restore', $archive->uuid), [
            ...$input, 'confirmation_token' => $preview->json('data.confirmation_token'),
        ])->assertOk()->assertJsonPath('data.counts.restored', 2);

        $this->assertEquals($group->getAttributes(), $group->fresh()->getAttributes());
        $this->assertEquals($item->getAttributes(), $item->fresh()->getAttributes());
        $this->assertModelMissing($archive);
        $this->assertDatabaseCount('archive_records', 0);
    }

    public function test_archive_and_target_search_ignore_case_and_treat_escape_markers_as_literal_text(): void
    {
        $actor = UserFactory::new()->create();
        $group = ReferenceGroup::factory()->create(['name' => 'Grup sumber']);
        ReferenceItem::factory()->for($group, 'group')->create(['content' => 'Tanda!_%\\Test']);
        $archive = app(ArchiveReference::class)->group($group, $actor);
        $target = ReferenceGroup::factory()->create(['description' => 'Tanda!_%\\Test']);
        ReferenceGroup::factory()->create(['description' => 'Tanda!XYZTest']);
        $keyword = 'tANDA!_%\\tEST';

        $this->actingAs($actor)->withoutVite()->get(route('archives.index', ['keyword' => $keyword]))
            ->assertOk()->assertInertia(fn(Assert $page) => $page->has('resources.data', 1)->where('resources.data.0.uuid', $archive->uuid));
        $this->get(route('ref.archive.index', ['keyword' => $keyword]))
            ->assertOk()->assertInertia(fn(Assert $page) => $page->has('resources.data', 1)->where('resources.data.0.uuid', $archive->uuid));
        $this->get(route('archives.show', ['archive' => $archive->uuid, 'target_keyword' => $keyword]))
            ->assertOk()->assertInertia(fn(Assert $page) => $page->reloadOnly('target_groups', fn(Assert $page) => $page
            ->has('target_groups.data', 1)->where('target_groups.data.0.id', $target->uuid)));
    }
}
