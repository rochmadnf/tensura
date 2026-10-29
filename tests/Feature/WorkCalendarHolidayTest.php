<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WorkCalendarHolidayTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_manual_conflict_foreign_actor_and_forged_selection_cannot_be_imported(): void
    {
        $this->prepare();
        $this->postJson(route('wc.events.store'), ['title' => 'Hari kerja pengganti', 'kind' => 'working_day', 'all_day' => true, 'start_date' => '2026-01-01', 'end_date' => '2026-01-01', 'calendar_revisions' => ['2026' => 1]])->assertCreated();
        $preview = $this->postJson(route('wc.holidays.preview', 2026), ['revision' => 2, 'include_collective_leave' => false])->assertOk()->assertJsonPath('data.summary.conflict', 1)->json('data');
        $payload = ['preview_id' => $preview['id'], 'preview_token' => $preview['token'], 'selected_ids' => [$preview['items'][0]['id']]];
        $this->postJson(route('wc.holidays.apply', 2026), $payload)->assertUnprocessable();
        $payload['selected_ids'] = [$preview['items'][1]['id']];
        $this->actingAs(UserFactory::new()->create());
        $this->postJson(route('wc.holidays.apply', 2026), $payload)->assertNotFound()->assertJsonPath('code', 'not_found');
        $this->assertDatabaseCount('calendar_events', 1);
        $this->assertDatabaseHas('calendar_events', ['kind' => 'working_day', 'origin' => 'manual']);
        Http::assertSentCount(1);
    }

    private function html(): string
    {
        $months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $html = '<div class="entry-content">';
        foreach ($months as $index => $month) {
            $html .= '<section class="kalender-indo"><div class="kal-title"><a class="kal-title-link">' . $month . ' 2026</a></div><ul class="kal-libur-list">' . ($index === 0 ? '<li><span class="kal-libur-day">1-2</span> Libur Nasional</li><li><span class="kal-libur-day">3</span> Cuti Bersama</li>' : '') . '</ul></section>';
        }

        return $html . '</div>';
    }

    private function prepare(): void
    {
        $this->actingAs(UserFactory::new()->create());
        $this->postJson(route('wc.years.store'), ['year' => 2026])->assertCreated();
        Http::preventStrayRequests();
        Http::fake(['tanggalans.com/*' => Http::response($this->html())]);
    }

    public function test_review_is_readonly_collective_is_optional_and_apply_is_idempotent(): void
    {
        $this->prepare();
        $preview = $this->postJson(route('wc.holidays.preview', 2026), ['revision' => 1, 'include_collective_leave' => false])->assertOk()->assertJsonCount(2, 'data.items')->json('data');
        $this->assertDatabaseCount('calendar_events', 0);
        $payload = ['preview_id' => $preview['id'], 'preview_token' => $preview['token'], 'selected_ids' => array_column($preview['items'], 'id')];
        $this->postJson(route('wc.holidays.apply', 2026), $payload)->assertOk()->assertJsonPath('data.created', 2)->assertJsonPath('data.calendar.status', 'draft');
        $this->postJson(route('wc.holidays.apply', 2026), $payload)->assertOk()->assertJsonPath('data.created', 2);
        $this->assertDatabaseCount('calendar_events', 2);
        $this->assertDatabaseCount('work_calendar_exceptions', 2);
        $this->postJson(route('wc.holidays.preview', 2026), ['revision' => 2, 'include_collective_leave' => true])->assertOk()->assertJsonPath('data.summary.duplicate', 2)->assertJsonPath('data.summary.new', 1);
        Http::assertSentCount(1);
    }

    public function test_changed_year_or_expired_preview_cannot_apply_and_source_error_keeps_calendar(): void
    {
        $this->prepare();
        $preview = $this->postJson(route('wc.holidays.preview', 2026), ['revision' => 1, 'include_collective_leave' => false])->assertOk()->json('data');
        $this->postJson(route('wc.years.confirm', 2026), ['revision' => 1])->assertOk();
        $this->postJson(route('wc.holidays.apply', 2026), ['preview_id' => $preview['id'], 'preview_token' => $preview['token'], 'selected_ids' => [$preview['items'][0]['id']]])->assertConflict();
        $preview = $this->postJson(route('wc.holidays.preview', 2026), ['revision' => 2, 'include_collective_leave' => false])->assertOk()->json('data');
        $this->travel(16)->minutes();
        $this->postJson(route('wc.holidays.apply', 2026), ['preview_id' => $preview['id'], 'preview_token' => $preview['token'], 'selected_ids' => [$preview['items'][0]['id']]])->assertStatus(410);
        $this->assertDatabaseCount('calendar_events', 0);
    }

    public function test_invalid_source_is_not_a_successful_empty_list(): void
    {
        $this->actingAs(UserFactory::new()->create());
        $this->postJson(route('wc.years.store'), ['year' => 2026]);
        Http::preventStrayRequests();
        Http::fake(['tanggalans.com/*' => Http::response('<html>Changed layout</html>')]);
        $this->postJson(route('wc.holidays.preview', 2026), ['revision' => 1, 'include_collective_leave' => false])->assertStatus(502)->assertJsonPath('code', 'holiday_source_changed');
        $this->assertDatabaseCount('holiday_import_previews', 0);
        $this->assertDatabaseCount('calendar_events', 0);
    }
}
