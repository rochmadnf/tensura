<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportEmployeeRequest;
use App\Models\MasterData\JobTitle;
use App\Services\Employee\EmployeeImportTemplate;
use App\Services\Employee\ImportEmployees;
use App\Services\Employee\ReadEmployeeImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EmployeeImportController extends Controller
{
    public function create(): Response
    {
        return inertia('employee/import', [
            'page' => ['id' => 'a30c21b9-15ef-4dc0-97ef-524ead13be55'],
            'hasJobTitles' => JobTitle::query()->exists(),
            'importSettings' => EmployeeImportTemplate::settings(),
            'instructionSections' => EmployeeImportTemplate::instructionSections(),
        ]);
    }

    public function template(): BinaryFileResponse
    {
        if (!JobTitle::query()->exists()) {
            throw ValidationException::withMessages(['file' => EmployeeImportTemplate::JOB_TITLE_REQUIRED]);
        }

        return Excel::download(new EmployeeImportTemplate(), 'template-impor-pegawai.xlsx', ExcelFormat::XLSX);
    }

    public function store(ImportEmployeeRequest $request, ReadEmployeeImport $reader, ImportEmployees $importer): JsonResponse
    {
        $rows = $reader->read($request->file('file'));

        return response()->json($importer->import($rows));
    }
}
