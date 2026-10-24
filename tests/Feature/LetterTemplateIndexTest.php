<?php

namespace Tests\Feature;

use App\Enums\LetterTemplateVersionStatusEnum;
use App\Models\LetterNumber;
use App\Models\MasterData\LetterTemplate;
use App\Models\MasterData\LetterTemplateVersion;
use Database\Factories\LetterNumberFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LetterTemplateIndexTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guests_cannot_view_the_list(): void
    {
        $this->get(route('lt.index'))->assertRedirectToRoute('login.index');
    }

    public function test_empty_list_has_expected_page_filters_and_pagination_contract(): void
    {
        $this->actingAs(UserFactory::new()->create())->withoutVite()->get(route('lt.index'))
            ->assertOk()->assertInertia(fn(Assert $page) => $page->component('letter-templates/index')
            ->where('page.id', 'a3063dc3-7414-4385-bf3f-0cf0742e3980')
            ->where('filters', ['keyword' => '', 'status' => null, 'classification' => null])
            ->has('resources.data', 0)->where('resources.meta.per_page', 10)->has('resources.links'));
    }

    public function test_display_classification_prefers_active_then_draft_then_latest_version(): void
    {
        $activeNumber = LetterNumberFactory::new()->create(['classification_code' => '007', 'classification_name' => 'Aktif Terpilih']);
        $draftNumber = LetterNumberFactory::new()->create(['classification_code' => '008', 'classification_name' => 'Draft Terpilih']);
        $latestNumber = LetterNumberFactory::new()->create(['classification_code' => '009', 'classification_name' => 'Arsip Terbaru']);
        $template = LetterTemplate::factory()->create(['name' => 'Surat Tugas']);
        $active = LetterTemplateVersion::factory()->for($template, 'template')->create(['version_number' => 1, 'letter_number_id' => $activeNumber->id, 'status' => LetterTemplateVersionStatusEnum::PUBLISHED]);
        $draft = LetterTemplateVersion::factory()->for($template, 'template')->create(['version_number' => 2, 'letter_number_id' => $draftNumber->id]);
        LetterTemplateVersion::factory()->for($template, 'template')->create(['version_number' => 3, 'letter_number_id' => $latestNumber->id, 'status' => LetterTemplateVersionStatusEnum::ARCHIVED]);
        $template->update(['active_version_id' => $active->id]);
        $this->actingAs(UserFactory::new()->create())->withoutVite();

        $this->get(route('lt.index'))->assertOk()->assertInertia(fn(Assert $page) => $page
            ->where('resources.data.0.classification.id', $activeNumber->uuid)
            ->where('resources.data.0.active_version', 1)->where('resources.data.0.has_draft', true));
        $this->get(route('lt.index', ['keyword' => 'Draft Terpilih']))->assertInertia(fn(Assert $page) => $page->has('resources.data', 0));
        $this->get(route('lt.index', ['filter' => 'classification:' . $draftNumber->uuid]))->assertInertia(fn(Assert $page) => $page->has('resources.data', 0));

        $template->update(['active_version_id' => null]);
        $this->get(route('lt.index', ['keyword' => 'Draft Terpilih', 'filter' => 'classification:' . $draftNumber->uuid]))
            ->assertOk()->assertInertia(fn(Assert $page) => $page->where('resources.data.0.classification.id', $draftNumber->uuid)->where('resources.data.0.active_version', null));
        $this->get(route('lt.index', ['keyword' => 'Aktif Terpilih']))->assertInertia(fn(Assert $page) => $page->has('resources.data', 0));

        $draft->update(['status' => LetterTemplateVersionStatusEnum::ARCHIVED]);
        $this->get(route('lt.index', ['keyword' => 'Arsip Terbaru', 'filter' => 'classification:' . $latestNumber->uuid]))
            ->assertOk()->assertInertia(fn(Assert $page) => $page->where('resources.data.0.classification.id', $latestNumber->uuid)->where('resources.data.0.has_draft', false));
    }

    #[DataProvider('literalKeywords')]
    public function test_search_matches_literal_name_and_classification_text(string $text, string $keyword): void
    {
        $number = LetterNumberFactory::new()->create(['classification_name' => $text]);
        $matched = $this->template('Surat Tugas', $number);
        $this->template('Surat Lain', LetterNumberFactory::new()->create(['classification_name' => 'Klasifikasi Umum']));

        $this->actingAs(UserFactory::new()->create())->withoutVite()->get(route('lt.index', ['keyword' => ' ' . $keyword . ' ']))
            ->assertOk()->assertInertia(fn(Assert $page) => $page->where('filters.keyword', $keyword)
            ->has('resources.data', 1)->where('resources.data.0.id', $matched->uuid));
    }

    public static function literalKeywords(): array
    {
        return [
            'case insensitive' => ['Perjalanan Dinas', 'pERJALANAN'],
            'percent' => ['Potongan 50% pegawai', '50%'],
            'underscore' => ['Data_nama', 'Data_'],
            'exclamation' => ['Data!nama', 'Data!'],
            'backslash' => ['Data\\nama', 'Data\\'],
            'quote' => ["Nama O'Reilly", "O'Reilly"],
        ];
    }

    public function test_name_and_code_search_combine_with_single_status_and_classification_filters(): void
    {
        $number = LetterNumberFactory::new()->create(['classification_code' => '007']);
        $matched = $this->template('Surat Tugas', $number);
        $inactive = $this->template('Surat Tugas Nonaktif', $number);
        $inactive->update(['is_active' => false]);
        $this->template('Surat Tugas Berbeda', LetterNumberFactory::new()->create(['classification_code' => '008']));
        $this->actingAs(UserFactory::new()->create())->withoutVite();
        foreach (['007', 'surat tugas'] as $keyword) {
            $this->get(route('lt.index', ['keyword' => $keyword, 'filter' => 'status:active|classification:' . $number->uuid]))
                ->assertOk()->assertInertia(fn(Assert $page) => $page->has('resources.data', 1)->where('resources.data.0.id', $matched->uuid)
                ->where('filters.status', 'active')->where('filters.classification', $number->uuid));
        }
        $this->get(route('lt.index', ['filter' => 'status:inactive']))->assertInertia(fn(Assert $page) => $page
            ->has('resources.data', 1)->where('resources.data.0.id', $inactive->uuid));
    }

    #[DataProvider('malformedFilters')]
    public function test_invalid_or_multiple_filter_values_are_ignored(mixed $filter): void
    {
        $this->template('Surat Tugas', LetterNumberFactory::new()->create());
        $this->actingAs(UserFactory::new()->create())->withoutVite()->get(route('lt.index', ['filter' => $filter]))
            ->assertOk()->assertInertia(fn(Assert $page) => $page->has('resources.data', 1)->where('filters.status', null)->where('filters.classification', null));
    }

    public static function malformedFilters(): array
    {
        return [
            [['status' => 'inactive']], ['status:active,inactive'], ['status:unknown'],
            ['classification:1'], ['classification:00000000-0000-4000-8000-000000000000'],
            ['classification:one,two'], ['name:injected|unknown:foo'],
        ];
    }

    #[DataProvider('ignoredKeywords')]
    public function test_short_or_malformed_keywords_are_normalized_to_empty(mixed $keyword): void
    {
        $this->template('Surat Tugas', LetterNumberFactory::new()->create());
        $this->actingAs(UserFactory::new()->create())->withoutVite()->get(route('lt.index', ['keyword' => $keyword]))
            ->assertOk()->assertInertia(fn(Assert $page) => $page->where('filters.keyword', '')->has('resources.data', 1));
    }

    public static function ignoredKeywords(): array
    {
        return [[' a '], [' '], [['invalid']]];
    }

    public function test_pagination_is_stable_in_name_order_and_keeps_query_parameters(): void
    {
        $number = LetterNumberFactory::new()->create();
        foreach (['Foxtrot', 'Alpha', 'Golf', 'Echo', 'Bravo', 'Delta', 'Charlie'] as $name) {
            $this->template('Surat ' . $name, $number);
        }
        $this->actingAs(UserFactory::new()->create())->withoutVite();
        $query = ['keyword' => 'Surat', 'filter' => 'status:active|classification:' . $number->uuid, 'per_page' => 5];
        $first = $this->get(route('lt.index', $query))->assertOk();
        $second = $this->get(route('lt.index', [...$query, 'page' => 2]))->assertOk();
        $this->assertSame(['Surat Alpha', 'Surat Bravo', 'Surat Charlie', 'Surat Delta', 'Surat Echo'], array_column($first->inertiaProps('resources.data'), 'name'));
        $this->assertSame(['Surat Foxtrot', 'Surat Golf'], array_column($second->inertiaProps('resources.data'), 'name'));
        $this->assertSame(7, $first->inertiaProps('resources.meta.total'));
        parse_str(parse_url($first->inertiaProps('resources.links.next'), PHP_URL_QUERY), $next);
        $this->assertSame([...array_map('strval', $query), 'page' => '2'], $next);
        $this->get(route('lt.index', ['per_page' => 999]))->assertInertia(fn(Assert $page) => $page->where('resources.meta.per_page', 10));
    }

    private function template(string $name, LetterNumber $classification): LetterTemplate
    {
        return LetterTemplateVersion::factory()->create(['letter_template_id' => LetterTemplate::factory()->state(['name' => $name]), 'letter_number_id' => $classification->id])->template;
    }
}
