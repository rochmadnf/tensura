<?php

namespace App\Http\Controllers;

use App\Http\Requests\WorkCalendarRequest;
use App\Services\WorkCalendar\CountWorkCalendarDays;
use App\Services\WorkCalendar\ManageWorkCalendar;
use Illuminate\Http\JsonResponse;

class WorkCalendarController extends Controller
{
    public function data(WorkCalendarRequest $request, ManageWorkCalendar $calendar): JsonResponse
    {
        return response()->json(['data' => $calendar->data($request->validated())]);
    }

    public function store(WorkCalendarRequest $request, ManageWorkCalendar $calendar): JsonResponse
    {
        return response()->json(['data' => $calendar->prepare($request->validated(), $request->user())], 201);
    }

    public function update(WorkCalendarRequest $request, int $year, ManageWorkCalendar $calendar): JsonResponse
    {
        return response()->json(['data' => $calendar->update($year, $request->validated(), $request->user())]);
    }

    public function confirm(WorkCalendarRequest $request, int $year, ManageWorkCalendar $calendar): JsonResponse
    {
        return response()->json(['data' => $calendar->update($year, $request->validated(), $request->user(), true)]);
    }

    public function storeEvent(WorkCalendarRequest $request, ManageWorkCalendar $calendar): JsonResponse
    {
        return response()->json(['data' => $calendar->event($request->validated(), $request->user())], 201);
    }

    public function updateEvent(WorkCalendarRequest $request, string $event, ManageWorkCalendar $calendar): JsonResponse
    {
        return response()->json(['data' => $calendar->event($request->validated(), $request->user(), $event)]);
    }

    public function destroyEvent(WorkCalendarRequest $request, string $event, ManageWorkCalendar $calendar): JsonResponse
    {
        return response()->json(['data' => $calendar->event($request->validated(), $request->user(), $event, true)]);
    }

    public function count(WorkCalendarRequest $request, CountWorkCalendarDays $counter): JsonResponse
    {
        $input = $request->validated();

        return response()->json(['data' => $counter->count($input['start_date'], $input['end_date'], $input['basis'])]);
    }
}
