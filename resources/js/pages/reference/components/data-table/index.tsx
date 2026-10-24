import { ResourceDataTable } from '@/components/resource-data-table';
import type { PaginationMeta } from '@/components/data-table';
import ref from '@/routes/ref';
import { columns } from './column';
import type { ReferenceGroupColumns } from './column';

const getRowId = (row: ReferenceGroupColumns) => row.id;

export function ReferenceGroupDataTable({ data, meta }: { data: ReferenceGroupColumns[]; meta: PaginationMeta }) {
    return <ResourceDataTable data={data} meta={meta} columns={columns} url={ref.index().url} getRowId={getRowId} />;
}
