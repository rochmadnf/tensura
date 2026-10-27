<?php

namespace App\Http\Controllers;

use App\Enums\LetterStatusEnum;
use App\Http\Requests\GenerateLetterPdfRequest;
use App\Http\Resources\LetterOperationResource;
use App\Models\Letter;
use App\Models\LetterArtifact;
use App\Services\Letter\LetterPdfState;
use App\Services\Letter\ManageLetterLifecycle;
use App\Services\Letter\ManageLetterPdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LetterPdfController extends Controller
{
    public function store(GenerateLetterPdfRequest $request, Letter $letter, LetterArtifact $artifact, ManageLetterPdf $pdfs): JsonResponse
    {
        abort_unless($artifact->letter_id === $letter->id && $artifact->kind === 'docx', 404);

        return response()->json(['operation' => (new LetterOperationResource($pdfs->request($letter, $artifact, $request->validated(), $request->user())))->resolve()], 202);
    }

    public function file(Request $request, Letter $letter, LetterArtifact $artifact, LetterPdfState $state): BinaryFileResponse
    {
        abort_unless($artifact->letter_id === $letter->id && $artifact->kind === 'pdf', 404);
        $state->assertCurrent($letter, $artifact);
        $headers = ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];
        $path = Storage::disk($artifact->disk)->path($artifact->path);
        if ($request->routeIs('ltr.pdfs.download')) {
            if ($letter->status === LetterStatusEnum::CANCELLED) {
                abort_unless(is_string($request->query('confirmation')) && hash_equals(app(ManageLetterLifecycle::class)->token($letter), $request->query('confirmation')), 422, 'Konfirmasikan bahwa dokumen berasal dari surat yang sudah dibatalkan.');
            }
            abort_unless($artifact->letter_version_id !== null, 404);

            return response()->download($path, 'surat-' . $letter->uuid . '-v' . $artifact->version->version_number . '.pdf', $headers)->setPrivate();
        }

        return response()->file($path, [...$headers, 'Content-Disposition' => 'inline; filename="surat.pdf"'])->setPrivate();
    }
}
