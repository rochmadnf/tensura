<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLetterTemplateDocxRequest;
use App\Models\MasterData\LetterTemplate;
use App\Models\MasterData\LetterTemplateVersion;
use App\Services\LetterTemplate\StoreLetterTemplateDocx;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LetterTemplateSourceController extends Controller
{
    public function store(StoreLetterTemplateDocxRequest $request, LetterTemplate $letterTemplate, LetterTemplateVersion $version, StoreLetterTemplateDocx $source): RedirectResponse
    {
        $source->store($letterTemplate, $version, $request->validated('docx'), $request->user());

        return redirect()->route('lt.edit', $letterTemplate->uuid);
    }

    public function show(LetterTemplate $letterTemplate, LetterTemplateVersion $version): StreamedResponse
    {
        $file = DB::transaction(function () use ($letterTemplate, $version): array {
            $version = $letterTemplate->versions()->whereKey($version->id)->sharedLock()->firstOrFail();
            abort_if($version->source_path === null, 404);
            $disk = Storage::disk($version->source_disk);
            abort_unless($disk->exists($version->source_path), 404);
            $stream = $disk->readStream($version->source_path);
            abort_if($stream === false, 404);

            return ['stream' => $stream, 'name' => $version->source_original_name];
        });

        return response()->streamDownload(function () use ($file): void {
            try {
                fpassthru($file['stream']);
            } finally {
                fclose($file['stream']);
            }
        }, $file['name'], ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
