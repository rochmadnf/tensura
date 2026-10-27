<?php

namespace App\Services\Employee;

use App\Models\MasterData\Employee;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

class EmployeeExport extends StringValueBinder implements FromQuery, WithCustomValueBinder, WithEvents, WithHeadings, WithMapping
{
    /** @param Builder<Employee> $employees */
    public function __construct(private Builder $employees) {}

    /** @return Builder<Employee> */
    public function query(): Builder
    {
        return $this->employees;
    }

    /** @return list<string> */
    public function headings(): array
    {
        return array_values(EmployeeImportTemplate::columns());
    }

    /** @param Employee $row
     * @return list<string|null>
     */
    public function map(mixed $row): array
    {
        $values = [];
        foreach (array_keys(EmployeeImportTemplate::columns()) as $field) {
            $values[] = match ($field) {
                'job_title_id' => $row->jobTitle?->name,
                'gender' => collect(Employee::genderOptions())->firstWhere('id', $row->gender)['label'] ?? null,
                'contract_status', 'status', 'str_status' => $row->$field?->label(),
                'join_date', 'str_published_date', 'sip_published_date', 'sip_expired_date' => $row->$field?->format('Y-m-d'),
                default => $row->$field,
            };
        }

        return $values;
    }

    /** @return array<class-string, callable> */
    public function registerEvents(): array
    {
        return (new EmployeeImportTemplate())->registerEvents();
    }
}
