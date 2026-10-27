<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExportEmployeeRequest;
use App\Services\Employee\EmployeeExport;
use App\Services\Employee\EmployeeListing;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EmployeeExportController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(ExportEmployeeRequest $request, EmployeeListing $listing): BinaryFileResponse
    {
        $selection = $listing->resolve($request->validated('scope', 'all') === 'filtered' ? $request : null);

        return Excel::download(new EmployeeExport($selection['query']), 'data-pegawai.xlsx');
    }
}
