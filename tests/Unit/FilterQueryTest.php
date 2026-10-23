<?php

namespace Tests\Unit;

use App\Http\FilterQuery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FilterQueryTest extends TestCase
{
    /** @param array<string, list<string>> $expected */
    #[DataProvider('filterQueries')]
    public function test_parses_filter_fields_independently(mixed $query, array $expected): void
    {
        $this->assertSame($expected, FilterQuery::parse($query));
    }

    /** @return array<string, array{mixed, array<string, list<string>>}> */
    public static function filterQueries(): array
    {
        return [
            'single and multiple values' => ['group:3,1|year:2034', ['group' => ['3', '1'], 'year' => ['2034']]],
            'field order' => ['year:2034|group:1,2', ['year' => ['2034'], 'group' => ['1', '2']]],
            'trim and deduplicate' => [' group : 3, 1,3,, |year: 2034 ', ['group' => ['3', '1'], 'year' => ['2034']]],
            'repeated fields' => ['group:1|year:2034|group:2,1', ['group' => ['1', '2'], 'year' => ['2034']]],
            'empty' => ['', []],
            'empty values' => ['group:,,|year:', []],
            'malformed fields' => ['invalid|:3|bad-name:1|group:1:2|year:2034', ['year' => ['2034']]],
            'zero value' => ['active:0', ['active' => ['0']]],
            'non string' => [['group:1'], []],
            'missing' => [null, []],
        ];
    }
}
