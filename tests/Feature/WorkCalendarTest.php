<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class WorkCalendarTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_working_sunday_zero_days_and_cross_year_moves_are_consistent(): void
    {
        $this->year();
        $this->ready();
        $this->postJson(route('wc.count'), ['start_date' => '2026-11-01', 'end_date' => '2026-11-01', 'basis' => 'working_days'])->assertUnprocessable()->assertJsonPath('code', 'zero_working_days');
        $this->postJson(route('wc.events.store'), $this->event(['kind' => 'working_day', 'start_date' => '2026-11-01', 'end_date' => '2026-11-01']))->assertCreated();
        $this->ready(3);
        $this->postJson(route('wc.count'), ['start_date' => '2026-11-01', 'end_date' => '2026-11-01', 'basis' => 'working_days'])->assertOk()->assertJsonPath('data.days', 1);
        $this->postJson(route('wc.years.store'), ['year' => 2027, 'copy_from_year' => 2026])->assertCreated();
        $event = $this->postJson(route('wc.events.store'), $this->event(['kind' => 'activity', 'start_date' => '2026-12-31', 'end_date' => '2027-06-01', 'calendar_revisions' => ['2026' => 4, '2027' => 1]]))->assertCreated()->json('data');
        $this->getJson(route('wc.data', ['date' => '2027-04-01', 'view' => 'agenda']))->assertOk()->assertJsonPath('data.events.0.calendar_revisions.2026', 5)->assertJsonPath('data.events.0.calendar_revisions.2027', 2);
        $this->putJson(route('wc.events.update', $event['id']), $this->event(['revision' => 1, 'kind' => 'activity', 'start_date' => '2027-01-02', 'end_date' => '2027-01-02', 'calendar_revisions' => ['2026' => 5, '2027' => 1]]))->assertConflict();
        $this->assertDatabaseHas('calendar_events', ['uuid' => $event['id'], 'start_date' => '2026-12-31', 'revision' => 1]);
        $this->putJson(route('wc.events.update', $event['id']), $this->event(['revision' => 1, 'kind' => 'activity', 'start_date' => '2027-01-02', 'end_date' => '2027-01-02', 'calendar_revisions' => ['2026' => 5, '2027' => 2]]))->assertOk()->assertJsonPath('data.calendar_revisions.2026', 6)->assertJsonPath('data.calendar_revisions.2027', 3);
    }

    public function test_views_and_literal_filters_preserve_calendar_days(): void
    {
        $this->year();
        CalendarEvent::factory()->create(['title' => 'Kegiatan 100%', 'start_date' => '2026-11-04', 'end_date' => '2026-11-04']);
        CalendarEvent::factory()->create(['title' => 'Kegiatan lain', 'start_date' => '2026-11-04', 'end_date' => '2026-11-04']);
        foreach ([['month', 42, '2026-10-26', '2026-12-06'], ['week', 7, '2026-11-02', '2026-11-08'], ['day', 1, '2026-11-04', '2026-11-04'], ['agenda', 30, '2026-11-01', '2026-11-30']] as [$view, $count, $start, $end]) {
            $this->getJson(route('wc.data', ['date' => '2026-11-04', 'view' => $view, 'search' => '%']))->assertOk()->assertJsonCount($count, 'data.days')->assertJsonCount(1, 'data.events')->assertJsonPath('data.period.start_date', $start)->assertJsonPath('data.period.end_date', $end);
        }
    }

    private function year(): array
    {
        $this->actingAs(UserFactory::new()->create());

        return $this->postJson(route('wc.years.store'), ['year' => 2026])->assertCreated()->json('data');
    }

    private function ready(int $revision = 1): array
    {
        return $this->postJson(route('wc.years.confirm', 2026), ['revision' => $revision])->assertOk()->json('data');
    }

    private function event(array $extra = []): array
    {
        return [...['title' => 'Libur instansi', 'kind' => 'holiday', 'start_date' => '2026-11-02', 'end_date' => '2026-11-02', 'all_day' => true, 'start_time' => null, 'end_time' => null, 'location' => null, 'description' => null, 'calendar_revisions' => ['2026' => 2]], ...$extra];
    }

    public function test_year_confirmation_weekdays_and_stale_write(): void
    {
        $year = $this->year();
        $this->assertSame('draft', $year['status']);
        $this->assertSame([1, 2, 3, 4, 5, 6], $year['working_weekdays']);
        $this->postJson(route('wc.count'), ['start_date' => '2026-11-01', 'end_date' => '2026-11-07', 'basis' => 'working_days'])->assertUnprocessable()->assertJsonPath('code', 'calendar_not_ready');
        $this->ready();
        $this->postJson(route('wc.count'), ['start_date' => '2026-11-01', 'end_date' => '2026-11-07', 'basis' => 'working_days'])->assertOk()->assertJsonPath('data.days', 6);
        $this->patchJson(route('wc.years.update', 2026), ['revision' => 1, 'working_weekdays' => [1, 2, 3, 4, 5]])->assertConflict();
        $this->patchJson(route('wc.years.update', 2026), ['revision' => 2, 'working_weekdays' => [1, 2, 3, 4, 5]])->assertOk()->assertJsonPath('data.status', 'draft');
    }

    public function test_exceptions_conflict_and_activities_do_not_invalidate_readiness(): void
    {
        $this->year();
        $this->ready();
        $activity = $this->postJson(route('wc.events.store'), $this->event(['kind' => 'activity', 'all_day' => false, 'start_time' => '08:00', 'end_time' => '10:00']))->assertCreated()->json('data');
        $this->getJson(route('wc.data', ['year' => 2026, 'date' => '2026-11-02']))->assertOk()->assertJsonPath('data.calendar.status', 'ready')->assertJsonCount(42, 'data.days');
        $holiday = $this->postJson(route('wc.events.store'), $this->event(['calendar_revisions' => ['2026' => 3]]))->assertCreated()->json('data');
        $this->postJson(route('wc.events.store'), $this->event(['kind' => 'working_day', 'calendar_revisions' => ['2026' => 4]]))->assertUnprocessable()->assertJsonPath('code', 'calendar_exception_conflict');
        $this->ready(4);
        $this->postJson(route('wc.count'), ['start_date' => '2026-11-01', 'end_date' => '2026-11-07', 'basis' => 'working_days'])->assertOk()->assertJsonPath('data.days', 5);
        $this->deleteJson(route('wc.events.destroy', $holiday['id']), ['revision' => 1, 'calendar_revisions' => ['2026' => 5]])->assertOk();
        $this->assertDatabaseCount('work_calendar_exceptions', 0);
        $this->assertDatabaseCount('calendar_events', 1);
    }

    public function test_missing_year_and_invalid_times_do_not_partially_write(): void
    {
        $this->year();
        $this->ready();
        $this->postJson(route('wc.events.store'), $this->event(['end_date' => '2027-01-01']))->assertUnprocessable()->assertJsonPath('code', 'calendar_year_missing');
        $this->assertDatabaseCount('calendar_events', 0);
        $this->postJson(route('wc.events.store'), $this->event(['kind' => 'activity', 'all_day' => false, 'start_time' => '10:00', 'end_time' => '08:00']))->assertUnprocessable();
        $this->postJson(route('wc.count'), ['start_date' => '2024-02-28', 'end_date' => '2024-03-01', 'basis' => 'calendar_days'])->assertOk()->assertJsonPath('data.days', 3);
        auth()->logout();
        $this->getJson(route('wc.data'))->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
    }
}
