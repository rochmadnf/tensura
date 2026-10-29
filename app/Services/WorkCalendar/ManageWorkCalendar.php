<?php

namespace App\Services\WorkCalendar;

use App\Models\CalendarEvent;
use App\Models\MasterData\User;
use App\Models\WorkCalendarYear;
use Carbon\CarbonImmutable as Date;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class ManageWorkCalendar
{
    public function prepare(array $input, User $actor): array
    {
        try {
            return DB::transaction(function () use ($input, $actor): array {
                if (WorkCalendarYear::where('year', $input['year'])->exists()) {
                    throw new CalendarFailure('revision_conflict', 'Kalender tahun sudah tersedia.', 409);
                }
                $week = isset($input['copy_from_year']) ? WorkCalendarYear::where('year', $input['copy_from_year'])->firstOrFail()->working_weekdays : [1, 2, 3, 4, 5, 6];
                $year = WorkCalendarYear::create(['year' => $input['year'], 'working_weekdays' => $week]);
                $this->audit($actor, 'year.prepared', ['year' => $year->year]);

                return $year->fresh()->publicData();
            });
        } catch (QueryException $e) {
            if ($e->getCode() === '23505') {
                throw new CalendarFailure('revision_conflict', 'Kalender tahun sudah tersedia.', 409);
            }
            throw $e;
        }
    }

    public function update(int $year, array $input, User $actor, bool $confirm = false): array
    {
        return DB::transaction(function () use ($year, $input, $actor, $confirm): array {
            $record = WorkCalendarYear::where('year', $year)->lockForUpdate()->firstOrFail();
            $this->revision($record, $input['revision']);
            $before = $record->publicData();
            if ($confirm) {
                $record->fill(['confirmed_work_revision' => $record->work_revision, 'confirmed_at' => now(), 'confirmed_by_uuid' => $actor->uuid, 'confirmed_by_name' => $actor->name]);
            } else {
                $days = array_map('intval', $input['working_weekdays']);
                sort($days);
                if ($days === $record->working_weekdays) {
                    return $record->publicData();
                }
                $record->working_weekdays = $days;
                $record->work_revision++;
            }
            $record->revision++;
            $record->save();
            $this->audit($actor, $confirm ? 'year.confirmed' : 'year.changed', ['before' => $before, 'after' => $record->publicData()]);

            return $record->publicData();
        });
    }

    public function event(array $input, User $actor, ?string $uuid = null, bool $delete = false): array
    {
        return DB::transaction(function () use ($input, $actor, $uuid, $delete): array {
            $initial = $uuid === null ? null : CalendarEvent::where('uuid', $uuid)->firstOrFail();
            $dates = $delete ? [] : $this->dates($input['start_date'], $input['end_date']);
            $oldDates = $initial === null ? [] : $this->dates($initial->start_date, $initial->end_date);
            $years = array_values(array_unique(array_map(fn(string $date): int => (int) substr($date, 0, 4), [...$dates, ...$oldDates])));
            sort($years);
            $records = WorkCalendarYear::whereIn('year', $years)->orderBy('year')->lockForUpdate()->get()->keyBy('year');
            if ($records->count() !== count($years)) {
                throw new CalendarFailure('calendar_year_missing', 'Siapkan seluruh tahun yang dipakai kegiatan.', 422, ['calendar' => ['Ada tahun yang belum disiapkan.']]);
            }
            foreach ($records as $year => $record) {
                $this->revision($record, $input['calendar_revisions'][$year] ?? 0);
            }
            $event = $uuid === null ? new CalendarEvent() : CalendarEvent::where('uuid', $uuid)->lockForUpdate()->firstOrFail();
            if ($initial !== null) {
                $this->revision($event, $input['revision']);
                if ($initial->revision !== $event->revision) {
                    throw new CalendarFailure('revision_conflict', 'Kegiatan sudah berubah.', 409);
                }
            }
            $before = $initial?->publicData();
            if (!$delete) {
                if ($input['kind'] !== 'activity' && (!$input['all_day'] || isset($input['start_time']) || isset($input['end_time']))) {
                    throw new CalendarFailure('validation_failed', 'Libur dan hari kerja pengganti harus sepanjang hari.', 422, ['all_day' => ['Gunakan sepanjang hari tanpa jam.']]);
                }
                if (!$input['all_day'] && (!isset($input['start_time'],$input['end_time']) || $input['end_date'] . ' ' . $input['end_time'] <= $input['start_date'] . ' ' . $input['start_time'])) {
                    throw new CalendarFailure('validation_failed', 'Jam selesai harus sesudah mulai.', 422, ['end_time' => ['Isi jam mulai dan selesai yang valid.']]);
                }
                if ($input['kind'] !== 'activity' && DB::table('work_calendar_exceptions')->whereIn('date', $dates)->when($event->exists, fn($q) => $q->where('calendar_event_id', '!=', $event->id))->exists()) {
                    throw new CalendarFailure('calendar_exception_conflict', 'Ada pengecualian pada tanggal yang sama.', 422, ['start_date' => ['Periksa pengecualian yang sudah tersimpan.']]);
                }
                $event->fill(array_intersect_key($input, array_flip(['title', 'kind', 'start_date', 'end_date', 'all_day', 'start_time', 'end_time', 'location', 'description'])));
                if ($input['all_day']) {
                    $event->start_time = $event->end_time = null;
                }
                $event->origin = 'manual';
                if ($event->exists) {
                    $event->revision++;
                }
                $event->save();
                $event->refresh();
            }
            $id = $event->uuid;
            DB::table('work_calendar_exceptions')->where('calendar_event_id', $event->id)->delete();
            if ($delete) {
                $event->delete();
            } elseif ($input['kind'] !== 'activity') {
                foreach ($dates as $date) {
                    DB::table('work_calendar_exceptions')->insert(['calendar_event_id' => $event->id, 'work_calendar_year_id' => $records[(int) substr($date, 0, 4)]->id, 'date' => $date, 'kind' => $event->kind]);
                }
            }
            $map = [];
            foreach ($records as $year => $record) {
                $record->revision++;
                if (($initial !== null && $initial->kind !== 'activity' && in_array($year, array_map(fn($d) => (int) substr($d, 0, 4), $oldDates), true)) || (!$delete && $input['kind'] !== 'activity' && in_array($year, array_map(fn($d) => (int) substr($d, 0, 4), $dates), true))) {
                    $record->work_revision++;
                }
                $record->save();
                $map[(string) $year] = $record->revision;
            }
            $this->audit($actor, $delete ? 'event.deleted' : ($initial === null ? 'event.created' : 'event.updated'), ['before' => $before, 'after' => $delete ? null : $event->publicData($map)]);

            return $delete ? ['id' => $id, 'calendar_revisions' => (object) $map] : $event->publicData($map);
        });
    }

    public function dates(string $start, string $end): array
    {
        $from = Date::parse($start)->startOfDay();
        $to = Date::parse($end)->startOfDay();
        $count = (int) $from->diffInDays($to, false) + 1;
        if ($count < 1 || $count > 366) {
            throw new CalendarFailure('validation_failed', 'Rentang tanggal harus 1 sampai 366 hari.', 422, ['end_date' => ['Rentang maksimal 366 hari.']]);
        }
        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $result[] = $from->addDays($i)->toDateString();
        }

        return $result;
    }

    public function revision(object $model, mixed $expected): void
    {
        if ($model->revision !== (int) $expected) {
            throw new CalendarFailure('revision_conflict', 'Data sudah berubah. Muat ulang status sebelum menyimpan.', 409, ['revision' => ['Versi data sudah berubah.']]);
        }
    }

    public function audit(User $actor, string $operation, array $changes): void
    {
        DB::table('work_calendar_audits')->insert(['actor_uuid' => $actor->uuid, 'actor_name' => $actor->name, 'operation' => $operation, 'changes' => json_encode($changes, JSON_THROW_ON_ERROR), 'occurred_at' => now()]);
    }

    public function data(array $query): array
    {
        $date = isset($query['date']) ? Date::parse($query['date']) : now()->toImmutable();
        if (isset($query['year']) && isset($query['date']) && (int) $query['year'] !== $date->year) {
            throw new CalendarFailure('validation_failed', 'Tahun dan tanggal tidak sesuai.', 422, ['year' => ['Tahun harus sesuai tanggal.']]);
        }
        if (isset($query['year']) && !isset($query['date'])) {
            $date = $date->startOfMonth()->setYear((int) $query['year']);
        }
        $view = $query['view'] ?? 'month';
        $start = match ($view) {
            'month' => $date->startOfMonth()->startOfWeek(CarbonInterface::MONDAY),'week' => $date->startOfWeek(CarbonInterface::MONDAY),'day' => $date->startOfDay(),default => $date->startOfMonth()
        };
        $end = match ($view) {
            'month' => $start->addDays(41),'week' => $start->addDays(6),'day' => $start,default => $date->endOfMonth()
        };
        $years = WorkCalendarYear::whereBetween('year', [$start->year, $end->year])->orderBy('year')->get()->keyBy('year');
        $exceptions = DB::table('work_calendar_exceptions')->whereBetween('date', [$start->toDateString(), $end->toDateString()])->get()->keyBy('date');
        $days = [];
        $exceptionEvents = CalendarEvent::whereIn('id', $exceptions->pluck('calendar_event_id'))->pluck('uuid', 'id');
        for ($d = $start; $d->toDateString() <= $end->toDateString(); $d = $d->addDay()) {
            $year = $years->get($d->year);
            $ex = $exceptions->get($d->toDateString());
            $working = $year === null ? null : ($ex === null ? in_array($d->isoWeekday(), $year->working_weekdays, true) : $ex->kind === 'working_day');
            $days[] = ['date' => $d->toDateString(), 'weekday' => $d->isoWeekday(), 'in_selected_month' => $d->month === $date->month && $d->year === $date->year, 'is_today' => $d->isToday(), 'is_working' => $working, 'basis' => $year === null ? 'unconfigured' : ($ex?->kind ?? 'weekly'), 'exception_id' => $ex === null ? null : $exceptionEvents->get($ex->calendar_event_id)];
        }
        $events = CalendarEvent::where('start_date', '<=', $end->toDateString())->where('end_date', '>=', $start->toDateString());
        if (filled($query['kind'] ?? null)) {
            $events->where('kind', $query['kind']);
        }
        if (filled($query['search'] ?? null)) {
            $text = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query['search']);
            $events->where(fn($q) => $q->where('title', 'ilike', '%' . $text . '%')->orWhere('description', 'ilike', '%' . $text . '%')->orWhere('location', 'ilike', '%' . $text . '%'));
        }
        $events = $events->orderBy('start_date')->orderByDesc('all_day')->orderBy('start_time')->orderBy('id')->limit(1001)->get();
        if ($events->count() > 1000) {
            throw new CalendarFailure('validation_failed', 'Persempit filter kalender, hasil lebih dari 1000 kegiatan.');
        }

        $eventYears = [];
        foreach ($events as $event) {
            $eventYears = [...$eventYears, ...range((int) substr($event->start_date, 0, 4), (int) substr($event->end_date, 0, 4))];
        }
        $eventRevisions = WorkCalendarYear::whereIn('year', array_unique($eventYears))->pluck('revision', 'year')->all();

        return ['contract_version' => 1, 'timezone' => config('app.timezone'), 'query' => ['year' => $date->year, 'date' => $date->toDateString(), 'view' => $view, 'search' => $query['search'] ?? '', 'kind' => $query['kind'] ?? null], 'calendar' => $years->get($date->year)?->publicData(), 'calendar_years' => $years->map->publicData()->values()->all(), 'period' => ['start_date' => $start->toDateString(), 'end_date' => $end->toDateString()], 'days' => $days, 'events' => $events->map(fn($e) => $e->publicData(array_intersect_key($eventRevisions, array_flip(range((int) substr($e->start_date, 0, 4), (int) substr($e->end_date, 0, 4))))))->all(), 'missing_years' => array_values(array_diff(range($start->year, $end->year), $years->keys()->all())), 'truncated' => false];
    }
}
