<?php

namespace App\Services\Employee;

use App\Enums\AsnStatusEnum;
use App\Enums\ContractStatusEnum;
use App\Enums\JobTitleGroupEnum;
use App\Http\FilterQuery;
use App\Models\MasterData\Employee;
use App\Models\MasterData\JobTitle;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EmployeeListing
{
    /** @return array{query: Builder<Employee>, filters: array{keyword: string, job_title: list<string>, job_group: list<int>, gender: list<int>, contract_status: list<int>, status: list<int>, tenure_min: ?int, tenure_max: ?int}} */
    public function resolve(?Request $request = null): array
    {
        $keyword = Employee::normalizeSearchKeyword($request?->query('keyword'));
        $parsed = FilterQuery::parse($request?->query('filter'));
        $jobTitles = JobTitle::query()
            ->whereIn('uuid', array_filter($parsed['job_title'] ?? [], fn(string $value): bool => Str::isUuid($value)))
            ->pluck('uuid')
            ->all();
        $contractStatuses = $this->enumValues($parsed['contract_status'] ?? [], ContractStatusEnum::class);
        $statuses = $this->enumValues($parsed['status'] ?? [], AsnStatusEnum::class);
        $jobGroups = $this->enumValues($parsed['job_group'] ?? [], JobTitleGroupEnum::class);
        $genders = collect($parsed['gender'] ?? [])
            ->filter(
                fn(string $value): bool => in_array(
                    $value,
                    [(string) Employee::LAKI_LAKI, (string) Employee::PEREMPUAN],
                    true,
                ),
            )
            ->map(fn(string $value): int => (int) $value)
            ->unique()
            ->values()
            ->all();
        $tenureMin = $this->tenureBound($parsed['tenure_min'] ?? []);
        $tenureMax = $this->tenureBound($parsed['tenure_max'] ?? []);

        if ($tenureMin !== null && $tenureMax !== null && $tenureMin > $tenureMax) {
            $tenureMin = $tenureMax = null;
        }

        $today = today();
        /** Keep derived join dates in positive calendar years, even for extreme filter input. */
        $maximumRepresentableTenure = $today->year - 1;
        $query = Employee::query()
            ->with('jobTitle:id,uuid,name,group')
            ->when(
                $jobGroups !== [] || $jobTitles !== [],
                fn(Builder $query): Builder => $query->whereHas(
                    'jobTitle',
                    fn(Builder $jobTitle): Builder => $jobTitle
                        ->when($jobGroups !== [], fn(Builder $query): Builder => $query->whereIn('group', $jobGroups))
                        ->when($jobTitles !== [], fn(Builder $query): Builder => $query->whereIn('uuid', $jobTitles)),
                ),
            )
            ->when($genders !== [], fn(Builder $query): Builder => $query->whereIn('gender', $genders))
            ->when(
                $contractStatuses !== [],
                fn(Builder $query): Builder => $query->whereIn('contract_status', $contractStatuses),
            )
            ->when($statuses !== [], fn(Builder $query): Builder => $query->whereIn('status', $statuses))
            ->when($tenureMin !== null, function (Builder $query) use ($tenureMin, $today, $maximumRepresentableTenure): void {
                if ($tenureMin > $maximumRepresentableTenure) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->where(
                        'join_date',
                        '<=',
                        $today->copy()->subYearsNoOverflow($tenureMin)->toDateString(),
                    );
                }
            })
            ->when(
                $tenureMax !== null && $tenureMax < $maximumRepresentableTenure,
                fn(Builder $query): Builder => $query->where(
                    'join_date',
                    '>',
                    $today
                        ->copy()
                        ->subYearsNoOverflow($tenureMax + 1)
                        ->toDateString(),
                ),
            )
            ->search($keyword)
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [AsnStatusEnum::AKTIF->value])
            ->orderBy('join_date')
            ->orderBy('name')
            ->orderBy('id');

        return ['query' => $query, 'filters' => [
            'keyword' => $keyword,
            'job_title' => $jobTitles,
            'job_group' => $jobGroups,
            'gender' => $genders,
            'contract_status' => $contractStatuses,
            'status' => $statuses,
            'tenure_min' => $tenureMin,
            'tenure_max' => $tenureMax,
        ]];
    }

    /** @param list<string> $values
     * @param  class-string<BackedEnum>  $enum
     * @return list<int>
     */
    private function enumValues(array $values, string $enum): array
    {
        return collect($values)
            ->filter(fn(string $value): bool => ctype_digit($value) && strlen($value) < 10)
            ->map(fn(string $value): ?int => $enum::tryFrom((int) $value)?->value)
            ->filter(fn(?int $value): bool => $value !== null)
            ->unique()
            ->values()
            ->all();
    }

    /** @param list<string> $values */
    private function tenureBound(array $values): ?int
    {
        if (count($values) !== 1) {
            return null;
        }

        $value = filter_var($values[0], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

        return $value === false ? null : $value;
    }
}
