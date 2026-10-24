<?php

namespace App\Http\Controllers;

use App\Enums\ArchiveKindEnum;
use App\Http\Resources\ArchiveRecordResource;
use App\Http\Resources\ArchiveResource;
use App\Models\Archive;
use App\Services\Archive\ReadArchive;
use App\Services\Archive\ReferenceSnapshot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Response as InertiaResponse;

class ArchiveController extends Controller
{
    public function index(Request $request, ReadArchive $read): InertiaResponse
    {
        $request->validate([
            'keyword' => ['nullable', 'string', 'max:255'],
            'module' => ['nullable', 'string', 'max:100'],
            'kind' => ['nullable', Rule::enum(ArchiveKindEnum::class)],
        ]);
        $context = $request->routeIs('ref.archive.index') ? 'reference' : null;
        $keyword = trim($request->string('keyword')->toString());
        $keyword = mb_strlen($keyword) >= 2 ? $keyword : '';
        $module = $context ?? $request->string('module')->toString();
        $kind = $request->string('kind')->toString();
        $archives = $read->query()
            ->when($module !== '', fn(Builder $query) => $query->where('module', $module))
            ->when($kind !== '', fn(Builder $query) => $query->where('kind', $kind))
            ->when($keyword !== '', function (Builder $query) use ($keyword): void {
                $term = '%' . addcslashes($keyword, '\\%_') . '%';
                $query->where(function (Builder $query) use ($term): void {
                    $query->whereRaw("label LIKE ? ESCAPE '\\'", [$term])
                        ->orWhereHas('records', fn(Builder $records) => $records->whereRaw("label LIKE ? ESCAPE '\\'", [$term]));
                });
            })
            ->orderByDesc('archived_at')->orderByDesc('id')
            ->paginate($this->perPage($request))->withQueryString();
        $read->loadTargets($archives->getCollection());

        return inertia('archive/index', [
            'page' => ['id' => 'archive'],
            'context' => $context,
            'filters' => ['keyword' => $keyword, 'module' => $module, 'kind' => $kind],
            'modules' => Archive::query()->distinct()->orderBy('module')->pluck('module'),
            'resources' => ArchiveResource::collection($archives),
        ]);
    }

    public function show(Request $request, Archive $archive, ReadArchive $read): InertiaResponse
    {
        $archive = $read->query()->whereKey($archive->id)->firstOrFail();
        $read->loadTargets(collect([$archive]));
        $parent = $archive->records()->where('entity', ReferenceSnapshot::GROUP)->orderBy('id')->first();
        $children = $archive->records()->where('entity', '!=', ReferenceSnapshot::GROUP)
            ->orderBy('id')->paginate($this->perPage($request))->withQueryString();

        return inertia('archive/show', [
            'page' => ['id' => 'archive'],
            'context' => $request->query('context') === 'reference' && $archive->module === 'reference' ? 'reference' : null,
            'resource' => ArchiveResource::make($archive),
            'parent_record' => $parent !== null ? ArchiveRecordResource::make($parent) : null,
            'resources' => ArchiveRecordResource::collection($children),
        ]);
    }

    private function perPage(Request $request): int
    {
        return in_array($request->integer('per_page'), [5, 10, 15, 20, 50, 100]) ? $request->integer('per_page') : 10;
    }
}
