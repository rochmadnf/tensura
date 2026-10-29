<?php

namespace App\Services\Letter;

use App\Models\WorkCalendarYear;
use Illuminate\Validation\ValidationException;

class LetterCalendarState
{
    public function years(array $snapshot, array $inputs = []): array
    {
        $years = [];
        foreach ($snapshot['question_results'] ?? [] as $question) {
            foreach ($question['result']['calendar_state']['years'] ?? [] as $year) {
                $years[] = (int) $year['year'];
            }
        }
        foreach ($inputs as $input) {
            if (is_array($input) && isset($input['start_date'],$input['end_date']) && is_string($input['start_date']) && is_string($input['end_date'])) {
                $first = (int) substr($input['start_date'], 0, 4);
                $last = (int) substr($input['end_date'], 0, 4);
                if ($first >= 1900 && $first <= 2199 && $last >= $first && $last <= $first + 1 && $last <= 2199) {
                    $years = [...$years, ...range($first, $last)];
                }
            }
        }
        $years = array_values(array_unique($years));
        sort($years);

        return $years;
    }

    public function lock(array $snapshot, array $inputs = []): void
    {
        WorkCalendarYear::whereIn('year', $this->years($snapshot, $inputs))->orderBy('year')->sharedLock()->get();
    }

    public function state(array $snapshot): ?array
    {
        if ($snapshot['calendar_missing'] ?? false) {
            return ['state' => 'missing', 'years' => [], 'changed_years' => $this->years($snapshot, $snapshot['inputs']['values'] ?? [])];
        }
        $dependencies = [];
        foreach ($snapshot['question_results'] ?? [] as $question) {
            foreach ($question['result']['calendar_state']['years'] ?? [] as $year) {
                $dependencies[$year['year'] . ':' . $year['work_revision']] = $year;
            }
        }
        if ($dependencies === []) {
            return null;
        }
        $years = array_values($dependencies);
        usort($years, fn(array $a, array $b): int => [$a['year'], $a['work_revision']] <=> [$b['year'], $b['work_revision']]);
        $current = WorkCalendarYear::whereIn('year', array_column($years, 'year'))->get()->keyBy('year');
        $changed = [];
        foreach ($years as $year) {
            $record = $current->get($year['year']);
            if ($record === null || !$record->ready() || $record->work_revision !== $year['work_revision']) {
                $changed[] = $year['year'];
            }
        }

        return ['state' => $changed === [] ? 'current' : 'stale', 'years' => $years, 'changed_years' => array_values(array_unique($changed))];
    }

    public function assertCurrent(array $snapshot): void
    {
        $this->lock($snapshot);
        if (($this->state($snapshot)['state'] ?? 'current') === 'stale') {
            throw ValidationException::withMessages(['calendar' => 'Kalender kerja berubah atau belum siap. Tinjau dan konfirmasi refresh data sebelum menerbitkan.']);
        }
    }
}
