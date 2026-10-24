import { ResourceDataTable } from '@/components/resource-data-table';
import type { PaginationMeta } from '@/components/data-table';
import empl from '@/routes/empl';
import { columns } from './column';
import type { EmployeeResource } from '@/schemas/employee';

const getRowId = (row: EmployeeResource) => row.id;

export function EmployeeDataTable({ data, meta }: { data: EmployeeResource[]; meta: PaginationMeta }) {
    return <ResourceDataTable data={data} meta={meta} columns={columns} url={empl.index().url} getRowId={getRowId} />;
}
