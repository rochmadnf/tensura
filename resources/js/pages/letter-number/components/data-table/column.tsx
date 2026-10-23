import { createColumnHelper } from '@tanstack/react-table';
import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { Edit04Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import ln from '@/routes/ln';

import { type DataTableFeatures } from './features';
import { DeleteAction } from './delete-action';

export type LetterNumberColumns = {
    id: string;
    name: string;
    code: string;
    page_number: number;
    sequence_number: number;
};

const columnHelper = createColumnHelper<DataTableFeatures, LetterNumberColumns>();

export const columns = columnHelper.columns([
    columnHelper.display({
        id: 'no',
        header: '#',
        cell: ({ row, table }) => {
            const { pageIndex, pageSize } = table.store.state.pagination;

            return <span className="text-muted-foreground tabular-nums">{pageIndex * pageSize + row.index + 1}</span>;
        },
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
        cell: ({ cell }) => <span className="tabular-nums">{cell.getValue()}</span>,
    }),
    columnHelper.accessor('sequence_number', {
        header: 'Nomor Urut',
        cell: ({ cell }) => <span className="tabular-nums">{cell.getValue()}</span>,
    }),
    columnHelper.display({
        id: 'actions',
        header: 'Aksi',
        cell: ({ row }) => (
            <div className="flex items-center gap-2">
                <Tooltip>
                    <Button
                        variant="warning"
                        size="icon-sm"
                        nativeButton={false}
                        render={<TooltipTrigger render={<Link href={ln.edit(row.original.id).url} />} />}
                        aria-label={`Ubah ${row.original.name}`}
                    >
                        <HugeiconsIcon icon={Edit04Icon} strokeWidth={2} />
                    </Button>
                    <TooltipContent>Ubah</TooltipContent>
                </Tooltip>
                <DeleteAction letterNumber={row.original} />
            </div>
        ),
    }),
]);
