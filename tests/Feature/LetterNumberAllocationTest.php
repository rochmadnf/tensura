<?php

namespace Tests\Feature;

use App\Models\Letter;
use App\Models\LetterDraft;
use App\Models\LetterNumberAssignment;
use App\Models\LetterNumberPeriod;
use App\Models\MasterData\LetterTemplateVersion;
use App\Services\LetterNumber\AllocateLetterNumber;
use Database\Factories\LetterNumberFactory;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LetterNumberAllocationTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[DataProvider('automaticPositions')]
    public function test_auto_position_rolls_over_and_is_not_reserved_until_claimed(?int $page, ?int $sequence, int $expectedPage, int $expectedSequence): void
    {
        [$classification, $draft] = $this->draft();
        $period = LetterNumberPeriod::factory()->create(['letter_number_id' => $classification->id, 'last_page_number' => $page, 'last_sequence_number' => $sequence]);
        $numbers = app(AllocateLetterNumber::class);
        $candidate = $numbers->candidate($classification, $draft);
        $this->assertSame($expectedPage, $candidate['page']);
        $this->assertSame($expectedSequence, $candidate['sequence']);
        $this->assertSame($page, $period->fresh()->last_page_number);
        $this->assertDatabaseCount('letter_number_assignments', 0);
        DB::transaction(fn() => $numbers->claim($classification, $draft, $candidate));
        $this->assertSame($expectedPage, $period->fresh()->last_page_number);
        $this->assertSame($expectedSequence, $period->fresh()->last_sequence_number);
        $this->assertNull($classification->fresh()->page_number);
    }

    public static function automaticPositions(): array
    {
        return [[null, null, 1, 1], [23, 44, 23, 45], [1, 99, 2, 1]];
    }

    public function test_manual_claim_does_not_advance_auto_and_auto_skips_released_manual_history(): void
    {
        [$classification, $draft] = $this->draft();
        $period = LetterNumberPeriod::factory()->create(['letter_number_id' => $classification->id, 'last_page_number' => 1, 'last_sequence_number' => 9]);
        $manual = LetterNumberAssignment::factory()->create(['letter_number_period_id' => $period->id, 'page_number' => 1, 'sequence_number' => 10, 'mode' => 2, 'released_at' => now()]);
        $candidate = app(AllocateLetterNumber::class)->candidate($classification, $draft);
        $this->assertSame('445/01.11/PKM-KMJ/X/2026', $candidate['number']);
        $draft->update(['numbering_mode' => 2, 'page_number' => 1, 'sequence_number' => 10]);
        $candidate = app(AllocateLetterNumber::class)->candidate($classification, $draft->fresh());
        DB::transaction(fn() => app(AllocateLetterNumber::class)->claim($classification, $draft->fresh(), $candidate));
        $this->assertSame(9, $period->fresh()->last_sequence_number);
        $this->assertDatabaseCount('letter_number_assignments', 2);
        $this->assertNotNull($manual->fresh()->released_at);
    }

    public function test_manual_conflict_suggests_the_next_free_position_without_changing_choice(): void
    {
        [$classification, $draft] = $this->draft();
        $draft->update(['numbering_mode' => 2, 'page_number' => 1, 'sequence_number' => 99]);
        $period = LetterNumberPeriod::factory()->create(['letter_number_id' => $classification->id]);
        LetterNumberAssignment::factory()->create(['letter_number_period_id' => $period->id, 'page_number' => 1, 'sequence_number' => 99]);
        LetterNumberAssignment::factory()->create(['letter_number_period_id' => $period->id, 'page_number' => 2, 'sequence_number' => 1]);
        try {
            app(AllocateLetterNumber::class)->candidate($classification, $draft->fresh());
            $this->fail('Expected collision.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('445/02.02/PKM-KMJ/X/2026', $exception->errors()['number'][0]);
        }
        $this->assertSame(99, $draft->fresh()->sequence_number);
        $this->assertNull($period->fresh()->last_sequence_number);
    }

    public function test_new_month_starts_empty_and_capacity_or_legacy_state_is_not_overwritten(): void
    {
        [$classification, $draft] = $this->draft();
        LetterNumberPeriod::factory()->create(['letter_number_id' => $classification->id, 'month' => 9, 'last_page_number' => 23, 'last_sequence_number' => 44]);
        $this->assertSame('445/01.01/PKM-KMJ/X/2026', app(AllocateLetterNumber::class)->candidate($classification, $draft)['number']);
        $period = LetterNumberPeriod::factory()->create(['letter_number_id' => $classification->id, 'last_page_number' => 2147483647, 'last_sequence_number' => 99]);
        try {
            app(AllocateLetterNumber::class)->candidate($classification, $draft);
            $this->fail('Expected capacity error.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('kapasitas', $exception->errors()['number'][0]);
        }
        $classification->update(['page_number' => 1, 'sequence_number' => 1]);
        try {
            app(AllocateLetterNumber::class)->candidate($classification->fresh(), $draft);
            $this->fail('Expected migration guard.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('angka lama', $exception->errors()['number'][0]);
        }
        $this->assertSame(99, $period->fresh()->last_sequence_number);
    }

    public function test_database_allows_released_history_but_prevents_two_active_claims(): void
    {
        $claim = LetterNumberAssignment::factory()->create();
        LetterNumberAssignment::factory()->create(['letter_number_period_id' => $claim->letter_number_period_id, 'page_number' => $claim->page_number, 'sequence_number' => $claim->sequence_number, 'released_at' => now()]);
        $this->expectException(UniqueConstraintViolationException::class);
        DB::transaction(fn() => LetterNumberAssignment::factory()->create(['letter_number_period_id' => $claim->letter_number_period_id, 'page_number' => $claim->page_number, 'sequence_number' => $claim->sequence_number]));
    }

    public function test_database_prevents_two_active_claims_for_the_same_letter_across_periods(): void
    {
        $claim = LetterNumberAssignment::factory()->create();
        $this->expectException(UniqueConstraintViolationException::class);
        DB::transaction(fn() => LetterNumberAssignment::factory()->create(['letter_id' => $claim->letter_id]));
    }

    private function draft(): array
    {
        $classification = LetterNumberFactory::new()->create(['classification_code' => '445', 'page_number' => null, 'sequence_number' => null]);
        $version = LetterTemplateVersion::factory()->create(['letter_number_id' => $classification->id]);
        $letter = Letter::factory()->create(['letter_template_id' => $version->letter_template_id]);

        return [$classification, LetterDraft::factory()->create(['letter_id' => $letter->id, 'template_version_id' => $version->id])];
    }
}
