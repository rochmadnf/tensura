<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLetterNumberRequest;
use App\Http\Requests\UpdateLetterNumberRequest;
use App\Http\Resources\LetterNumberPeriodResource;
use App\Http\Resources\LetterNumberResource;
use App\Models\LetterNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Response as InertiaResponse;

class LetterNumberController extends Controller
{
    protected string $pageId = 'a3063dab-44a6-4de2-9c93-8fa84742e3da';

    public function index(Request $request): InertiaResponse
    {
        $perPage = in_array($request->integer('per_page'), [5, 10, 15, 20, 50, 100])
            ? $request->integer('per_page')
            : 10;

        $keyword = LetterNumber::normalizeSearchKeyword($request->query('keyword'));

        $records = LetterNumber::query()
            ->search($keyword)
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
            $request->safe()->only(['classification_name', 'classification_code']),
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

    public function show(Request $request, LetterNumber $letterNumber): InertiaResponse
    {
        $perPage = in_array($request->integer('per_page'), [5, 10, 15, 20, 50, 100]) ? $request->integer('per_page') : 10;

        return inertia('letter-number/show', [
            'page' => ['id' => $this->pageId],
            'resource' => LetterNumberResource::make($letterNumber),
            'resources' => LetterNumberPeriodResource::collection($letterNumber->periods()->orderByDesc('year')->orderByDesc('month')->paginate($perPage)->withQueryString()),
            'periodDefaults' => ['year' => now()->year, 'month' => now()->month],
        ]);
    }

    public function update(UpdateLetterNumberRequest $request, LetterNumber $letterNumber): RedirectResponse
    {
        DB::transaction(function () use ($request, $letterNumber): void {
            $letterNumber = LetterNumber::query()->whereKey($letterNumber->id)->lockForUpdate()->firstOrFail();
            $data = $request->safe()->only(['classification_name', 'classification_code']);
            if ($data['classification_code'] !== $letterNumber->classification_code && $letterNumber->periods()->whereHas('assignments')->exists()) {
                throw ValidationException::withMessages(['classification_code' => 'Kode klasifikasi sudah digunakan surat dan tidak dapat diubah. Buat klasifikasi baru untuk kode berbeda.']);
            }
            $letterNumber->update($data);
        }, 5);

        return redirect()->route('ln.index');
    }

    public function destroy(LetterNumber $letterNumber): RedirectResponse
    {
        DB::transaction(function () use ($letterNumber): void {
            $letterNumber = LetterNumber::query()->whereKey($letterNumber->id)->lockForUpdate()->firstOrFail();
            if ($letterNumber->templateVersions()->exists()) {
                throw ValidationException::withMessages(['deletion' => 'Klasifikasi tidak dapat dihapus karena masih dirujuk templat surat, termasuk versi historis atau diarsipkan.']);
            }
            if ($letterNumber->periods()->exists()) {
                throw ValidationException::withMessages(['deletion' => 'Klasifikasi tidak dapat dihapus karena memiliki periode penomoran.']);
            }
            $letterNumber->delete();
        }, 5);

        return redirect()->route('ln.index');
    }
}
