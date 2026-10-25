<?php

namespace App\Services\LetterTemplate;

use App\Models\MasterData\Employee;
use Carbon\CarbonImmutable;
use DateTimeInterface;

class ResolveEmployeeAttributes
{
    public function __construct(private FormatEmployeeDate $dates) {}

    /**
     * @param  list<array{source_attribute: string, default_text?: ?string, date_format?: ?string}>  $settings
     * @return array<string, string>
     */
    public function resolve(?Employee $employee, array $settings, DateTimeInterface $asOf): array
    {
        $values = [];
        $settings = collect($settings)->keyBy('source_attribute');
        $tenure = null;
        if ($employee?->join_date !== null) {
            $end = CarbonImmutable::instance($asOf)->setTimezone(config('app.timezone'))->startOfDay();
            $start = CarbonImmutable::instance($employee->join_date)->startOfDay();
            $difference = $start->greaterThan($end) ? $end->diff($end) : $start->diff($end);
            $tenure = ['years' => $difference->y, 'months' => $difference->m, 'days' => $difference->d];
        }
        foreach (EmployeeAttributeCatalog::options() as $field) {
            $key = $field['id'];
            $value = $employee === null ? null : match ($key) {
                'name' => trim(implode(' ', array_filter([$employee->front_title, $employee->name, $employee->back_title], fn(?string $part): bool => filled($part)))),
                'raw_name' => $employee->name,
                'gender' => collect(Employee::genderOptions())->firstWhere('id', $employee->gender)['label'] ?? null,
                'job_title' => $employee->jobTitle?->name, 'job_title_group' => $employee->jobTitle?->group?->label(),
                'contract_status' => $employee->contract_status?->label(), 'status' => $employee->status?->label(), 'str_status' => $employee->str_status?->label(),
                'tenure' => $tenure === null ? null : (implode(' ', array_filter([
                    $tenure['years'] ? $tenure['years'] . ' tahun' : '', $tenure['months'] ? $tenure['months'] . ' bulan' : '',
                    $tenure['days'] ? $tenure['days'] . ' hari' : '',
                ])) ?: '0 hari'),
                'tenure_years' => $tenure['years'] ?? null, 'tenure_months' => $tenure['months'] ?? null, 'tenure_days' => $tenure['days'] ?? null,
                default => $employee->getAttribute($key),
            };
            $setting = $settings->get($key, []);
            if ($value instanceof DateTimeInterface) {
                $value = $this->dates->format($value, $setting['date_format'] ?? null);
            }
            $values[$key] = blank($value) ? (string) ($setting['default_text'] ?? '') : (string) $value;
        }

        return $values;
    }

    /**
     * @param  list<array{source_attribute: string, default_text?: ?string, date_format?: ?string}>  $settings
     * @return array<string, string>
     */
    public function tokens(string $prefix, ?Employee $employee, array $settings, DateTimeInterface $asOf): array
    {
        $values = $this->resolve($employee, $settings, $asOf);

        return array_map(fn(string $attribute): string => $values[$attribute], EmployeeAttributeCatalog::tokens($prefix));
    }
}
