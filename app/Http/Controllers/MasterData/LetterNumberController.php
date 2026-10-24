<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLetterNumberRequest;
use App\Http\Requests\UpdateLetterNumberRequest;
use App\Http\Resources\LetterNumberResource;
use App\Models\LetterNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response as InertiaResponse;

class LetterNumberController extends Controller
{
    protected string $pageId = 'a3063dab-44a6-4de2-9c93-8fa84742e3da';

    public function index(Request $request): InertiaResponse
    {
        $perPage = in_array($request->integer('per_page'), [5, 10, 15, 20, 50, 100])
            ? $request->integer('per_page')
            : 10;

        $keyword = $request->query('keyword');
        $keyword = is_string($keyword) ? trim($keyword) : '';
        $keyword = mb_strlen($keyword) >= 2 ? $keyword : '';

        $records = LetterNumber::query()
            ->when($keyword !== '', function (Builder $query) use ($keyword): void {
                $term = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($keyword)) . '%';

                $query->where(
                    fn(Builder $q): Builder => $q
                        ->whereRaw("LOWER(classification_name) LIKE ? ESCAPE '!'", [$term])
                        ->orWhereRaw("LOWER(classification_code) LIKE ? ESCAPE '!'", [$term]),
                );
            })
            ->orderBy('classification_code')
            ->paginate($perPage)
            ->withQueryString();

        return inertia('letter-number/index', [
            'page' => ['id' => $this->pageId],
            'filters' => ['keyword' => $keyword],
            'resources' => LetterNumberResource::collection($records),
        ]);
    }

    public function create(): InertiaResponse
    {
        return inertia('letter-number/create', [
            'page' => ['id' => $this->pageId],
        ]);
    }

    public function store(StoreLetterNumberRequest $request): RedirectResponse
    {
        LetterNumber::create(
            $request->safe()->only(['classification_name', 'classification_code', 'page_number', 'sequence_number']),
        );

        return redirect()->route('ln.index');
    }

    public function edit(LetterNumber $letterNumber): InertiaResponse
    {
        return inertia('letter-number/edit', [
            'page' => ['id' => $this->pageId],
            'resource' => LetterNumberResource::make($letterNumber),
        ]);
    }

    public function update(UpdateLetterNumberRequest $request, LetterNumber $letterNumber): RedirectResponse
    {
        $letterNumber->update($request->safe()->only(['classification_name', 'classification_code']));

        return redirect()->route('ln.index');
    }

    public function destroy(LetterNumber $letterNumber): RedirectResponse
    {
        $letterNumber->delete();

        return redirect()->route('ln.index');
    }
}
