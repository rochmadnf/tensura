<?php

namespace App\Http\Controllers;

use App\Enums\LetterDraftKindEnum;
use App\Http\Requests\LetterLifecycleRequest;
use App\Models\Letter;
use App\Services\Letter\ManageLetterLifecycle;
use Illuminate\Http\RedirectResponse;

class LetterLifecycleController extends Controller
{
    public function store(LetterLifecycleRequest $request, Letter $letter, ManageLetterLifecycle $lifecycle): RedirectResponse
    {
        $data = $request->validated();
        match (true) {
            $request->routeIs('ltr.revise') => $lifecycle->start($letter, $data, $request->user(), LetterDraftKindEnum::REVISION),
            $request->routeIs('ltr.recover') => $lifecycle->start($letter, $data, $request->user(), LetterDraftKindEnum::RECOVERY),
            $request->routeIs('ltr.cancel') => $lifecycle->cancel($letter, $data, $request->user()),
            default => $lifecycle->discard($letter, $data, $request->user()),
        };

        return redirect()->route('ltr.show', $letter->uuid);
    }
}
