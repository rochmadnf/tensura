<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ArchiveChangedException extends RuntimeException implements ShouldntReport
{
    public function __construct()
    {
        parent::__construct('Data arsip atau tujuan telah berubah. Tinjau ulang lalu konfirmasi kembali.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'code' => 'archive_changed'], 409);
    }
}
