<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\MasterData\LetterTemplate;
use App\Services\LetterTemplate\LetterTemplateAuditTimeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LetterTemplateAuditController extends Controller
{
    public function __invoke(Request $request, LetterTemplate $letterTemplate, LetterTemplateAuditTimeline $timeline): JsonResponse
    {
        $data = $request->validate(['cursor' => ['nullable', 'string', 'max:2000']]);

        return response()->json($timeline->page($letterTemplate, $data['cursor'] ?? null))->header('Cache-Control', 'private, no-store');
    }
}
