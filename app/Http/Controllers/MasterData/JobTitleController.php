<?php

namespace App\Http\Controllers\MasterData;

use App\Enums\JobTitleGroupEnum;
use App\Http\Controllers\Controller;
use App\Http\FilterQuery;
use App\Http\Requests\StoreJobTitleRequest;
use App\Http\Requests\UpdateJobTitleRequest;
use App\Http\Resources\MasterData\JobTitleResource;
use App\Models\MasterData\JobTitle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response as InertiaResponse;

class JobTitleController extends Controller
{
    protected string $pageId = 'a30c2273-d730-4e1a-af2c-479e149cf54b';

    public function index(Request $request): InertiaResponse
    {
        $perPage = in_array($request->integer('per_page'), [5, 10, 15, 20, 50, 100])
            ? $request->integer('per_page')
            : 10;

        $keyword = JobTitle::normalizeSearchKeyword($request->query('keyword'));

        $filter = $request->query('filter');
        $requestedGroups =
            $filter === null ? $request->query('groups', []) : FilterQuery::parse($filter)['group'] ?? [];

        $groups = collect(is_array($requestedGroups) ? $requestedGroups : [])
            ->filter(fn(mixed $value): bool => is_int($value) || (is_string($value) && ctype_digit($value)))
            ->map(fn(int|string $value): ?int => JobTitleGroupEnum::tryFrom((int) $value)?->value)
            ->filter(fn(?int $value): bool => $value !== null)
            ->unique()
            ->values()
            ->all();

        $records = JobTitle::query()
            ->when($groups !== [], fn(Builder $query): Builder => $query->whereIn('group', $groups))
            ->search($keyword)
            ->orderBy('group')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return inertia('job-title/index', [
            'page' => ['id' => $this->pageId],
            'filters' => ['keyword' => $keyword, 'groups' => $groups],
            'groups' => JobTitleGroupEnum::options(),
            'resources' => JobTitleResource::collection($records),
        ]);
    }

    public function create(): InertiaResponse
    {
        return inertia('job-title/create', [
            'page' => ['id' => $this->pageId],
            'groups' => JobTitleGroupEnum::options(),
        ]);
    }

    public function store(StoreJobTitleRequest $request): RedirectResponse
    {
        JobTitle::create($request->safe()->only(['name', 'group']));

        return redirect()->route('jt.index');
    }

    public function edit(JobTitle $jobTitle): InertiaResponse
    {
        return inertia('job-title/edit', [
            'page' => ['id' => $this->pageId],
            'groups' => JobTitleGroupEnum::options(),
            'resource' => JobTitleResource::make($jobTitle),
        ]);
    }

    public function update(UpdateJobTitleRequest $request, JobTitle $jobTitle): RedirectResponse
    {
        $jobTitle->update($request->safe()->only(['name', 'group']));

        return redirect()->route('jt.index');
    }

    public function destroy(JobTitle $jobTitle): RedirectResponse
    {
        $jobTitle->delete();

        return redirect()->route('jt.index');
    }
}
