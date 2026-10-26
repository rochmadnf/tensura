<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangeLetterDraftRequest;
use App\Models\Letter;
use App\Services\Letter\ManageLetterDraft;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class LetterDraftChangeController extends Controller
{
    public function preview(ChangeLetterDraftRequest $request, Letter $letter, ManageLetterDraft $drafts): JsonResponse
    {
        return response()->json($drafts->previewChange($letter, (int) $request->validated('revision'), $request->route('kind')))->header('Cache-Control', 'private, no-store');
    }

    public function apply(ChangeLetterDraftRequest $request, Letter $letter, ManageLetterDraft $drafts): RedirectResponse
    {
        $drafts->applyChange($letter, $request->validated(), $request->user(), $request->route('kind'));

        return redirect()->route('ltr.show', $letter->uuid);
    }
}
