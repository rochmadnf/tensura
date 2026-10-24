import { ResourceDataTable } from '@/components/resource-data-table';
import type { PaginationMeta } from '@/components/data-table';
import jt from '@/routes/jt';
import { columns } from './column';
import type { JobTitleColumns } from './column';

const getRowId = (row: JobTitleColumns) => row.id;

export function JobTitleDataTable({ data, meta }: { data: JobTitleColumns[]; meta: PaginationMeta }) {
    return <ResourceDataTable data={data} meta={meta} columns={columns} url={jt.index().url} getRowId={getRowId} />;
}
