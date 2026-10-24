<?php

namespace Tests\Feature;

use App\Enums\ArchiveKindEnum;
use App\Models\Archive;
use App\Models\ArchiveRecord;
use App\Models\MasterData\ReferenceGroup;
use App\Services\Archive\ReferenceSnapshot;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ArchiveIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_entries_share_reference_data_with_correct_context(): void
    {
        $archive = Archive::factory()->has(ArchiveRecord::factory(), 'records')->create();
        $other = Archive::factory()->create(['module' => 'other']);
        $actor = UserFactory::new()->create();

        $this->actingAs($actor)->withoutVite()->get(route('archives.index'))->assertOk()->assertInertia(
            fn(Assert $page) => $page->component('archive/index')->where('context', null)->where('page.id', 'archive')
                ->has('resources.data', 2)->where('resources.data.0.uuid', $other->uuid)->where('resources.data.1.uuid', $archive->uuid),
        );
        $central = $this->get(route('archives.index', ['module' => 'reference']))->assertOk();
        $reference = $this->get(route('ref.archive.index', ['module' => 'other']))->assertOk()->assertInertia(
            fn(Assert $page) => $page->where('context', 'reference')->where('filters.module', 'reference')->has('resources.data', 1),
        );
        $this->assertSame($central->inertiaProps('resources.data'), $reference->inertiaProps('resources.data'));
    }

    public function test_search_matches_children_once_and_counts_all_remaining_items(): void
    {
        $archive = Archive::factory()->create(['label' => 'Grup utama']);
        ArchiveRecord::factory()->count(2)->for($archive)->create(['entity' => ReferenceSnapshot::ITEM, 'label' => 'Konten cocok']);
        ArchiveRecord::factory()->for($archive)->create(['entity' => ReferenceSnapshot::ITEM, 'label' => 'Lainnya']);
        Archive::factory()->create(['label' => 'Tidak terkait']);

        $this->actingAs(UserFactory::new()->create())->withoutVite()->get(route('archives.index', ['keyword' => ' cocok ']))
            ->assertOk()->assertInertia(fn(Assert $page) => $page->where('filters.keyword', 'cocok')->has('resources.data', 1)
            ->where('resources.meta.total', 1)->where('resources.data.0.uuid', $archive->uuid)->where('resources.data.0.items_count', 3));
    }

    #[DataProvider('literalKeywords')]
    public function test_search_treats_wildcards_and_injection_as_literal_text(string $keyword): void
    {
        $archive = Archive::factory()->create(['label' => 'Grup ' . $keyword]);
        Archive::factory()->create(['label' => 'Grup lain']);

        $this->actingAs(UserFactory::new()->create())->withoutVite()->get(route('archives.index', ['keyword' => $keyword]))
            ->assertOk()->assertInertia(fn(Assert $page) => $page->has('resources.data', 1)->where('resources.data.0.uuid', $archive->uuid));
    }

    public static function literalKeywords(): array
    {
        return ['percent' => ['50%'], 'underscore' => ['A_'], 'backslash' => ['A\\B'], 'sql' => ["' OR 1=1 --"]];
    }

    public function test_module_kind_and_keyword_filters_combine_and_remain_in_pagination(): void
    {
        $archives = Archive::factory()->count(6)->create(['label' => 'Cocok', 'archived_at' => '2026-01-01 00:00:00']);
        Archive::factory()->create(['label' => 'Cocok', 'kind' => ArchiveKindEnum::RECORD]);
        Archive::factory()->create(['label' => 'Cocok', 'module' => 'other']);
        $response = $this->actingAs(UserFactory::new()->create())->withoutVite()->get(route('archives.index', [
            'module' => 'reference', 'kind' => 'package', 'keyword' => 'Cocok', 'per_page' => 5,
        ]))->assertOk()->assertInertia(fn(Assert $page) => $page->has('resources.data', 5)->where('resources.meta.total', 6)
            ->where('resources.data.0.uuid', $archives->last()->uuid));
        $next = $response->inertiaProps('resources.links.next');
        $this->assertStringContainsString('module=reference', $next);
        $this->assertStringContainsString('kind=package', $next);
        $this->assertStringContainsString('keyword=Cocok', $next);
        $this->get($next)->assertInertia(fn(Assert $page) => $page->has('resources.data', 1)->where('resources.meta.current_page', 2));
    }

    public function test_newest_archival_time_precedes_id_tiebreaker(): void
    {
        $newer = Archive::factory()->create(['archived_at' => '2026-10-01 00:00:00']);
        Archive::factory()->create(['archived_at' => '2026-01-01 00:00:00']);

        $this->actingAs(UserFactory::new()->create())->withoutVite()->get(route('archives.index'))
            ->assertInertia(fn(Assert $page) => $page->where('resources.data.0.uuid', $newer->uuid));
    }

    public function test_parent_status_tracks_archived_active_missing_and_merge_targets(): void
    {
        $group = ReferenceGroup::factory()->create();
        $archived = Archive::factory()->has(ArchiveRecord::factory(), 'records')->create();
        $active = Archive::factory()->create(['context' => ['parent' => ['source_uuid' => $group->uuid, 'label' => 'Nama asal'], 'parent_resolved' => true]]);
        $missing = Archive::factory()->create(['context' => ['parent_resolved' => true, 'parent' => ['source_uuid' => fake()->uuid()]]]);
        $merged = Archive::factory()->create(['context' => ['target' => ['uuid' => $group->uuid], 'parent_resolved' => true]]);
        $lostTarget = Archive::factory()->create(['context' => ['target' => ['uuid' => fake()->uuid()], 'parent' => ['source_uuid' => $group->uuid], 'parent_resolved' => true]]);

        $response = $this->actingAs(UserFactory::new()->create())->withoutVite()->get(route('archives.index'))->assertOk();
        $rows = collect($response->inertiaProps('resources.data'))->keyBy('uuid');
        $this->assertSame('archived', $rows[$archived->uuid]['parent_status']);
        $this->assertSame('active', $rows[$active->uuid]['parent_status']);
        $this->assertSame('missing', $rows[$missing->uuid]['parent_status']);
        $this->assertSame($group->uuid, $rows[$merged->uuid]['target']['uuid']);
        $this->assertSame('missing', $rows[$lostTarget->uuid]['parent_status']);
        $group->delete();
        $this->get(route('archives.index'))->assertInertia(fn(Assert $page) => $page->where('resources.data.1.parent_status', 'missing'));
    }

    public function test_deleted_actor_name_survives_and_internal_payload_is_not_exposed(): void
    {
        $actor = UserFactory::new()->create();
        Archive::factory()->has(ArchiveRecord::factory(), 'records')->create(['archived_by' => $actor->id, 'archived_by_name' => 'Nama penghapus']);
        $actor->delete();
        $response = $this->actingAs(UserFactory::new()->create())->withoutVite()->get(route('archives.index'))->assertOk()
            ->assertInertia(fn(Assert $page) => $page->where('resources.data.0.archived_by_name', 'Nama penghapus'));
        $row = $response->inertiaProps('resources.data.0');
        foreach (['payload', 'context', 'id', 'archived_by', 'revision', 'records'] as $key) {
            $this->assertArrayNotHasKey($key, $row);
        }
    }

    public function test_empty_results_short_keyword_and_invalid_page_size_are_handled(): void
    {
        $this->actingAs(UserFactory::new()->create())->withoutVite()->get(route('archives.index', ['keyword' => ' A ', 'per_page' => 999]))
            ->assertOk()->assertInertia(fn(Assert $page) => $page->has('resources.data', 0)->where('filters.keyword', '')->where('resources.meta.per_page', 10));
        $this->get(route('archives.index', ['kind' => 'bad']))->assertSessionHasErrors('kind');
    }

    public function test_guests_cannot_read_either_entry(): void
    {
        $this->get(route('archives.index'))->assertRedirectToRoute('login.index');
        $this->get(route('ref.archive.index'))->assertRedirectToRoute('login.index');
    }
}
