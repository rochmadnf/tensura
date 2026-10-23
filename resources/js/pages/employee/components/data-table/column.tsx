import { Subscribe } from '@tanstack/react-table';
import { createDataTableColumnHelper } from '@/components/data-table';
import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { Edit04Icon, InfoIcon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import empl from '@/routes/empl';
import type { EmployeeResource } from '@/schemas/employee';
import { DeleteAction } from './delete-action';

const columnHelper = createDataTableColumnHelper<EmployeeResource>();
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
        header: 'Nama',
        cell: ({ cell }) => <span className="font-medium text-foreground">{cell.getValue().full}</span>,
    }),
    columnHelper.accessor('nip', {
        header: 'NIP',
        cell: ({ cell }) => <span className="tabular-nums">{cell.getValue() || '—'}</span>,
    }),
    columnHelper.accessor('job_title', { header: 'Jabatan', cell: ({ cell }) => cell.getValue().name || '—' }),
    columnHelper.accessor('contract_status', {
        header: 'Status Kontrak',
        cell: ({ cell }) => cell.getValue().label || '—',
    }),
    columnHelper.accessor('status', {
        header: 'Status Pegawai',
        cell: ({ cell }) => (
            <span className="inline-flex rounded-md bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground ring-1 ring-border ring-inset">
                {cell.getValue().label || '—'}
            </span>
        ),
    }),
    columnHelper.display({
        id: 'actions',
        header: 'Aksi',
        meta: { className: 'w-28 text-center', skeletonClassName: 'mx-auto w-16' },
        cell: ({ row }) => (
            <div className="flex items-center gap-2">
                <Tooltip>
                    <Button
                        variant="info"
                        size="icon-sm"
                        nativeButton={false}
                        render={<TooltipTrigger render={<Link href={empl.show(row.original.id).url} />} />}
                        aria-label={`Detail ${row.original.name.full}`}
                    >
                        <HugeiconsIcon icon={InfoIcon} strokeWidth={2} />
                    </Button>
                    <TooltipContent>Detail</TooltipContent>
                </Tooltip>
                <Tooltip>
                    <Button
                        variant="warning"
                        size="icon-sm"
                        nativeButton={false}
                        render={<TooltipTrigger render={<Link href={empl.edit(row.original.id).url} />} />}
                        aria-label={`Ubah ${row.original.name.full}`}
                    >
                        <HugeiconsIcon icon={Edit04Icon} strokeWidth={2} />
                    </Button>
                    <TooltipContent>Ubah</TooltipContent>
                </Tooltip>
                <DeleteAction employee={row.original} />
            </div>
        ),
    }),
]);
