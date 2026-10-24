import { ResourceDataTable } from '@/components/resource-data-table';
import type { PaginationMeta } from '@/components/data-table';
import lt from '@/routes/lt';
import { columns } from './column';
import type { LetterTemplateResource } from '@/schemas/letter-template';

const getRowId = (row: LetterTemplateResource) => row.id;

export function LetterTemplateDataTable({ data, meta }: { data: LetterTemplateResource[]; meta: PaginationMeta }) {
    return <ResourceDataTable data={data} meta={meta} columns={columns} url={lt.index().url} getRowId={getRowId} />;
}
