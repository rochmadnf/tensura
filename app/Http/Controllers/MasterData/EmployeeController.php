<?php

namespace App\Http\Controllers\MasterData;

use App\Enums\AsnStatusEnum;
use App\Enums\ContractStatusEnum;
use App\Enums\JobTitleGroupEnum;
use App\Enums\StrStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\FilterQuery;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Http\Resources\MasterData\EmployeeResource;
use App\Models\MasterData\Employee;
use App\Models\MasterData\JobTitle;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Response as InertiaResponse;

class EmployeeController extends Controller
{
    protected string $pageId = 'a30c21b9-15ef-4dc0-97ef-524ead13be55';

    public function index(Request $request): InertiaResponse
    {
        $perPage = in_array($request->integer('per_page'), [5, 10, 15, 20, 50, 100])
            ? $request->integer('per_page')
            : 10;
        $keyword = Employee::normalizeSearchKeyword($request->query('keyword'));
        $parsed = FilterQuery::parse($request->query('filter'));
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
        $records = Employee::query()
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
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return inertia('employee/index', [
            'page' => ['id' => $this->pageId],
            'filters' => [
                'keyword' => $keyword,
                'job_title' => $jobTitles,
                'job_group' => $jobGroups,
                'gender' => $genders,
                'contract_status' => $contractStatuses,
                'status' => $statuses,
                'tenure_min' => $tenureMin,
                'tenure_max' => $tenureMax,
            ],
            ...$this->formOptions($jobTitles),
            'jobTitleGroups' => JobTitleGroupEnum::options(),
            'resources' => EmployeeResource::collection($records),
        ]);
    }

    public function create(): InertiaResponse
    {
        return inertia('employee/create', [
            'page' => ['id' => $this->pageId],
            ...$this->formOptions(),
        ]);
    }

    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $attributes = $request->validated();
        $attributes['job_title_id'] = JobTitle::query()->where('uuid', $attributes['job_title_id'])->firstOrFail()->id;
        Employee::create($attributes);

        return redirect()->route('empl.index');
    }

    public function show(Employee $employee): InertiaResponse
    {
        return inertia('employee/show', [
            'page' => ['id' => $this->pageId],
            'resource' => EmployeeResource::make($employee->load('jobTitle:id,uuid,name,group')),
        ]);
    }

    public function edit(Employee $employee): InertiaResponse
    {
        return inertia('employee/edit', [
            'page' => ['id' => $this->pageId],
            ...$this->formOptions([$employee->jobTitle?->uuid]),
            'resource' => EmployeeResource::make($employee->load('jobTitle:id,uuid,name,group')),
        ]);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $attributes = $request->validated();
        $attributes['job_title_id'] = JobTitle::query()->where('uuid', $attributes['job_title_id'])->firstOrFail()->id;
        $employee->update($attributes);

        return redirect()->route('empl.index');
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        DB::transaction(function () use ($employee): void {
            $employee = Employee::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();
            if ($employee->templateSignerCandidates()->exists()) {
                throw ValidationException::withMessages(['deletion' => 'Pegawai tidak dapat dihapus karena masih menjadi kandidat penandatangan templat surat, termasuk versi historis atau diarsipkan.']);
            }
            $employee->delete();
        }, 5);

        return redirect()->route('empl.index');
    }

    /** @return array{jobTitles: list<array{id: string, label: string, group: ?int}>, genders: list<array{id: int, label: string}>, strStatuses: list<array{id: int, label: string}>, contractStatuses: list<array{id: int, label: string}>, statuses: list<array{id: int, label: string}>, today: string} */
    private function formOptions(array $selected = []): array
    {
        return [
            'jobTitles' => JobTitle::query()->whereIn('uuid', array_filter($selected))
                ->orderBy('name')
                ->get(['uuid', 'name', 'group'])
                ->map(
                    fn(JobTitle $jobTitle): array => [
                        'id' => $jobTitle->uuid,
                        'label' => $jobTitle->name,
                        'group' => $jobTitle->group?->value,
                    ],
                )
                ->all(),
            'genders' => Employee::genderOptions(),
            'strStatuses' => StrStatusEnum::options(),
            'contractStatuses' => ContractStatusEnum::options(),
            'statuses' => AsnStatusEnum::options(),
            'today' => today()->toDateString(),
        ];
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
