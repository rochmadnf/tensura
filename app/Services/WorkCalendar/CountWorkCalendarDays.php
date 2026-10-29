<?php

namespace App\Services\WorkCalendar;

use App\Models\WorkCalendarYear;
use App\Services\Letter\SpellLetterNumber;
use Carbon\CarbonImmutable as Date;
use Illuminate\Support\Facades\DB;

class CountWorkCalendarDays
{
    public function __construct(private ManageWorkCalendar $calendar, private SpellLetterNumber $numbers) {}

    public function count(string $start, string $end, string $basis): array
    {
        return DB::transaction(function () use ($start, $end, $basis): array {
            $dates = $this->calendar->dates($start, $end);
            $working = $basis === 'working_days';
            $years = [];
            $exceptions = collect();
            $state = [];
            if ($working) {
                $needed = array_values(array_unique(array_map(fn($d) => (int) substr($d, 0, 4), $dates)));
                $years = WorkCalendarYear::whereIn('year', $needed)->orderBy('year')->sharedLock()->get()->keyBy('year');
                if ($years->count() !== count($needed)) {
                    throw new CalendarFailure('calendar_year_missing', 'Siapkan kalender untuk seluruh tahun dalam rentang.', 422, ['calendar' => ['Kalender tahun belum tersedia.']]);
                }
                foreach ($years as $year) {
                    if (!$year->ready()) {
                        throw new CalendarFailure('calendar_not_ready', 'Konfirmasikan kalender sebelum menghitung hari kerja.', 422, ['calendar' => ['Kalender ' . $year->year . ' belum siap.']]);
                    }
                    $state[] = ['year' => $year->year, 'work_revision' => $year->work_revision];
                }
                $exceptions = DB::table('work_calendar_exceptions')->whereBetween('date', [$start, $end])->get()->keyBy('date');
            }
            $details = [];
            $count = 0;
            foreach ($dates as $date) {
                $ex = $exceptions->get($date);
                $reason = $working ? ($ex?->kind ?? (in_array(Date::parse($date)->isoWeekday(), $years[(int) substr($date, 0, 4)]->working_weekdays, true) ? 'working_day' : 'weekly_off')) : 'calendar_day';
                $counted = in_array($reason, ['working_day', 'calendar_day'], true);
                $count += (int) $counted;
                $details[] = ['date' => $date, 'counted' => $counted, 'reason' => $reason];
            }
            if ($working && $count === 0) {
                throw new CalendarFailure('zero_working_days', 'Rentang tidak memiliki hari kerja.', 422, ['end_date' => ['Pilih rentang dengan hari kerja.']]);
            }
            $words = $this->numbers->resolve((string) $count, ['mode' => 'integer', 'scale' => 0, 'allow_negative' => false])['words'];

            return ['start_date' => $start, 'end_date' => $end, 'basis' => $basis, 'days' => $count, 'words' => $words, 'duration' => $count . ' (' . $words . ') hari', 'dates' => $details, 'calendar_state' => $working ? ['state' => 'current', 'years' => $state] : null];
        });
    }
}
