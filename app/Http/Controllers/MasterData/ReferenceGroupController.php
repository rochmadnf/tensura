<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReferenceGroupRequest;
use App\Http\Requests\UpdateReferenceGroupRequest;
use App\Http\Resources\MasterData\ReferenceGroupResource;
use App\Models\MasterData\ReferenceGroup;
use App\Services\Archive\ArchiveReference;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Response as InertiaResponse;

class ReferenceGroupController extends Controller
{
    protected string $pageId = 'a3063dcc-2750-45e0-8ea7-c859c50bd3fb';

    public function index(Request $request): InertiaResponse
    {
        $perPage = in_array($request->integer('per_page'), [5, 10, 15, 20, 50, 100])
            ? $request->integer('per_page')
            : 10;
        $keyword = trim($request->string('keyword')->toString());
        $keyword = mb_strlen($keyword) >= 2 ? $keyword : '';

        $records = ReferenceGroup::query()
            ->withCount('items')
            ->when($keyword !== '', function (Builder $query) use ($keyword): void {
                $term = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($keyword)) . '%';
                $query->where(function (Builder $query) use ($term): void {
                    $query
                        ->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$term])
                        ->orWhereRaw("LOWER(description) LIKE ? ESCAPE '!'", [$term]);
                });
            })
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return inertia('reference/index', [
            'page' => ['id' => $this->pageId],
            'filters' => ['keyword' => $keyword],
            'resources' => ReferenceGroupResource::collection($records),
        ]);
    }

    public function create(): InertiaResponse
    {
        return inertia('reference/create', ['page' => ['id' => $this->pageId]]);
    }

    public function store(StoreReferenceGroupRequest $request): RedirectResponse
    {
        $referenceGroup = ReferenceGroup::create($request->safe()->only(['name', 'description']));

        return redirect()->route('ref.edit', $referenceGroup->uuid);
    }

    public function edit(ReferenceGroup $referenceGroup): InertiaResponse
    {
        $referenceGroup->load('items')->loadCount('items');

        return inertia('reference/edit', [
            'page' => ['id' => $this->pageId],
            'resource' => ReferenceGroupResource::make($referenceGroup),
        ]);
    }

    public function update(UpdateReferenceGroupRequest $request, ReferenceGroup $referenceGroup): RedirectResponse
    {
        DB::transaction(function () use ($request, $referenceGroup): void {
            ReferenceGroup::query()
                ->whereKey($referenceGroup->id)
                ->where('uuid', $referenceGroup->uuid)
                ->lockForUpdate()
                ->firstOrFail()
                ->update($request->safe()->only(['name', 'description']));
        }, 5);

        return redirect()->route('ref.edit', $referenceGroup->uuid);
    }

    public function destroy(Request $request, ReferenceGroup $referenceGroup, ArchiveReference $archive): RedirectResponse
    {
        $archive->group($referenceGroup, $request->user());

        return redirect()->route('ref.index');
    }
}
