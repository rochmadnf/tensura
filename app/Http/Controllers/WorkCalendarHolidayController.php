<?php

namespace App\Http\Controllers;

use App\Http\Requests\WorkCalendarRequest;
use App\Services\WorkCalendar\ImportIndonesianHolidays;
use Illuminate\Http\JsonResponse;

class WorkCalendarHolidayController extends Controller
{
    public function preview(WorkCalendarRequest $request, int $year, ImportIndonesianHolidays $holidays): JsonResponse
    {
        return response()->json(['data' => $holidays->preview($year, $request->validated(), $request->user())]);
    }

    public function apply(WorkCalendarRequest $request, int $year, ImportIndonesianHolidays $holidays): JsonResponse
    {
        return response()->json(['data' => $holidays->apply($year, $request->validated(), $request->user())]);
    }
}
