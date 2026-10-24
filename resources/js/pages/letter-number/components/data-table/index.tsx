import { ResourceDataTable } from '@/components/resource-data-table';
import type { PaginationMeta } from '@/components/data-table';
import ln from '@/routes/ln';
import { columns } from './column';
import type { LetterNumberColumns } from './column';

const getRowId = (row: LetterNumberColumns) => row.id;

export function LetterNumberDataTable({ data, meta }: { data: LetterNumberColumns[]; meta: PaginationMeta }) {
    return <ResourceDataTable data={data} meta={meta} columns={columns} url={ln.index().url} getRowId={getRowId} />;
}
