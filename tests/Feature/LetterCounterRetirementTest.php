<?php

namespace Tests\Feature;

use App\Models\LetterNumber;
use App\Models\LetterNumberPeriod;
use App\Services\LetterNumber\RetireLetterNumberCounters;
use Database\Factories\LetterNumberFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use RuntimeException;
use Tests\TestCase;

class LetterCounterRetirementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_retirement_refuses_uninitialized_numbers_without_changing_schema_or_data(): void
    {
        $number = LetterNumberFactory::new()->create(['classification_code' => '445', 'page_number' => 23, 'sequence_number' => 44]);
        $this->artisan('letters:retire-counters', ['--check' => true])->expectsOutputToContain('445')->assertFailed();
        try {
            app(RetireLetterNumberCounters::class)->retire();
            $this->fail('Expected initialization guard.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('445', $exception->getMessage());
        }
        $this->assertTrue(Schema::hasColumn('letter_numbers', 'page_number'));
        $this->assertSame(23, $number->fresh()->page_number);
        $this->assertSame(44, $number->fresh()->sequence_number);
    }

    public function test_preflight_requires_the_correct_period_and_a_position_that_preserves_the_old_counter(): void
    {
        $number = LetterNumberFactory::new()->create(['page_number' => 23, 'sequence_number' => 44]);
        $wrong = LetterNumberPeriod::factory()->create(['last_page_number' => 23, 'last_sequence_number' => 44]);
        $number->forceFill(['initial_period_id' => $wrong->id])->save();
        $retirement = app(RetireLetterNumberCounters::class);
        $this->assertNotEmpty($retirement->pending());
        $period = LetterNumberPeriod::factory()->for($number)->create(['last_page_number' => 23, 'last_sequence_number' => 43]);
        $number->forceFill(['initial_period_id' => $period->id])->save();
        $this->assertNotEmpty($retirement->pending());
        $period->update(['last_sequence_number' => 44]);
        $wrong->letterNumber->forceFill(['page_number' => null, 'sequence_number' => null])->save();
        $this->assertSame([], $retirement->pending());
    }

    public function test_initialized_counter_is_retired_and_only_period_counters_are_exposed_and_updated(): void
    {
        $number = LetterNumberFactory::new()->create(['page_number' => 23, 'sequence_number' => 44]);
        $period = LetterNumberPeriod::factory()->for($number)->create(['last_page_number' => 23, 'last_sequence_number' => 45]);
        $number->forceFill(['initial_period_id' => $period->id])->save();
        $this->artisan('letters:retire-counters', ['--check' => true])->assertSuccessful();
        $this->assertTrue(Schema::hasColumn('letter_numbers', 'page_number'));
        app(RetireLetterNumberCounters::class)->retire();
        $this->assertFalse(Schema::hasColumn('letter_numbers', 'page_number'));
        $this->assertFalse(Schema::hasColumn('letter_numbers', 'sequence_number'));
        $this->assertSame($period->id, $number->fresh()->initial_period_id);
        $this->assertSame(45, $period->fresh()->last_sequence_number);
        $this->actingAs(UserFactory::new()->create())->withoutVite()->get(route('ln.show', $number->uuid))->assertInertia(fn(AssertableInertia $page) => $page->missing('resource.data.page_number')->missing('resource.data.sequence_number')->where('resource.data.needs_initialization', false));
        $this->post(route('ln.store'), ['classification_name' => 'Baru', 'classification_code' => '999', 'page_number' => 99, 'sequence_number' => 99])->assertSessionHasNoErrors();
        $new = LetterNumber::query()->where('classification_code', '999')->sole();
        $this->post(route('ln.periods.store', $new->uuid), ['mode' => 'empty', 'year' => 2026, 'month' => 10])->assertSessionHasNoErrors();
        $this->assertSame(['page' => 1, 'sequence' => 1], $new->periods()->sole()->nextPosition());
        $this->assertSame([], app(RetireLetterNumberCounters::class)->pending());
    }
}
