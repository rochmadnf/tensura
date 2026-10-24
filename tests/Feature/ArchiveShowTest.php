<?php

namespace Tests\Feature;

use App\Models\ArchiveRecord;
use App\Models\MasterData\ReferenceGroup;
use App\Models\MasterData\ReferenceItem;
use App\Services\Archive\ArchiveReference;
use App\Services\Archive\ReferenceSnapshot;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ArchiveShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_detail_paginates_only_own_children_and_keeps_parent_separate(): void
    {
        $actor = UserFactory::new()->create();
        $group = ReferenceGroup::factory()->create(['description' => 'Deskripsi asli']);
        ReferenceItem::factory()->count(6)->for($group, 'group')->create();
        $archive = app(ArchiveReference::class)->group($group, $actor);
        ArchiveRecord::factory()->create();
        $response = $this->actingAs($actor)->withoutVite()->get(route('archives.show', ['archive' => $archive->uuid, 'context' => 'reference', 'per_page' => 5]))
            ->assertOk()->assertInertia(fn(Assert $page) => $page->component('archive/show')->where('page.id', 'archive')->where('context', 'reference')
            ->where('resource.data.uuid', $archive->uuid)->where('resource.data.items_count', 6)->where('resource.data.records_count', 7)
            ->where('resource.data.parent_status', 'archived')->where('parent_record.data.description', 'Deskripsi asli')
            ->where('parent_record.data.source_uuid', $group->uuid)->has('resources.data', 5)->where('resources.meta.total', 6));
        $rows = $response->inertiaProps('resources.data');
        $this->assertSame($archive->records()->where('entity', ReferenceSnapshot::ITEM)->orderBy('id')->limit(5)->pluck('uuid')->all(), array_column($rows, 'uuid'));
        $this->assertArrayNotHasKey('payload', $rows[0]);
        $this->assertArrayNotHasKey('context', $response->inertiaProps('resource.data'));
        $next = $response->inertiaProps('resources.links.next');
        $this->assertStringContainsString('context=reference', $next);
        $this->get($next)->assertInertia(fn(Assert $page) => $page->has('resources.data', 1)->where('resources.meta.current_page', 2));
        $this->assertDatabaseCount('archive_records', 8);
        $this->assertDatabaseCount('reference_groups', 0);
    }

    public function test_standalone_item_shows_active_parent_and_original_identity(): void
    {
        $actor = UserFactory::new()->create();
        $item = ReferenceItem::factory()->create();
        $group = $item->group;
        $archive = app(ArchiveReference::class)->item($group, $item, $actor);
        $this->actingAs($actor)->withoutVite()->get(route('archives.show', $archive->uuid))->assertOk()
            ->assertInertia(fn(Assert $page) => $page->where('parent_record', null)->where('resource.data.parent_status', 'active')
                ->where('resource.data.target.uuid', $group->uuid)->where('resources.data.0.source_uuid', $item->uuid)
                ->where('resources.data.0.uuid', $archive->records()->sole()->uuid)->where('resources.data.0.supported', true));
    }

    public function test_unsupported_snapshot_has_reason_without_exposing_unknown_payload(): void
    {
        $record = ArchiveRecord::factory()->create(['schema_version' => 99, 'payload' => ['secret' => 'internal']]);
        $this->actingAs(UserFactory::new()->create())->withoutVite()->get(route('archives.show', $record->archive->uuid))->assertOk()
            ->assertInertia(fn(Assert $page) => $page->where('parent_record.data.supported', false)->where('parent_record.data.description', null)
                ->where('parent_record.data.unsupported_reason', 'Entitas, isi snapshot, atau versi skema belum didukung untuk pemulihan.')->missing('parent_record.data.payload'));
    }

    public function test_empty_group_has_detail_without_children(): void
    {
        $actor = UserFactory::new()->create();
        $archive = app(ArchiveReference::class)->group(ReferenceGroup::factory()->create(), $actor);
        $this->actingAs($actor)->withoutVite()->get(route('archives.show', $archive->uuid))->assertOk()
            ->assertInertia(fn(Assert $page) => $page->has('resources.data', 0)->where('resource.data.items_count', 0)->where('resource.data.records_count', 1));
    }

    public function test_guest_numeric_id_and_unknown_uuid_are_rejected(): void
    {
        $record = ArchiveRecord::factory()->create();
        $this->get(route('archives.show', $record->archive->uuid))->assertRedirectToRoute('login.index');
        $this->actingAs(UserFactory::new()->create())->get(route('archives.show', $record->archive->id))->assertNotFound();
        $this->get(route('archives.show', fake()->uuid()))->assertNotFound();
    }

    public function test_target_picker_is_optional_and_paginates_active_groups_independently(): void
    {
        $record = ArchiveRecord::factory()->create();
        ReferenceGroup::factory()->count(11)->sequence(fn($sequence): array => ['name' => sprintf('Tujuan %02d', $sequence->index)])->create();
        $actor = UserFactory::new()->create();
        $this->actingAs($actor)->withoutVite()->get(route('archives.show', $record->archive->uuid))->assertOk()
            ->assertInertia(fn(Assert $page) => $page->missing('target_groups'));

        $this->get(route('archives.show', ['archive' => $record->archive->uuid, 'target_page' => 2, 'page' => 3, 'context' => 'reference']))
            ->assertOk()->assertInertia(fn(Assert $page) => $page->reloadOnly('target_groups', fn(Assert $page) => $page->has('target_groups.data', 1)
            ->where('target_groups.data.0.name', 'Tujuan 10')->where('target_groups.meta.current_page', 2)
            ->where('target_groups.meta.total', 11)->missing('resources')->missing('target_groups.data.0.items')
            ->where('target_groups.links.prev', fn(string $url): bool => str_contains($url, 'context=reference'))));
        $this->assertDatabaseCount('archive_records', 1);
    }

    #[DataProvider('targetKeywords')]
    public function test_target_search_matches_literal_names_or_descriptions_without_loading_items(string $keyword): void
    {
        $record = ArchiveRecord::factory()->create();
        $group = ReferenceGroup::factory()->create(['name' => 'Grup tujuan', 'description' => 'Keterangan ' . $keyword]);
        ReferenceItem::factory()->for($group, 'group')->create();
        ReferenceGroup::factory()->create(['name' => 'Lainnya']);

        $this->actingAs(UserFactory::new()->create())->withoutVite()->get(route('archives.show', [
            'archive' => $record->archive->uuid, 'target_keyword' => ' ' . $keyword . ' ',
        ]))->assertOk()->assertInertia(fn(Assert $page) => $page->reloadOnly('target_groups', fn(Assert $page) => $page->has('target_groups.data', 1)->where('target_groups.data.0.id', $group->uuid)
            ->where('target_groups.data.0.items_count', 1)->missing('target_groups.data.0.items')));
    }

    /** @return array<string, array{string}> */
    public static function targetKeywords(): array
    {
        return ['word' => ['khusus'], 'wildcard' => ['50%'], 'underscore' => ['A_'], 'backslash' => ['A\\B'], 'escape marker' => ['A!_%'], 'injection' => ["' OR 1=1 --"]];
    }

    public function test_target_search_rejects_invalid_query_inputs(): void
    {
        $record = ArchiveRecord::factory()->create();
        $this->actingAs(UserFactory::new()->create())->withoutVite()->get(route('archives.show', [
            'archive' => $record->archive->uuid, 'target_keyword' => ['bad'], 'target_page' => -1,
        ]))->assertSessionHasErrors(['target_keyword', 'target_page']);
    }
}
