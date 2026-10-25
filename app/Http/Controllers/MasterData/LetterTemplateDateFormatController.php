<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\PreviewLetterTemplateDateFormatRequest;
use App\Services\LetterTemplate\FormatEmployeeDate;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class LetterTemplateDateFormatController extends Controller
{
    public function __invoke(PreviewLetterTemplateDateFormatRequest $request, FormatEmployeeDate $dates): JsonResponse
    {
        return response()->json(['example' => $dates->format(CarbonImmutable::create(2026, 10, 25), $request->validated('date_format')), 'locale' => config('app.locale')]);
    }
}
