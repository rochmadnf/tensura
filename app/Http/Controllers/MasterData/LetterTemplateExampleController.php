<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\MasterData\LetterTemplate;
use App\Models\MasterData\LetterTemplateVersion;
use App\Services\LetterTemplate\BuildLetterTemplateExample;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LetterTemplateExampleController extends Controller
{
    public function __invoke(Request $request, LetterTemplate $letterTemplate, LetterTemplateVersion $version, BuildLetterTemplateExample $example): BinaryFileResponse
    {
        $layout = $request->validate(['layout' => ['sometimes', 'required', 'in:horizontal,vertical']])['layout'] ?? 'horizontal';
        $path = DB::transaction(function () use ($letterTemplate, $version, $example, $layout): string {
            $template = LetterTemplate::query()->whereKey($letterTemplate->id)->sharedLock()->firstOrFail();
            $version = $template->versions()->whereKey($version->id)->sharedLock()->firstOrFail();

            return $example->build($version, $layout);
        });

        return response()->download($path, 'contoh-templat-v' . $version->version_number . '.docx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ])->deleteFileAfterSend(true);
    }
}
