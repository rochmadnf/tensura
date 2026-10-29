<?php

namespace Tests\Feature;

use App\Models\MasterData\LetterTemplateVariable;
use App\Models\MasterData\LetterTemplateVersion;
use App\Services\Letter\LetterCalendarState;
use App\Services\Letter\LetterDraftSnapshot;
use App\Services\LetterTemplate\SaveLetterTemplateConfiguration;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LetterDateRangeTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function prepare(): array
    {
        $user = UserFactory::new()->create();
        $this->actingAs($user);
        $version = LetterTemplateVersion::factory()->create(['validation_result' => ['valid' => true, 'placeholders' => ['cuti:durasi']]]);
        app(SaveLetterTemplateConfiguration::class)->save($version->template, $version, ['variables' => [['name' => 'cuti', 'label' => 'Lama cuti', 'type' => 8, 'is_required' => true, 'is_multiple' => false, 'option_source' => null, 'columns' => [], 'date_range' => ['basis' => 'working_days', 'date_format' => 'd-m-Y']]], 'signer_slots' => []], $user);
        $version->refresh()->update(['validation_result' => ['valid' => true, 'placeholders' => ['cuti:durasi']]]);
        $this->postJson(route('wc.years.store'), ['year' => 2026])->assertCreated();
        $this->postJson(route('wc.years.confirm', 2026), ['revision' => 1])->assertOk();

        return [$version->fresh(), $version->fresh()->variables->sole()];
    }

    public function test_range_snapshot_is_frozen_until_explicit_refresh_and_stale_is_rejected(): void
    {
        [$version,$variable] = $this->prepare();
        $input = ['values' => [$variable->uuid => ['start_date' => '2026-11-01', 'end_date' => '2026-11-07']], 'signers' => []];
        $date = CarbonImmutable::parse('2026-11-01');
        $original = app(LetterDraftSnapshot::class)->build($version, $input, $date);
        $this->assertSame('6 (Enam) hari', $original['values']['cuti:durasi']);
        $this->postJson(route('wc.events.store'), ['title' => 'Libur', 'kind' => 'holiday', 'all_day' => true, 'start_date' => '2026-11-02', 'end_date' => '2026-11-02', 'calendar_revisions' => ['2026' => 2]])->assertCreated();
        $preserved = app(LetterDraftSnapshot::class)->build($version, $input, $date, $original);
        $this->assertSame($original['values'], $preserved['values']);
        $this->assertSame('stale', app(LetterCalendarState::class)->state($original)['state']);
        try {
            app(LetterCalendarState::class)->assertCurrent($original);
            $this->fail('Expected stale calendar rejection');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('calendar', $e->errors());
        }
        $this->postJson(route('wc.years.confirm', 2026), ['revision' => 3])->assertOk();
        $refreshed = app(LetterDraftSnapshot::class)->build($version, $input, $date);
        $this->assertSame('5 (Lima) hari', $refreshed['values']['cuti:durasi']);
        $this->assertSame('current', app(LetterCalendarState::class)->state($refreshed)['state']);
        $this->assertSame('6 (Enam) hari', $original['values']['cuti:durasi']);
    }

    public function test_unprepared_calendar_keeps_raw_input_in_an_incomplete_draft_snapshot(): void
    {
        [$version,$variable] = $this->prepare();
        $input = ['values' => [$variable->uuid => ['start_date' => '2027-01-02', 'end_date' => '2027-01-04']], 'signers' => []];
        $snapshot = app(LetterDraftSnapshot::class)->build($version, $input, CarbonImmutable::parse('2026-11-01'));
        $this->assertFalse($snapshot['complete']);
        $this->assertSame($input, $snapshot['inputs']);
        $this->assertSame('missing', app(LetterCalendarState::class)->state($snapshot)['state']);
    }

    public function test_an_unprepared_range_does_not_drop_frozen_results_of_another_range(): void
    {
        [$version, $first] = $this->prepare();
        $input = ['values' => [$first->uuid => ['start_date' => '2026-11-01', 'end_date' => '2026-11-07']], 'signers' => []];
        $date = CarbonImmutable::parse('2026-11-01');
        $original = app(LetterDraftSnapshot::class)->build($version, $input, $date);
        $second = LetterTemplateVariable::factory()->create(['version_id' => $version->id, 'name' => 'lain', 'type' => 8, 'is_required' => true, 'date_range' => $first->date_range]);
        $version->update(['validation_result' => ['valid' => true, 'placeholders' => ['cuti:durasi', 'lain:durasi']]]);
        $input['values'][$second->uuid] = ['start_date' => '2027-01-01', 'end_date' => '2027-01-07'];
        $partial = app(LetterDraftSnapshot::class)->build($version->fresh(), $input, $date, $original);
        $this->assertFalse($partial['complete']);
        $this->assertSame($original['question_results'][$first->uuid], $partial['question_results'][$first->uuid]);
        $this->assertSame('6 (Enam) hari', $partial['values']['cuti:durasi']);
    }

    public function test_one_stale_range_is_not_hidden_by_another_current_range_in_the_same_year(): void
    {
        [$version, $variable] = $this->prepare();
        $input = ['values' => [$variable->uuid => ['start_date' => '2026-11-01', 'end_date' => '2026-11-07']], 'signers' => []];
        $snapshot = app(LetterDraftSnapshot::class)->build($version, $input, CarbonImmutable::parse('2026-11-01'));
        $this->patchJson(route('wc.years.update', 2026), ['revision' => 2, 'working_weekdays' => [1, 2, 3, 4, 5]])->assertOk();
        $this->postJson(route('wc.years.confirm', 2026), ['revision' => 3])->assertOk();
        $current = app(LetterDraftSnapshot::class)->build($version, $input, CarbonImmutable::parse('2026-11-01'));
        $snapshot['question_results']['other'] = $current['question_results'][$variable->uuid];
        $this->assertSame('stale', app(LetterCalendarState::class)->state($snapshot)['state']);
    }

    public function test_resolver_range_is_readonly_and_rejects_reverse_range(): void
    {
        [$version,$variable] = $this->prepare();
        $this->postJson(route('lt.values.resolve'), ['template_version' => $version->uuid, 'values' => [$variable->uuid => ['start_date' => '2026-11-01', 'end_date' => '2026-11-07']]])->assertOk()->assertJsonPath('data.fields.0.tokens.cuti:jumlah_hari', '6');
        $this->postJson(route('lt.values.resolve'), ['template_version' => $version->uuid, 'values' => [$variable->uuid => ['start_date' => '2026-11-07', 'end_date' => '2026-11-01']]])->assertUnprocessable();
        $this->assertDatabaseCount('letter_drafts', 0);
    }
}
