<?php

namespace App\Services\WorkCalendar;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class CalendarFailure extends RuntimeException implements ShouldntReport
{
    public function render(Request $request): JsonResponse
    {
        return response()->json(['code' => $this->reason, 'message' => $this->getMessage(), 'errors' => (object) $this->errors], $this->status);
    }

    public function __construct(public string $reason, string $message, public int $status = 422, public array $errors = [])
    {
        parent::__construct($message);
    }
}
