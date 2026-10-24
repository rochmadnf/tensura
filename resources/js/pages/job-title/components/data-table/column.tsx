import { TableActionButton } from '@/components/table-action-button';
import { Subscribe } from '@tanstack/react-table';
import { createDataTableColumnHelper } from '@/components/data-table';
import { Edit04Icon } from '@hugeicons/core-free-icons';
import jt from '@/routes/jt';

import { DeleteAction } from './delete-action';

export type JobTitleColumns = {
    id: string;
    name: string;
    group: { id: number | null; label: string | null };
};

const columnHelper = createDataTableColumnHelper<JobTitleColumns>();

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
        header: 'Nama Jabatan',
        cell: ({ cell }) => <span className="font-medium text-foreground">{cell.getValue()}</span>,
    }),
    columnHelper.accessor('group', {
        header: 'Kelompok',
        cell: ({ cell }) => (
            <span className="inline-flex items-center rounded-md bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground tabular-nums ring-1 ring-border ring-inset">
                {cell.getValue().label ?? '—'}
            </span>
        ),
    }),
    columnHelper.display({
        id: 'actions',
        header: 'Aksi',
        meta: { className: 'w-15 text-center', skeletonClassName: 'mx-auto w-8' },
        cell: ({ row }) => (
            <div className="flex items-center gap-2">
                <TableActionButton
                    variant="warning"
                    href={jt.edit(row.original.id).url}
                    label={`Ubah ${row.original.name}`}
                    icon={Edit04Icon}
                    tooltip="Ubah"
                />
                <DeleteAction jobTitle={row.original} />
            </div>
        ),
    }),
]);
