<?php

namespace Tests\Feature;

use App\Models\LetterNumber;
use App\Models\LetterNumberPeriod;
use Database\Factories\LetterNumberFactory;
use Database\Factories\UserFactory;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LetterNumberPeriodTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_detail_exposes_only_public_period_data_in_descending_order(): void
    {
        $number = LetterNumberFactory::new()->create(['classification_code' => '007']);
        LetterNumberPeriod::factory()->for($number)->create(['year' => 2026, 'month' => 10, 'last_page_number' => 23, 'last_sequence_number' => 44]);
        $november = LetterNumberPeriod::factory()->for($number)->create(['year' => 2026, 'month' => 11]);
        LetterNumberPeriod::factory()->create(['year' => 2027]);
        $this->actingAs(UserFactory::new()->create())->withoutVite()->get(route('ln.show', $number->uuid))
            ->assertOk()->assertInertia(fn(Assert $page) => $page->component('letter-number/show')
            ->where('page.id', 'a3063dab-44a6-4de2-9c93-8fa84742e3da')
            ->where('resource.data.code', '007')->where('resource.data.needs_initialization', true)
            ->has('resources.data', 2)->where('resources.data.0.id', $november->uuid)
            ->where('resources.data.0.last_position', null)->where('resources.data.0.next_position', '01.01')
            ->where('resources.data.1.last_position', '23.44')->where('resources.data.1.next_position', '23.45')
            ->missing('resources.data.0.letter_number_id'));
    }

    public function test_legacy_numbers_are_copied_once_to_the_explicit_period_and_preserved_on_the_master(): void
    {
        $number = LetterNumberFactory::new()->create(['page_number' => 23, 'sequence_number' => 44]);
        $this->actingAs(UserFactory::new()->create())->post(route('ln.periods.store', $number->uuid), [
            'year' => 2026, 'month' => 10, 'mode' => 'legacy', 'last_page_number' => 99, 'last_sequence_number' => 99,
        ])->assertRedirectToRoute('ln.show', $number->uuid)->assertSessionHasNoErrors();
        $period = $number->periods()->sole();
        $this->assertSame(23, $period->last_page_number);
        $this->assertSame(44, $period->last_sequence_number);
        $this->assertSame($period->id, $number->fresh()->initial_period_id);
        $this->assertSame(23, $number->fresh()->page_number);
        $this->assertFalse($number->fresh()->needsPeriodInitialization());
        $this->post(route('ln.periods.store', $number->uuid), ['year' => 2026, 'month' => 11, 'mode' => 'legacy'])->assertInvalid(['mode']);
        $this->assertDatabaseCount('letter_number_periods', 1);
    }

    public function test_legacy_initialization_cannot_be_bypassed_by_an_empty_period(): void
    {
        $number = LetterNumberFactory::new()->create();
        $this->actingAs(UserFactory::new()->create())->post(route('ln.periods.store', $number->uuid), [
            'year' => 2026, 'month' => 10, 'mode' => 'empty',
        ])->assertInvalid(['mode']);
        $this->assertDatabaseCount('letter_number_periods', 0);
    }

    public function test_new_classifications_do_not_require_global_counters(): void
    {
        $this->actingAs(UserFactory::new()->create())->post(route('ln.store'), [
            'classification_name' => 'Surat Baru', 'classification_code' => '007',
        ])->assertSessionHasNoErrors();
        $number = LetterNumber::query()->sole();
        $this->assertNull($number->page_number);
        $this->assertNull($number->sequence_number);
        $this->assertFalse($number->needsPeriodInitialization());
        $this->post(route('ln.periods.store', $number->uuid), ['year' => 2026, 'month' => 11, 'mode' => 'empty'])->assertSessionHasNoErrors();
        $this->assertSame(['page' => 1, 'sequence' => 1], $number->periods()->sole()->nextPosition());
    }

    public function test_custom_initial_position_is_scoped_to_the_bound_classification_and_period(): void
    {
        $number = LetterNumberFactory::new()->create(['page_number' => null, 'sequence_number' => null]);
        $other = LetterNumberFactory::new()->create();
        $this->actingAs(UserFactory::new()->create())->post(route('ln.periods.store', $number->uuid), [
            'year' => 2025, 'month' => 3, 'mode' => 'custom', 'last_page_number' => 12, 'last_sequence_number' => 99,
            'letter_number_id' => $other->id, 'uuid' => $other->uuid,
        ])->assertSessionHasNoErrors();
        $period = $number->periods()->sole();
        $this->assertSame(2025, $period->year);
        $this->assertSame(3, $period->month);
        $this->assertNotSame($other->uuid, $period->uuid);
        $this->assertSame(['page' => 13, 'sequence' => 1], $period->nextPosition());
        $this->assertSame(0, $other->periods()->count());
    }

    public function test_duplicate_period_does_not_overwrite_its_position(): void
    {
        $number = LetterNumberFactory::new()->create(['page_number' => null, 'sequence_number' => null]);
        $period = LetterNumberPeriod::factory()->for($number)->create(['last_page_number' => 3, 'last_sequence_number' => 9]);
        $this->actingAs(UserFactory::new()->create())->post(route('ln.periods.store', $number->uuid), [
            'year' => 2026, 'month' => 10, 'mode' => 'empty',
        ])->assertInvalid(['month']);
        $this->assertSame(3, $period->fresh()->last_page_number);
        $this->assertSame(9, $period->fresh()->last_sequence_number);
        $this->assertDatabaseCount('letter_number_periods', 1);
    }

    #[DataProvider('invalidPeriods')]
    public function test_invalid_period_input_is_rejected(array $change, string $field): void
    {
        $number = LetterNumberFactory::new()->create(['page_number' => null, 'sequence_number' => null]);
        $this->actingAs(UserFactory::new()->create())->post(route('ln.periods.store', $number->uuid), [
            ...['year' => 2026, 'month' => 10, 'mode' => 'custom', 'last_page_number' => 1, 'last_sequence_number' => 1], ...$change,
        ])->assertInvalid([$field]);
        $this->assertDatabaseCount('letter_number_periods', 0);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidPeriods(): array
    {
        return [
            'month zero' => [['month' => 0], 'month'], 'month thirteen' => [['month' => 13], 'month'],
            'year zero' => [['year' => 0], 'year'], 'year out of range' => [['year' => 10000], 'year'],
            'unknown mode' => [['mode' => 'automatic'], 'mode'],
            'missing page' => [['last_page_number' => null], 'last_page_number'],
            'page zero' => [['last_page_number' => 0], 'last_page_number'],
            'page overflow' => [['last_page_number' => 2147483648], 'last_page_number'],
            'missing sequence' => [['last_sequence_number' => null], 'last_sequence_number'],
            'sequence overflow' => [['last_sequence_number' => 100], 'last_sequence_number'],
            'sequence zero' => [['last_sequence_number' => 0], 'last_sequence_number'],
        ];
    }

    public function test_empty_period_ignores_spoofed_position_and_does_not_require_seed_numbers(): void
    {
        $number = LetterNumberFactory::new()->create(['page_number' => null, 'sequence_number' => null]);
        $this->actingAs(UserFactory::new()->create())->post(route('ln.periods.store', $number->uuid), [
            'year' => 2026, 'month' => 10, 'mode' => 'empty', 'last_page_number' => 99, 'last_sequence_number' => 99,
        ])->assertSessionHasNoErrors();
        $this->assertNull($number->periods()->sole()->last_page_number);
        $this->assertNull($number->periods()->sole()->last_sequence_number);
    }

    public function test_period_blocks_classification_deletion(): void
    {
        $period = LetterNumberPeriod::factory()->create();
        $this->actingAs(UserFactory::new()->create())->delete(route('ln.destroy', $period->letterNumber->uuid))->assertInvalid(['deletion']);
        $this->assertModelExists($period);
        $this->assertModelExists($period->letterNumber);
    }

    public function test_guests_and_non_uuid_identifiers_cannot_access_period_management(): void
    {
        $number = LetterNumberFactory::new()->create();
        $this->get(route('ln.show', $number->uuid))->assertRedirectToRoute('login.index');
        $this->post(route('ln.periods.store', $number->uuid))->assertRedirectToRoute('login.index');
        $this->actingAs(UserFactory::new()->create());
        foreach ([$number->id, 'a3063dab-44a6-4de2-9c93-8fa84742e3da'] as $identifier) {
            $this->get(route('ln.show', $identifier))->assertNotFound();
            $this->post(route('ln.periods.store', $identifier))->assertNotFound();
        }
    }

    public function test_database_rejects_partially_empty_position(): void
    {
        $this->expectException(QueryException::class);
        LetterNumberPeriod::factory()->create(['last_page_number' => 1, 'last_sequence_number' => null]);
    }

    public function test_period_pagination_preserves_context_and_rejects_unbounded_page_sizes(): void
    {
        $number = LetterNumberFactory::new()->create();
        foreach (range(1, 12) as $month) {
            LetterNumberPeriod::factory()->for($number)->create(['month' => $month]);
        }
        $this->actingAs(UserFactory::new()->create())->withoutVite()
            ->get(route('ln.show', ['letterNumber' => $number->uuid, 'per_page' => 5, 'page' => 2]))
            ->assertInertia(fn(Assert $page) => $page->where('resources.meta.current_page', 2)
                ->where('resources.meta.per_page', 5)->where('resources.data.0.month', 7)
                ->where('resources.meta.path', route('ln.show', $number->uuid)));
        $this->get(route('ln.show', ['letterNumber' => $number->uuid, 'per_page' => 10000]))
            ->assertInertia(fn(Assert $page) => $page->where('resources.meta.per_page', 10));
    }

    public function test_database_rejects_duplicate_periods_even_outside_http(): void
    {
        $period = LetterNumberPeriod::factory()->create();
        $this->expectException(QueryException::class);
        LetterNumberPeriod::factory()->create(['letter_number_id' => $period->letter_number_id, 'year' => $period->year, 'month' => $period->month]);
    }

    public function test_next_position_does_not_wrap_after_integer_capacity_or_persist_a_claim(): void
    {
        $period = LetterNumberPeriod::factory()->create(['last_page_number' => 2147483647, 'last_sequence_number' => 99]);
        $this->assertNull($period->nextPosition());
        $this->assertSame(99, $period->fresh()->last_sequence_number);
    }
}
