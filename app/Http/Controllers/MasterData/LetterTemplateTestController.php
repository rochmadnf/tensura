<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmLetterTemplateTestRequest;
use App\Http\Requests\DownloadLetterTemplateTestRequest;
use App\Http\Requests\GenerateLetterTemplateTestRequest;
use App\Models\MasterData\LetterTemplate;
use App\Models\MasterData\LetterTemplateVersion;
use App\Services\LetterTemplate\GenerateLetterTemplateTest;
use App\Services\LetterTemplate\LetterTemplateTestEvidence;
use App\Services\LetterTemplate\RecordLetterTemplateAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LetterTemplateTestController extends Controller
{
    public function store(GenerateLetterTemplateTestRequest $request, LetterTemplate $letterTemplate, LetterTemplateVersion $version, GenerateLetterTemplateTest $generator): RedirectResponse
    {
        $generator->generate($letterTemplate, $version, $request->safe()->only(['validation_token', 'values', 'signers']), $request->user());

        return redirect()->route('lt.edit', $letterTemplate->uuid);
    }

    public function show(DownloadLetterTemplateTestRequest $request, LetterTemplate $letterTemplate, LetterTemplateVersion $version, LetterTemplateTestEvidence $evidence): StreamedResponse
    {
        $file = DB::transaction(function () use ($request, $letterTemplate, $version, $evidence): array {
            $version = $letterTemplate->versions()->whereKey($version->id)->sharedLock()->firstOrFail();
            $evidence->assertCurrent($version, $request->validated('test_token'));
            $stream = Storage::disk($version->test_disk)->readStream($version->test_path);
            abort_if($stream === false, 404);

            return ['stream' => $stream, 'number' => $version->version_number];
        });

        return response()->streamDownload(function () use ($file): void {
            try {
                fpassthru($file['stream']);
            } finally {
                fclose($file['stream']);
            }
        }, 'hasil-uji-templat-v' . $file['number'] . '.docx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function confirm(ConfirmLetterTemplateTestRequest $request, LetterTemplate $letterTemplate, LetterTemplateVersion $version, LetterTemplateTestEvidence $evidence, RecordLetterTemplateAudit $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $letterTemplate, $version, $evidence, $audit): void {
            $letterTemplate = LetterTemplate::query()->whereKey($letterTemplate->id)->lockForUpdate()->firstOrFail();
            $version = $letterTemplate->versions()->whereKey($version->id)->lockForUpdate()->firstOrFail();
            $evidence->assertDraft($version);
            $evidence->assertCurrent($version, $request->validated('test_token'));
            if ($version->visually_confirmed_at !== null) {
                return;
            }
            $before = $audit->snapshot($letterTemplate, $version);
            $actor = $request->user();
            $version->fill(['visually_confirmed_at' => now(), 'visually_confirmed_by' => $actor->id, 'visually_confirmed_by_uuid' => $actor->uuid, 'visually_confirmed_by_name' => $actor->name])->save();
            $audit->record($letterTemplate, $version, $actor, 'test.confirmed', 'Admin menyatakan DOCX hasil uji terbaru telah dibuka dan diperiksa secara visual.', $before);
        }, 5);

        return redirect()->route('lt.edit', $letterTemplate->uuid);
    }
}
