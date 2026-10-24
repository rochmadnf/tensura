<?php

namespace Tests\Feature;

use App\Models\MasterData\ReferenceGroup;
use App\Models\MasterData\ReferenceItem;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReferenceGroupIndexTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_index_exposes_uuid_and_counts_without_loading_items(): void
    {
        $group = ReferenceGroup::factory()->create(['name' => 'Agama']);
        ReferenceItem::factory()->count(3)->for($group, 'group')->create();
        $this->actingAs(UserFactory::new()->create())
            ->withoutVite()
            ->get(route('ref.index'))
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('reference/index')
                    ->where('page.id', 'a3063dcc-2750-45e0-8ea7-c859c50bd3fb')
                    ->where('filters.keyword', '')
                    ->where('resources.data', [
                        ['id' => $group->uuid, 'name' => 'Agama', 'description' => null, 'items_count' => 3],
                    ])
                    ->where('resources.meta.per_page', 10),
            );
    }

    public function test_search_trims_and_searches_name_and_description_without_searching_items(): void
    {
        $name = ReferenceGroup::factory()->create(['name' => 'Jenis Darah']);
        $description = ReferenceGroup::factory()->create(['name' => 'Lainnya', 'description' => 'Keterangan darah']);
        $other = ReferenceGroup::factory()->create(['name' => 'Agama']);
        ReferenceItem::factory()
            ->for($other, 'group')
            ->create(['content' => 'darah']);
        $this->actingAs(UserFactory::new()->create())
            ->withoutVite()
            ->get(route('ref.index', ['keyword' => ' darah ']))
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->where('filters.keyword', 'darah')
                    ->has('resources.data', 2)
                    ->where('resources.data.0.id', $name->uuid)
                    ->where('resources.data.1.id', $description->uuid),
            );
    }

    #[DataProvider('literalKeywords')]
    public function test_search_treats_wildcards_and_injection_as_literal_text(string $keyword): void
    {
        $match = ReferenceGroup::factory()->create(['name' => 'Grup ' . $keyword]);
        ReferenceGroup::factory()->create(['name' => 'Grup Lain']);
        $this->actingAs(UserFactory::new()->create())
            ->withoutVite()
            ->get(route('ref.index', ['keyword' => $keyword]))
            ->assertInertia(
                fn(Assert $page) => $page->has('resources.data', 1)->where('resources.data.0.id', $match->uuid),
            );
    }

    /** @return array<string, array{string}> */
    public static function literalKeywords(): array
    {
        return ['percent' => ['50%'], 'underscore' => ['A_'], 'backslash' => ['A\\B'], 'sql' => ["' OR 1=1 --"]];
    }

    public function test_short_keyword_is_ignored_and_groups_are_ordered_by_name(): void
    {
        $last = ReferenceGroup::factory()->create(['name' => 'Zebra']);
        $first = ReferenceGroup::factory()->create(['name' => 'Agama']);
        $this->actingAs(UserFactory::new()->create())
            ->withoutVite()
            ->get(route('ref.index', ['keyword' => ' A ']))
            ->assertInertia(
                fn(Assert $page) => $page
                    ->where('filters.keyword', '')
                    ->has('resources.data', 2)
                    ->where('resources.data.0.id', $first->uuid)
                    ->where('resources.data.1.id', $last->uuid),
            );
    }

    public function test_pagination_keeps_keyword_and_page_size(): void
    {
        ReferenceGroup::factory()->count(6)->create();
        $response = $this->actingAs(UserFactory::new()->create())
            ->withoutVite()
            ->get(route('ref.index', ['keyword' => 'Referensi', 'per_page' => 5]));
        $response->assertInertia(
            fn(Assert $page) => $page
                ->has('resources.data', 5)
                ->where('resources.meta.total', 6)
                ->where(
                    'resources.links.next',
                    fn(string $url): bool => str_contains($url, 'keyword=Referensi') &&
                        str_contains($url, 'per_page=5'),
                ),
        );
        $this->get($response->inertiaProps('resources.links.next'))->assertInertia(
            fn(Assert $page) => $page
                ->has('resources.data', 1)
                ->where('resources.meta.current_page', 2)
                ->where('resources.meta.per_page', 5),
        );
    }

    #[DataProvider('pageSizes')]
    public function test_page_size_is_bounded(mixed $input, int $expected): void
    {
        $this->actingAs(UserFactory::new()->create())
            ->withoutVite()
            ->get(route('ref.index', ['per_page' => $input]))
            ->assertInertia(fn(Assert $page) => $page->where('resources.meta.per_page', $expected));
    }

    /** @return array<string, array{mixed, int}> */
    public static function pageSizes(): array
    {
        return [
            'five' => [5, 5],
            'ten' => [10, 10],
            'fifteen' => [15, 15],
            'twenty' => [20, 20],
            'fifty' => [50, 50],
            'hundred' => [100, 100],
            'invalid' => [999, 10],
            'negative' => [-1, 10],
            'text' => ['invalid', 10],
        ];
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('ref.index'))->assertRedirectToRoute('login.index');
    }
}
