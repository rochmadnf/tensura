<?php

namespace Tests\Feature;

use App\Enums\AsnStatusEnum;
use App\Enums\ContractStatusEnum;
use App\Models\MasterData\Employee;
use App\Models\MasterData\JobTitle;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmployeeIndexTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('empl.index'))->assertRedirectToRoute('login.index');
    }

    public function test_index_has_page_id_uuid_resources_and_empty_filters(): void
    {
        $employee = Employee::factory()->create();
        $this->actingAs(UserFactory::new()->create())->withoutVite()->get(route('empl.index'))
            ->assertOk()->assertInertia(fn(Assert $page) => $page->component('employee/index')
            ->where('page.id', 'a30c21b9-15ef-4dc0-97ef-524ead13be55')
            ->where('resources.data.0.id', $employee->uuid)
            ->where('resources.data.0.job_title.id', $employee->jobTitle->uuid)
            ->where('resources.meta.per_page', 10)
            ->where('filters', ['keyword' => '', 'job_title' => [], 'contract_status' => [], 'status' => [], 'tenure_min' => null, 'tenure_max' => null]));
    }

    public function test_active_employees_are_first_then_longest_tenure_name_and_id(): void
    {
        $retired = Employee::factory()->create(['status' => AsnStatusEnum::PENSIUN, 'join_date' => '1990-01-01']);
        $transfer = Employee::factory()->create(['status' => AsnStatusEnum::MUTASI, 'join_date' => '2000-01-01']);
        $new = Employee::factory()->create(['name' => 'Amin', 'join_date' => '2020-01-01']);
        $lastName = Employee::factory()->create(['name' => 'Zain', 'join_date' => '2010-01-01']);
        $old = Employee::factory()->create(['name' => 'Budi', 'join_date' => '2010-01-01']);
        $same = Employee::factory()->create(['name' => 'Budi', 'join_date' => '2010-01-01']);
        $this->actingAs(UserFactory::new()->create())->withoutVite()->get(route('empl.index'))->assertOk()
            ->assertInertia(fn(Assert $page) => $page->where('resources.data', fn($rows): bool => collect($rows)->pluck('id')->all() === [$old->uuid, $same->uuid, $lastName->uuid, $new->uuid, $retired->uuid, $transfer->uuid]));
    }

    #[DataProvider('searchFields')]
    public function test_search_trims_keyword_and_matches_each_allowed_field(string $field, string $value): void
    {
        $matching = Employee::factory()->create([$field => $value]);
        Employee::factory()->create(['name' => 'Unrelated', 'nik' => '1234567890123456', 'nip' => null, 'email' => null, 'front_title' => $value]);
        $this->actingAs(UserFactory::new()->create())->withoutVite()->get(route('empl.index', ['keyword' => '  ' . $value . '  ']))
            ->assertOk()->assertInertia(fn(Assert $page) => $page->where('filters.keyword', $value)->has('resources.data', 1)->where('resources.data.0.id', $matching->uuid));
    }

    /** @return array<string, array{string, string}> */
    public static function searchFields(): array
    {
        return ['name' => ['name', 'Search Person'], 'nik' => ['nik', '0000000000000001'], 'nip' => ['nip', '000000000000000001'], 'email' => ['email', 'search@example.test']];
    }

    public function test_search_does_not_match_job_title_or_other_unlisted_columns(): void
    {
        $job = JobTitle::factory()->create(['name' => 'Needle']);
        Employee::factory()->create(['name' => 'Unrelated', 'front_title' => 'Needle', 'back_title' => 'Needle', 'rank' => 'Needle', 'grade' => 'Needle', 'job_title_id' => $job->id]);
        $this->actingAs(UserFactory::new()->create())->withoutVite()->get(route('empl.index', ['keyword' => 'Needle']))
            ->assertInertia(fn(Assert $page) => $page->has('resources.data', 0));
    }

    #[DataProvider('literalKeywords')]
    public function test_search_escapes_like_wildcards_and_sql_injection(string $keyword): void
    {
        $matching = Employee::factory()->create(['name' => 'literal ' . $keyword]);
        Employee::factory()->create(['name' => 'literal other']);
        $this->actingAs(UserFactory::new()->create())->withoutVite()->get(route('empl.index', ['keyword' => $keyword]))
            ->assertOk()->assertInertia(fn(Assert $page) => $page->has('resources.data', 1)->where('resources.data.0.id', $matching->uuid));
    }

    /** @return array<string, array{string}> */
    public static function literalKeywords(): array
    {
        return ['percent' => ['50%'], 'underscore' => ['a_'], 'backslash' => ['a\\'], 'injection' => ["' OR 1=1 --"]];
    }

    #[DataProvider('ignoredKeywords')]
    public function test_ignores_short_empty_and_non_scalar_keywords(mixed $keyword): void
    {
        Employee::factory()->count(2)->create();
        $this->actingAs(UserFactory::new()->create())->withoutVite()->get(route('empl.index', ['keyword' => $keyword]))
            ->assertOk()->assertInertia(fn(Assert $page) => $page->where('filters.keyword', '')->has('resources.data', 2));
    }

    /** @return array<string, array{mixed}> */
    public static function ignoredKeywords(): array
    {
        return ['short' => [' A '], 'empty' => [' '], 'array' => [['abc']]];
    }

    public function test_multi_choice_filters_combine_with_keyword_and_tenure(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 24)->startOfDay());
        $job = JobTitle::factory()->create();
        $otherJob = JobTitle::factory()->create();
        $doctor = Employee::factory()->create(['job_title_id' => $job->id, 'name' => 'Needle Alpha', 'join_date' => '2020-10-24']);
        $nurse = Employee::factory()->create(['job_title_id' => $otherJob->id, 'name' => 'Needle Beta', 'join_date' => '2020-10-24', 'contract_status' => ContractStatusEnum::PPPK, 'status' => AsnStatusEnum::MUTASI]);
        Employee::factory()->create(['job_title_id' => $job->id, 'name' => 'Other', 'join_date' => '2020-10-24']);
        Employee::factory()->create(['job_title_id' => $job->id, 'name' => 'Needle old', 'join_date' => '2000-10-24']);
        Employee::factory()->create(['job_title_id' => $job->id, 'name' => 'Needle contract', 'join_date' => '2020-10-24', 'contract_status' => ContractStatusEnum::KONTRAK_BLUD]);
        Employee::factory()->create(['job_title_id' => $job->id, 'name' => 'Needle retired', 'join_date' => '2020-10-24', 'status' => AsnStatusEnum::PENSIUN]);
        Employee::factory()->create(['name' => 'Needle other job', 'join_date' => '2020-10-24']);
        $filter = "job_title:{$job->uuid},{$otherJob->uuid}|contract_status:1,2|status:1,2|tenure_min:5|tenure_max:10";
        $this->actingAs(UserFactory::new()->create())->withoutVite()->get(route('empl.index', ['filter' => $filter, 'keyword' => 'Needle']))
            ->assertOk()->assertInertia(fn(Assert $page) => $page->has('resources.data', 2)
            ->where('resources.data.0.id', $doctor->uuid)->where('resources.data.1.id', $nurse->uuid)
            ->where('filters.contract_status', [1, 2])->where('filters.status', [1, 2])
            ->where('filters.tenure_min', 5)->where('filters.tenure_max', 10));
    }

    #[DataProvider('tenureRanges')]
    public function test_tenure_ranges_use_inclusive_complete_years(string $filter, array $expected): void
    {
        $this->travelTo(now()->setDate(2026, 10, 24)->startOfDay());
        foreach (['2015-10-24', '2015-10-25', '2016-10-24', '2021-10-24', '2021-10-25', '2026-10-24'] as $date) {
            Employee::factory()->create(['name' => $date, 'join_date' => $date]);
        }
        $this->actingAs(UserFactory::new()->create())->withoutVite()->get(route('empl.index', ['filter' => $filter]))
            ->assertOk()->assertInertia(fn(Assert $page) => $page->where('resources.data', fn($rows): bool => collect($rows)->pluck('name.raw')->all() === $expected));
    }

    /** @return array<string, array{string, list<string>}> */
    public static function tenureRanges(): array
    {
        return [
            'inclusive range' => ['tenure_min:5|tenure_max:10', ['2015-10-25', '2016-10-24', '2021-10-24']],
            'minimum only' => ['tenure_min:10', ['2015-10-24', '2015-10-25', '2016-10-24']],
            'maximum only' => ['tenure_max:4', ['2021-10-25', '2026-10-24']],
            'zero year' => ['tenure_min:0|tenure_max:0', ['2026-10-24']],
            'equal bounds' => ['tenure_min:10|tenure_max:10', ['2015-10-25', '2016-10-24']],
            'large minimum' => ['tenure_min:999999999', []],
            'large maximum' => ['tenure_max:999999999', ['2015-10-24', '2015-10-25', '2016-10-24', '2021-10-24', '2021-10-25', '2026-10-24']],
        ];
    }

    #[DataProvider('leapYearCases')]
    public function test_leap_day_resource_tenure_agrees_with_filter(string $today, string $joined, int $years): void
    {
        $this->travelTo(now()->setDateFrom($today)->startOfDay());
        Employee::factory()->create(['join_date' => $joined]);
        $this->actingAs(UserFactory::new()->create())->withoutVite()->get(route('empl.index', ['filter' => "tenure_min:{$years}|tenure_max:{$years}"]))
            ->assertOk()->assertInertia(fn(Assert $page) => $page->has('resources.data', 1)->where('resources.data.0.tenure_years', $years));
    }

    /** @return array<string, array{string, string, int}> */
    public static function leapYearCases(): array
    {
        return ['before leap anniversary' => ['2025-02-28', '2020-02-29', 4], 'after leap anniversary' => ['2025-03-01', '2020-02-29', 5], 'on leap day' => ['2024-02-29', '2020-02-29', 4], 'non leap join on leap day' => ['2024-02-29', '2019-02-28', 5]];
    }

    #[DataProvider('invalidFilters')]
    public function test_invalid_filters_are_ignored_without_query_injection(mixed $filter): void
    {
        Employee::factory()->count(2)->create();
        $this->actingAs(UserFactory::new()->create())->withoutVite()->get(route('empl.index', ['filter' => $filter]))
            ->assertOk()->assertInertia(fn(Assert $page) => $page->has('resources.data', 2)
            ->where('filters.job_title', [])->where('filters.contract_status', [])->where('filters.status', [])
            ->where('filters.tenure_min', null)->where('filters.tenure_max', null));
    }

    /** @return array<string, array{mixed}> */
    public static function invalidFilters(): array
    {
        return [
            'array' => [['status:1']], 'unknown fields' => ['other:1'],
            'unknown job' => ['job_title:00000000-0000-4000-8000-000000000000'],
            'injection' => ["status:1 OR 1=1|contract_status:99|job_title:' OR 1=1 --"],
            'invalid enum numbers' => ['status:0,-1,99,1.5|contract_status:99'],
            'reversed range' => ['tenure_min:10|tenure_max:5'],
            'negative range' => ['tenure_min:-1|tenure_max:-2'],
            'non integer range' => ['tenure_min:1.5|tenure_max:abc'],
            'multiple range bounds' => ['tenure_min:1,2|tenure_max:3,4'],
            'overflow range' => ['tenure_min:99999999999999999999999'],
        ];
    }

    public function test_valid_filter_values_are_normalized_and_invalid_values_discarded(): void
    {
        $job = JobTitle::factory()->create();
        Employee::factory()->create(['job_title_id' => $job->id]);
        Employee::factory()->create(['status' => AsnStatusEnum::PENSIUN]);
        $this->actingAs(UserFactory::new()->create())->withoutVite()->get(route('empl.index', ['filter' => "job_title:{$job->uuid},garbage|status:1,1,99,1 OR 1=1|contract_status:1,99"]))
            ->assertOk()->assertInertia(fn(Assert $page) => $page->has('resources.data', 1)
            ->where('filters.status', [1])->where('filters.contract_status', [1])->where('filters.job_title', [$job->uuid]));
    }

    #[DataProvider('pageSizes')]
    public function test_page_sizes_are_bounded_to_allowlist(int $requested, int $expected): void
    {
        $this->actingAs(UserFactory::new()->create())->withoutVite()->get(route('empl.index', ['per_page' => $requested]))
            ->assertOk()->assertInertia(fn(Assert $page) => $page->where('resources.meta.per_page', $expected));
    }

    /** @return array<string, array{int, int}> */
    public static function pageSizes(): array
    {
        return ['5' => [5, 5], '10' => [10, 10], '15' => [15, 15], '20' => [20, 20], '50' => [50, 50], '100' => [100, 100], 'zero' => [0, 10], 'negative' => [-5, 10], 'unbounded' => [9999, 10]];
    }

    public function test_pagination_keeps_search_filter_and_page_size(): void
    {
        Employee::factory()->count(6)->create(['name' => 'Needle', 'join_date' => '2020-01-01']);
        Employee::factory()->create(['name' => 'Other', 'status' => AsnStatusEnum::PENSIUN]);
        $this->actingAs(UserFactory::new()->create())->withoutVite();
        $response = $this->get(route('empl.index', ['keyword' => 'Needle', 'filter' => 'status:1', 'per_page' => 5]));
        $response->assertOk()->assertInertia(fn(Assert $page) => $page->has('resources.data', 5)->where('resources.meta.total', 6)
            ->where('resources.links.next', fn(string $url): bool => str_contains(urldecode($url), 'filter=status:1') && str_contains($url, 'keyword=Needle') && str_contains($url, 'per_page=5')));
        $this->get($response->inertiaProps('resources.links.next'))->assertOk()->assertInertia(fn(Assert $page) => $page->has('resources.data', 1)->where('resources.meta.current_page', 2));
    }
}
