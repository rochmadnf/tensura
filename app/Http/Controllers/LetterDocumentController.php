<?php

namespace App\Http\Controllers;

use App\Enums\LetterStatusEnum;
use App\Models\Letter;
use App\Models\LetterVersion;
use App\Services\Letter\ManageLetterLifecycle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LetterDocumentController extends Controller
{
    public function show(Request $request, Letter $letter, LetterVersion $version): BinaryFileResponse
    {
        abort_unless($version->letter_id === $letter->id, 404);
        $artifact = $version->wordArtifact;
        abort_unless($artifact !== null && $artifact->letter_version_id === $version->id && $artifact->letter_id === $letter->id && $artifact->kind === 'docx', 404);
        $disk = Storage::disk($artifact->disk);
        abort_unless($disk->exists($artifact->path) && $disk->size($artifact->path) === $artifact->size && hash_file('sha256', $disk->path($artifact->path)) === $artifact->sha256, 410, 'Dokumen resmi tidak tersedia atau integritasnya berubah. Hubungi pengelola aplikasi.');
        $headers = ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];
        if ($letter->status === LetterStatusEnum::CANCELLED) {
            abort_unless(is_string($request->query('confirmation')) && hash_equals(app(ManageLetterLifecycle::class)->token($letter), $request->query('confirmation')), 422, 'Konfirmasikan bahwa dokumen berasal dari surat yang sudah dibatalkan.');
        }

        return response()->download($disk->path($artifact->path), 'surat-' . $letter->uuid . '-v' . $version->version_number . '.docx', $headers)->setPrivate();
    }
}
