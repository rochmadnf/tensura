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
