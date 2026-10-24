import { TableActionButton } from '@/components/table-action-button';
import { Subscribe } from '@tanstack/react-table';
import { createDataTableColumnHelper } from '@/components/data-table';
import { Edit04Icon } from '@hugeicons/core-free-icons';
import ln from '@/routes/ln';

import { DeleteAction } from './delete-action';

export type LetterNumberColumns = {
    id: string;
    name: string;
    code: string;
    page_number: number;
    sequence_number: number;
};

const columnHelper = createDataTableColumnHelper<LetterNumberColumns>();

export const columns = columnHelper.columns([
    columnHelper.display({
        id: 'no',
        header: '#',
        meta: { className: 'w-16 text-center', skeletonClassName: 'mx-auto w-8' },
        cell: ({ row, table }) => (
            <Subscribe source={table.atoms.pagination}>
                {({ pageIndex, pageSize }) => (
                    <span className="text-muted-foreground tabular-nums">{pageIndex * pageSize + row.index + 1}</span>
                )}
            </Subscribe>
        ),
    }),
    columnHelper.accessor('name', {
        header: 'Nama Klasifikasi',
        cell: ({ cell }) => <span className="font-medium text-foreground">{cell.getValue()}</span>,
    }),
    columnHelper.accessor('code', {
        header: 'Kode Klasifikasi',
        cell: ({ cell }) => (
            <span className="inline-flex items-center rounded-md bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground tabular-nums ring-1 ring-border ring-inset">
                {cell.getValue()}
            </span>
        ),
    }),
    columnHelper.accessor('page_number', {
        header: 'Nomor Halaman',
        meta: { className: 'w-40 text-center', skeletonClassName: 'mx-auto w-8' },
        cell: ({ cell }) => <span className="tabular-nums">{cell.getValue()}</span>,
    }),
    columnHelper.accessor('sequence_number', {
        header: 'Nomor Urut',
        meta: { className: 'w-40 text-center', skeletonClassName: 'mx-auto w-8' },
        cell: ({ cell }) => <span className="tabular-nums">{cell.getValue()}</span>,
    }),
    columnHelper.display({
        id: 'actions',
        header: 'Aksi',
        meta: { className: 'w-15 text-center', skeletonClassName: 'mx-auto w-8' },
        cell: ({ row }) => (
            <div className="flex items-center gap-2">
                <TableActionButton
                    variant="warning"
                    href={ln.edit(row.original.id).url}
                    label={`Ubah ${row.original.name}`}
                    icon={Edit04Icon}
                    tooltip="Ubah"
                />
                <DeleteAction letterNumber={row.original} />
            </div>
        ),
    }),
]);
