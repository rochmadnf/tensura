<?php

namespace App\Http\Controllers\MasterData;

use App\Enums\AsnStatusEnum;
use App\Enums\ContractStatusEnum;
use App\Enums\JobTitleGroupEnum;
use App\Enums\StrStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Http\Resources\MasterData\EmployeeResource;
use App\Models\MasterData\Employee;
use App\Models\MasterData\JobTitle;
use App\Services\Employee\EmployeeListing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Response as InertiaResponse;

class EmployeeController extends Controller
{
    protected string $pageId = 'a30c21b9-15ef-4dc0-97ef-524ead13be55';

    public function index(Request $request, EmployeeListing $listing): InertiaResponse
    {
        $perPage = in_array($request->integer('per_page'), [5, 10, 15, 20, 50, 100])
            ? $request->integer('per_page')
            : 10;
        $selection = $listing->resolve($request);
        $records = $selection['query']->paginate($perPage)->withQueryString();

        return inertia('employee/index', [
            'page' => ['id' => $this->pageId],
            'filters' => $selection['filters'],
            ...$this->formOptions($selection['filters']['job_title']),
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
}
