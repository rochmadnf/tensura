<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckLetterPreviewRequest;
use App\Http\Requests\GenerateLetterPreviewRequest;
use App\Http\Resources\LetterOperationResource;
use App\Models\Letter;
use App\Models\LetterArtifact;
use App\Services\Letter\LetterPreviewState;
use App\Services\Letter\ManageLetterPreview;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LetterPreviewController extends Controller
{
    public function store(GenerateLetterPreviewRequest $request, Letter $letter, ManageLetterPreview $previews): JsonResponse
    {
        return response()->json(['operation' => (new LetterOperationResource($previews->request($letter, $request->validated(), $request->user())))->resolve()], 202);
    }

    public function file(Letter $letter, LetterArtifact $artifact, LetterPreviewState $state): BinaryFileResponse
    {
        abort_unless($artifact->letter_id === $letter->id && $artifact->kind === 'docx', 404);
        $state->assertCurrent($letter, $artifact);

        return response()->file(Storage::disk($artifact->disk)->path($artifact->path), ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'Content-Disposition' => 'inline; filename="draft.docx"', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'])->setPrivate();
    }

    public function rendered(CheckLetterPreviewRequest $request, Letter $letter, LetterArtifact $artifact, ManageLetterPreview $previews): JsonResponse
    {
        abort_unless($artifact->letter_id === $letter->id && in_array($artifact->kind, ['docx', 'pdf'], true) && $artifact->letter_version_id === null, 404);
        $previews->check($letter, $artifact, $request->validated(), $request->user(), false);

        return response()->json(['rendered' => true]);
    }

    public function confirm(CheckLetterPreviewRequest $request, Letter $letter, LetterArtifact $artifact, ManageLetterPreview $previews): JsonResponse
    {
        abort_unless($artifact->letter_id === $letter->id && in_array($artifact->kind, ['docx', 'pdf'], true) && $artifact->letter_version_id === null, 404);
        $previews->check($letter, $artifact, $request->validated(), $request->user(), true);

        return response()->json(['confirmed' => true]);
    }
}
