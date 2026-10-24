import { Subscribe } from '@tanstack/react-table';
import { createDataTableColumnHelper } from '@/components/data-table';
import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { Edit04Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import ref from '@/routes/ref';

import { DeleteAction } from './delete-action';

import type { ReferenceGroupResource } from '@/schemas/reference';

export type ReferenceGroupColumns = ReferenceGroupResource;

const columnHelper = createDataTableColumnHelper<ReferenceGroupColumns>();

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
        header: 'Nama Grup',
        cell: ({ cell }) => <span className="font-medium text-foreground">{cell.getValue()}</span>,
    }),
    columnHelper.accessor('description', {
        header: 'Deskripsi',
        cell: ({ cell }) => <span className="text-muted-foreground">{cell.getValue() ?? '—'}</span>,
    }),
    columnHelper.accessor('items_count', {
        header: 'Jumlah Item',
        cell: ({ cell }) => <span className="tabular-nums">{cell.getValue()}</span>,
    }),
    columnHelper.display({
        id: 'actions',
        header: 'Aksi',
        meta: { className: 'w-15 text-center', skeletonClassName: 'mx-auto w-8' },
        cell: ({ row }) => (
            <div className="flex items-center gap-2">
                <Tooltip>
                    <Button
                        variant="warning"
                        size="icon-sm"
                        nativeButton={false}
                        render={<TooltipTrigger render={<Link href={ref.edit(row.original.id).url} />} />}
                        aria-label={`Ubah/Kelola ${row.original.name}`}
                    >
                        <HugeiconsIcon icon={Edit04Icon} strokeWidth={2} />
                    </Button>
                    <TooltipContent>Ubah/Kelola</TooltipContent>
                </Tooltip>
                <DeleteAction referenceGroup={row.original} />
            </div>
        ),
    }),
]);
