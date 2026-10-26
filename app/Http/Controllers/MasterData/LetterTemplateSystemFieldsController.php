<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateLetterTemplateSystemFieldsRequest;
use App\Models\MasterData\LetterTemplate;
use App\Models\MasterData\LetterTemplateVersion;
use App\Services\LetterTemplate\InvalidateLetterTemplateEvidence;
use App\Services\LetterTemplate\LetterTemplateTestEvidence;
use App\Services\LetterTemplate\RecordLetterTemplateAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class LetterTemplateSystemFieldsController extends Controller
{
    public function __invoke(UpdateLetterTemplateSystemFieldsRequest $request, LetterTemplate $letterTemplate, LetterTemplateVersion $version, LetterTemplateTestEvidence $tests, InvalidateLetterTemplateEvidence $evidence, RecordLetterTemplateAudit $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $letterTemplate, $version, $tests, $evidence, $audit): void {
            $template = LetterTemplate::query()->whereKey($letterTemplate->id)->lockForUpdate()->firstOrFail();
            $version = $template->versions()->whereKey($version->id)->lockForUpdate()->firstOrFail();
            $tests->assertDraft($version);
            $format = $request->validated('letter_date_format');
            if ($version->letter_date_format === $format) {
                return;
            }
            $before = $audit->snapshot($template, $version);
            $version->letter_date_format = $format;
            $evidence->invalidate($version);
            $version->save();
            $audit->record($template, $version, $request->user(), 'configuration.updated', 'Format tanggal surat diperbarui; validasi dan pemeriksaan perlu diulang.', $before);
        }, 5);

        return redirect()->route('lt.edit', $letterTemplate->uuid);
    }
}
