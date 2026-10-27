<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLetterNumberPeriodRequest;
use App\Models\LetterNumber;
use App\Services\LetterNumber\PrepareLetterNumberPeriod;
use Illuminate\Http\RedirectResponse;

class LetterNumberPeriodController extends Controller
{
    public function store(StoreLetterNumberPeriodRequest $request, LetterNumber $letterNumber, PrepareLetterNumberPeriod $periods): RedirectResponse
    {
        $periods->prepare($letterNumber, $request->safe()->only(['year', 'month', 'mode', 'last_page_number', 'last_sequence_number']));

        return redirect()->route('ln.show', $letterNumber->uuid);
    }
}
