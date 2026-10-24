<?php

namespace Tests\Feature;

use Database\Factories\LetterNumberFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LetterNumberIndexTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('ln.index'))->assertRedirectToRoute('login.index');
    }

    public function test_search_matches_classification_codes(): void
    {
        $match = LetterNumberFactory::new()->create(['classification_code' => '001', 'classification_name' => 'Administrasi']);
        LetterNumberFactory::new()->create(['classification_code' => '002', 'classification_name' => 'Keuangan']);

        $this->actingAs(UserFactory::new()->create())->withoutVite()
            ->get(route('ln.index', ['keyword' => '01']))
            ->assertOk()->assertInertia(fn(Assert $page) => $page
            ->has('resources.data', 1)->where('resources.data.0.id', $match->uuid));
    }

    public function test_pages_follow_unique_classification_codes_without_repeated_or_missing_rows(): void
    {
        $records = collect(['101', '007', '445', '002', '123', '003', '006', '001', '004', '005', '999'])
            ->map(fn(string $code) => LetterNumberFactory::new()->create([
                'classification_code' => $code,
                'classification_name' => 'Surat Pengujian',
            ]))->keyBy('classification_code');
        $expected = [
            ['001', '002', '003', '004', '005'],
            ['006', '007', '101', '123', '445'],
            ['999'],
        ];
        $this->actingAs(UserFactory::new()->create())->withoutVite();

        foreach ($expected as $pageIndex => $codes) {
            $response = $this->get(route('ln.index', ['keyword' => 'surat', 'per_page' => 5, 'page' => $pageIndex + 1]));

            $response->assertOk()->assertInertia(fn(Assert $page) => $page
                ->where('filters.keyword', 'surat')
                ->where('resources.meta.current_page', $pageIndex + 1)
                ->where('resources.meta.per_page', 5)
                ->where('resources.meta.total', 11)
                ->where('resources.data', fn($rows): bool => collect($rows)->pluck('id')->all()
                    === array_map(fn(string $code): string => $records[$code]->uuid, $codes)));
        }
    }
}
