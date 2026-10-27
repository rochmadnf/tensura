import { ResourceDataTable } from '@/components/resource-data-table';
import type { PaginationMeta } from '@/components/data-table';
import type { LetterResource } from '@/schemas/letter';
import { index } from '@/routes/ltr';
import { columns } from './column';
const getRowId = (letter: LetterResource) => letter.id;
export function LetterDataTable({ data, meta }: { data: LetterResource[]; meta: PaginationMeta }) {
    return (
        <ResourceDataTable
            data={data}
            meta={meta}
            columns={columns}
            getRowId={getRowId}
            url={index().url}
            emptyMessage="Belum ada surat yang sesuai pencarian atau filter."
        />
    );
}
