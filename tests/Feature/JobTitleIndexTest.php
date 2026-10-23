<?php

namespace Tests\Feature;

use App\Enums\JobTitleGroupEnum;
use App\Models\MasterData\JobTitle;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class JobTitleIndexTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_filters_multiple_groups_and_combines_them_with_search(): void
    {
        $doctor = JobTitle::factory()->create(['name' => 'Jabatan Dokter', 'group' => JobTitleGroupEnum::DOKTER]);
        $nurse = JobTitle::factory()->create(['name' => 'Jabatan Perawat', 'group' => JobTitleGroupEnum::PERAWAT]);
        JobTitle::factory()->create(['name' => 'Lainnya', 'group' => JobTitleGroupEnum::DOKTER]);
        JobTitle::factory()->create(['name' => 'Jabatan Bidan', 'group' => JobTitleGroupEnum::BIDAN]);
        JobTitle::factory()->create(['name' => 'Jabatan Lama', 'group' => null]);

        $this->actingAs(UserFactory::new()->create())
            ->withoutVite()
            ->get(route('jt.index', ['filter' => 'group:1,2', 'keyword' => 'Jabatan']))
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page
                ->where('filters.groups', [1, 2])
                ->where('filters.keyword', 'Jabatan')
                ->where('resources.meta.total', 2)
                ->where('resources.data.0.id', $doctor->uuid)
                ->where('resources.data.1.id', $nurse->uuid)
                ->has('groups', 11)
                ->where('groups.0', ['id' => 1, 'label' => 'Dokter']));
    }

    public function test_normalizes_group_choices_and_filters_a_single_group(): void
    {
        $doctor = JobTitle::factory()->create(['group' => JobTitleGroupEnum::DOKTER]);
        JobTitle::factory()->create(['group' => JobTitleGroupEnum::PERAWAT]);

        $this->actingAs(UserFactory::new()->create())
            ->withoutVite()
            ->get(route('jt.index', ['groups' => ['1', '1', '999', 'invalid', ['2'], '1 OR 1=1', '1.5']]))
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page
                ->where('filters.groups', [1])
                ->has('resources.data', 1)
                ->where('resources.data.0.id', $doctor->uuid));
    }

    #[DataProvider('emptyGroupFilters')]
    public function test_shows_all_groups_including_legacy_nulls_without_valid_choices(mixed $groups): void
    {
        JobTitle::factory()->create(['group' => JobTitleGroupEnum::DOKTER]);
        JobTitle::factory()->create(['group' => null]);

        $this->actingAs(UserFactory::new()->create())
            ->withoutVite()
            ->get(route('jt.index', ['groups' => $groups]))
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page
                ->where('filters.groups', [])
                ->where('resources.meta.total', 2));
    }

    /** @return array<string, array{mixed}> */
    public static function emptyGroupFilters(): array
    {
        return [
            'empty' => [[]],
            'scalar' => ['1'],
            'invalid' => [['0', '-1', '999', 'invalid']],
        ];
    }

    public function test_returns_an_empty_result_for_a_group_without_matches(): void
    {
        JobTitle::factory()->create(['group' => JobTitleGroupEnum::DOKTER]);

        $this->actingAs(UserFactory::new()->create())
            ->withoutVite()
            ->get(route('jt.index', ['groups' => ['2']]))
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page
                ->where('filters.groups', [2])
                ->has('resources.data', 0)
                ->where('resources.meta.total', 0));
    }

    public function test_group_filter_is_applied_before_pagination_and_kept_on_the_next_page(): void
    {
        JobTitle::factory()->count(6)->create(['group' => JobTitleGroupEnum::DOKTER]);
        JobTitle::factory()->create(['group' => JobTitleGroupEnum::PERAWAT]);
        JobTitle::factory()->create(['group' => JobTitleGroupEnum::BIDAN]);

        $response = $this->actingAs(UserFactory::new()->create())
            ->withoutVite()
            ->get(route('jt.index', ['filter' => 'group:1,2', 'per_page' => 5]));

        $response->assertOk()->assertInertia(fn(Assert $page) => $page
            ->has('resources.data', 5)
            ->where('resources.meta.total', 7)
            ->where('resources.links.next', fn(string $url): bool => str_contains(urldecode($url), 'filter=group:1,2')));

        $this->get($response->inertiaProps('resources.links.next'))
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page
                ->where('filters.groups', [1, 2])
                ->has('resources.data', 2)
                ->where('resources.meta.current_page', 2)
                ->where('resources.meta.per_page', 5)
                ->where('resources.meta.total', 7));
    }

    #[DataProvider('compactGroupFilters')]
    public function test_normalizes_compact_group_filters(mixed $filter, array $expectedGroups, int $expectedTotal): void
    {
        JobTitle::factory()->create(['group' => JobTitleGroupEnum::DOKTER]);
        JobTitle::factory()->create(['group' => JobTitleGroupEnum::PERAWAT]);
        JobTitle::factory()->create(['group' => null]);

        $this->actingAs(UserFactory::new()->create())
            ->withoutVite()
            ->get(route('jt.index', ['filter' => $filter]))
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page
                ->where('filters.groups', $expectedGroups)
                ->where('resources.meta.total', $expectedTotal));
    }

    /** @return array<string, array{mixed, list<int>, int}> */
    public static function compactGroupFilters(): array
    {
        return [
            'multiple' => ['group:1,2', [1, 2], 2],
            'duplicates and invalid ids' => ['group:1,1,999,invalid,1.5', [1], 1],
            'query injection' => ['group:2,1 OR 1=1', [2], 1],
            'empty values' => ['group:', [], 3],
            'empty filter' => ['', [], 3],
            'unknown field' => ['other:1,2', [], 3],
            'missing field' => ['1,2', [], 3],
            'array input' => [['group:1'], [], 3],
        ];
    }

    public function test_compact_filter_takes_precedence_over_legacy_group_parameters(): void
    {
        $nurse = JobTitle::factory()->create(['group' => JobTitleGroupEnum::PERAWAT]);
        JobTitle::factory()->create(['group' => JobTitleGroupEnum::DOKTER]);

        $this->actingAs(UserFactory::new()->create())
            ->withoutVite()
            ->get(route('jt.index', ['filter' => 'group:2', 'groups' => ['1']]))
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page
                ->where('filters.groups', [2])
                ->has('resources.data', 1)
                ->where('resources.data.0.id', $nurse->uuid));
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('jt.index'))->assertRedirectToRoute('login.index');
    }

    public function test_index_exposes_uuid_and_handles_legacy_null_group(): void
    {
        $jobTitle = JobTitle::factory()->create(['group' => null]);

        $this->actingAs(UserFactory::new()->create())
            ->withoutVite()
            ->get(route('jt.index'))
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('job-title/index')
                    ->where('page.id', 'a30c2273-d730-4e1a-af2c-479e149cf54b')
                    ->where('filters.keyword', '')
                    ->where('resources.data', [
                        [
                            'id' => $jobTitle->uuid,
                            'name' => $jobTitle->name,
                            'group' => ['id' => null, 'label' => null],
                        ],
                    ])
                    ->where('resources.meta.per_page', 10),
            );
    }

    public function test_search_trims_keyword_and_only_searches_name(): void
    {
        $matching = JobTitle::factory()->create(['name' => 'Dokter Ahli Pertama']);
        JobTitle::factory()->create(['name' => 'Tenaga Medis', 'group' => JobTitleGroupEnum::DOKTER]);

        $this->actingAs(UserFactory::new()->create())
            ->withoutVite()
            ->get(route('jt.index', ['keyword' => '  Dokter  ']))
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->where('filters.keyword', 'Dokter')
                    ->has('resources.data', 1)
                    ->where('resources.data.0.id', $matching->uuid),
            );
    }

    public function test_search_ignores_a_single_character(): void
    {
        JobTitle::factory()->count(2)->create();

        $this->actingAs(UserFactory::new()->create())
            ->withoutVite()
            ->get(route('jt.index', ['keyword' => ' J ']))
            ->assertInertia(fn(Assert $page) => $page->where('filters.keyword', '')->has('resources.data', 2));
    }

    #[DataProvider('literalKeywords')]
    public function test_search_escapes_like_wildcards(string $keyword, string $name): void
    {
        $matching = JobTitle::factory()->create(['name' => $name]);
        JobTitle::factory()->create(['name' => 'Jabatan Lain']);

        $this->actingAs(UserFactory::new()->create())
            ->withoutVite()
            ->get(route('jt.index', ['keyword' => $keyword]))
            ->assertInertia(
                fn(Assert $page) => $page->has('resources.data', 1)->where('resources.data.0.id', $matching->uuid),
            );
    }

    /** @return array<string, array{string, string}> */
    public static function literalKeywords(): array
    {
        return [
            'percent' => ['n%', 'Jabatan%Khusus'],
            'underscore' => ['n_', 'Jabatan_Khusus'],
            'backslash' => ['n\\', 'Jabatan\\Khusus'],
            'quote' => ["n'", "Jabatan'Khusus"],
        ];
    }

    #[DataProvider('pageSizes')]
    public function test_page_size_is_bounded(string $perPage, int $expected): void
    {
        JobTitle::factory()->create();

        $this->actingAs(UserFactory::new()->create())
            ->withoutVite()
            ->get(route('jt.index', ['per_page' => $perPage]))
            ->assertInertia(fn(Assert $page) => $page->where('resources.meta.per_page', $expected));
    }

    /** @return array<string, array{string, int}> */
    public static function pageSizes(): array
    {
        return [
            'five' => ['5', 5],
            'ten' => ['10', 10],
            'fifteen' => ['15', 15],
            'twenty' => ['20', 20],
            'fifty' => ['50', 50],
            'hundred' => ['100', 100],
            'unsupported' => ['7', 10],
            'negative' => ['-5', 10],
            'too large' => ['101', 10],
            'non numeric' => ['invalid', 10],
        ];
    }

    public function test_pagination_orders_by_group_then_name_and_keeps_query_string(): void
    {
        JobTitle::factory()
            ->count(6)
            ->sequence(
                ['name' => 'Jabatan Zeta', 'group' => JobTitleGroupEnum::PERAWAT],
                ['name' => 'Jabatan Echo', 'group' => JobTitleGroupEnum::DOKTER],
                ['name' => 'Jabatan Delta', 'group' => JobTitleGroupEnum::DOKTER],
                ['name' => 'Jabatan Charlie', 'group' => JobTitleGroupEnum::DOKTER],
                ['name' => 'Jabatan Beta', 'group' => JobTitleGroupEnum::DOKTER],
                ['name' => 'Jabatan Alpha', 'group' => JobTitleGroupEnum::DOKTER],
            )
            ->create();

        $this->actingAs(UserFactory::new()->create())->withoutVite();
        $query = ['keyword' => 'Jabatan', 'per_page' => 5];

        $this->get(route('jt.index', $query))->assertInertia(
            fn(Assert $page) => $page
                ->where('resources.data.0.name', 'Jabatan Alpha')
                ->where('resources.data.4.name', 'Jabatan Echo')
                ->where('resources.meta.total', 6)
                ->where(
                    'resources.links.next',
                    fn(string $url): bool => str_contains($url, 'keyword=Jabatan') &&
                        str_contains($url, 'per_page=5') &&
                        str_contains($url, 'page=2'),
                ),
        );

        $this->get(route('jt.index', [...$query, 'page' => 2]))->assertInertia(
            fn(Assert $page) => $page
                ->has('resources.data', 1)
                ->where('resources.data.0.name', 'Jabatan Zeta')
                ->where('resources.meta.from', 6),
        );
    }
}
