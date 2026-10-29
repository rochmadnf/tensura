<?php

namespace App\Services\WorkCalendar;

use App\Models\CalendarEvent;
use App\Models\MasterData\User;
use App\Models\WorkCalendarYear;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportIndonesianHolidays
{
    public function __construct(private FetchIndonesianHolidays $source, private ManageWorkCalendar $calendar) {}

    public function preview(int $year, array $input, User $actor): array
    {
        $items = collect($this->source->fetch($year))->filter(fn($h) => $input['include_collective_leave'] || !$h['is_collective_leave'])->groupBy('date');

        return DB::transaction(function () use ($year, $input, $actor, $items): array {
            $record = WorkCalendarYear::where('year', $year)->lockForUpdate()->firstOrFail();
            $this->calendar->revision($record, $input['revision']);
            $exceptions = DB::table('work_calendar_exceptions')->where('work_calendar_year_id', $record->id)->get()->keyBy('date');
            $events = CalendarEvent::whereIn('id', $exceptions->pluck('calendar_event_id'))->get()->keyBy('id');
            $rows = [];
            $summary = ['new' => 0, 'duplicate' => 0, 'conflict' => 0];
            foreach ($items as $date => $entries) {
                $name = $entries->pluck('name')->unique()->implode('; ');
                if (mb_strlen($name) > 150) {
                    throw new CalendarFailure('holiday_source_changed', 'Gabungan nama libur melebihi batas.', 502);
                }
                $kind = $entries->contains(fn($e) => !$e['is_collective_leave']) ? 'national_holiday' : 'collective_leave';
                $decision = 'new';
                $reason = null;
                $ex = $exceptions->get($date);
                if ($ex !== null) {
                    $event = $events[$ex->calendar_event_id];
                    if ($ex->kind === 'working_day') {
                        $decision = 'conflict';
                        $reason = 'manual_working_day';
                    } elseif ($event->origin !== 'manual' && $event->title !== $name) {
                        $decision = 'conflict';
                        $reason = 'source_changed';
                    } else {
                        $decision = 'duplicate';
                        $reason = 'existing_holiday';
                    }
                }
                $summary[$decision]++;
                $rows[] = ['id' => (string) Str::uuid(), 'date' => $date, 'name' => $name, 'kind' => $kind, 'decision' => $decision, 'reason' => $reason, 'selectable' => $decision === 'new'];
            }
            $uuid = (string) Str::uuid();
            $token = Str::random(64);
            $expires = now()->addMinutes(15);
            $snapshot = ['id' => $uuid, 'year' => $year, 'calendar_revision' => $record->revision, 'include_collective_leave' => $input['include_collective_leave'], 'source_url' => $this->source->url($year), 'fetched_at' => $this->source->fetchedAt, 'from_cache' => $this->source->fromCache, 'expires_at' => $expires->toIso8601String(), 'items' => $rows, 'summary' => $summary];
            DB::table('holiday_import_previews')->insert(['uuid' => $uuid, 'token_hash' => hash('sha256', $token), 'actor_uuid' => $actor->uuid, 'work_calendar_year_id' => $record->id, 'calendar_revision' => $record->revision, 'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'expires_at' => $expires, 'created_at' => now(), 'updated_at' => now()]);

            return [...$snapshot, 'token' => $token];
        });
    }

    public function apply(int $year, array $input, User $actor): array
    {
        return DB::transaction(function () use ($year, $input, $actor): array {
            $record = WorkCalendarYear::where('year', $year)->lockForUpdate()->firstOrFail();
            $preview = DB::table('holiday_import_previews')->where('uuid', $input['preview_id'])->where('actor_uuid', $actor->uuid)->where('work_calendar_year_id', $record->id)->lockForUpdate()->first();
            abort_if($preview === null || !hash_equals($preview->token_hash, hash('sha256', $input['preview_token'])), 404);
            if ($preview->result !== null) {
                return json_decode($preview->result, true, 512, JSON_THROW_ON_ERROR);
            }
            if (CarbonImmutable::parse($preview->expires_at)->isPast()) {
                throw new CalendarFailure('holiday_preview_expired', 'Tinjauan libur kedaluwarsa. Ambil ulang.', 410);
            }
            if ($record->revision !== $preview->calendar_revision) {
                throw new CalendarFailure('holiday_preview_conflict', 'Kalender berubah setelah tinjauan. Ambil ulang.', 409);
            }
            $snapshot = json_decode($preview->snapshot, true, 512, JSON_THROW_ON_ERROR);
            $rows = collect($snapshot['items'])->whereIn('id', $input['selected_ids']);
            if ($rows->count() !== count($input['selected_ids']) || $rows->contains(fn($h) => !$h['selectable'])) {
                throw new CalendarFailure('validation_failed', 'Pilihan libur tidak sesuai tinjauan.', 422, ['selected_ids' => ['Pilih hanya libur baru dari tinjauan ini.']]);
            }
            foreach ($rows as $row) {
                $event = CalendarEvent::create(['title' => $row['name'], 'kind' => 'holiday', 'start_date' => $row['date'], 'end_date' => $row['date'], 'all_day' => true, 'origin' => $row['kind'], 'source_url' => $snapshot['source_url'], 'imported_at' => now()]);
                DB::table('work_calendar_exceptions')->insert(['date' => $row['date'], 'calendar_event_id' => $event->id, 'work_calendar_year_id' => $record->id, 'kind' => 'holiday']);
            }
            $record->revision++;
            $record->work_revision++;
            $record->save();
            $result = ['preview_id' => $input['preview_id'], 'created' => $rows->count(), 'skipped' => count($snapshot['items']) - $rows->count(), 'calendar' => $record->publicData(), 'calendar_revisions' => [(string) $year => $record->revision]];
            DB::table('holiday_import_previews')->where('id', $preview->id)->update(['result' => json_encode($result, JSON_THROW_ON_ERROR), 'updated_at' => now()]);
            $this->calendar->audit($actor, 'holidays.applied', $result);

            return $result;
        });
    }
}
