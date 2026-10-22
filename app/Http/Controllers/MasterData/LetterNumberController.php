<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\LetterNumberRequest;
use App\Models\LetterNumber;
use Illuminate\Http\RedirectResponse;
use Inertia\Response as InertiaResponse;

class LetterNumberController extends Controller
{
    protected string $pageId = 'a3063dab-44a6-4de2-9c93-8fa84742e3da';

    public function index(): InertiaResponse
    {
        return inertia('letter-number/index', [
            'page' => ['id' => $this->pageId],
        ]);
    }

    public function create(): InertiaResponse
    {
        return inertia('letter-number/create', [
            'page' => ['id' => $this->pageId],
        ]);
    }

    public function store(LetterNumberRequest $request): RedirectResponse
    {
        LetterNumber::create($request->whenFulfill());

        return redirect()->route('ln.index');
    }
}
