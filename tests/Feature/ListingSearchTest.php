<?php

namespace Tests\Feature;

use Database\Factories\EmployeeFactory;
use Database\Factories\JobTitleFactory;
use Database\Factories\LetterNumberFactory;
use Database\Factories\ReferenceGroupFactory;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ListingSearchTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @param class-string<Factory> $factory */
    #[DataProvider('literalSearches')]
    public function test_search_ignores_case_and_treats_special_characters_literally(string $route, string $factory, string $field, string $keyword, string $decoy): void
    {
        $match = $factory::new()->create([$field => "Prefix {$keyword} Suffix"]);
        $factory::new()->create([$field => "Prefix {$decoy} Suffix"]);

        $response = $this->actingAs(UserFactory::new()->create())->withoutVite()
            ->get(route($route, ['keyword' => '  ' . mb_strtolower($keyword) . '  ']));

        $response->assertOk()->assertInertia(fn(Assert $page) => $page
            ->where('filters.keyword', mb_strtolower($keyword))
            ->has('resources.data', 1)
            ->where('resources.data.0.id', $match->uuid));
    }

    /** @param class-string<Factory> $factory */
    #[DataProvider('malformedKeywords')]
    public function test_array_keywords_are_ignored_without_server_errors(string $route, string $factory, string $field, array $keyword): void
    {
        $factory::new()->create([$field => 'Data pertama']);
        $factory::new()->create([$field => 'Data kedua']);

        $response = $this->actingAs(UserFactory::new()->create())->withoutVite()
            ->get(route($route, ['keyword' => $keyword]));

        $response->assertOk()->assertInertia(fn(Assert $page) => $page
            ->where('filters.keyword', '')->has('resources.data', 2));
    }

    /** @return array<string, array{string, class-string, string}> */
    private static function modules(): array
    {
        return [
            'job title' => ['jt.index', JobTitleFactory::class, 'name'],
            'employee' => ['empl.index', EmployeeFactory::class, 'name'],
            'letter number' => ['ln.index', LetterNumberFactory::class, 'classification_name'],
            'reference' => ['ref.index', ReferenceGroupFactory::class, 'name'],
        ];
    }

    /** @return array<string, array{string, class-string, string, string, string}> */
    public static function literalSearches(): array
    {
        $cases = [
            'case' => ['MiXeD', 'Different'],
            'percent' => ['100%Test', '100XYZTest'],
            'underscore' => ['A_Test', 'AXTest'],
            'escape marker' => ['A!Test', 'ATest'],
            'backslash' => ['A\\Test', 'ATest'],
            'quote' => ["O'Brien", 'OBrien'],
        ];
        $result = [];
        foreach (self::modules() as $module => $arguments) {
            foreach ($cases as $name => $values) {
                $result["{$module}: {$name}"] = [...$arguments, ...$values];
            }
        }

        return $result;
    }

    /** @return array<string, array{string, class-string, string, array}> */
    public static function malformedKeywords(): array
    {
        $result = [];
        foreach (self::modules() as $module => $arguments) {
            foreach (['array' => ['x'], 'nested array' => ['value' => ['x']]] as $name => $keyword) {
                $result["{$module}: {$name}"] = [...$arguments, $keyword];
            }
        }

        return $result;
    }
}
