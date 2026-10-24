import { Subscribe } from '@tanstack/react-table';
import { createDataTableColumnHelper } from '@/components/data-table';
import { TableActionButton } from '@/components/table-action-button';
import { Badge } from '@/components/ui/badge';
import { Edit04Icon } from '@hugeicons/core-free-icons';
import lt from '@/routes/lt';
import type { LetterTemplateResource } from '@/schemas/letter-template';

const columnHelper = createDataTableColumnHelper<LetterTemplateResource>();

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
        cell: ({ cell }) => <span className="font-medium">{cell.getValue()}</span>,
    }),
    columnHelper.accessor('classification', {
        header: 'Klasifikasi',
        cell: ({ cell }) => {
            const classification = cell.getValue();
            return classification ? `${classification.code} — ${classification.name}` : '—';
        },
    }),
    columnHelper.accessor('active_version', { header: 'Versi Aktif', cell: ({ cell }) => cell.getValue() ?? '—' }),
    columnHelper.accessor('is_active', {
        header: 'Status',
        cell: ({ cell }) => <Badge variant="secondary">{cell.getValue() ? 'Aktif' : 'Nonaktif'}</Badge>,
    }),
    columnHelper.accessor('has_draft', {
        header: 'Draft',
        cell: ({ cell }) => (cell.getValue() ? <Badge variant="outline">Ada Draft</Badge> : '—'),
    }),
    columnHelper.display({
        id: 'actions',
        header: 'Aksi',
        meta: { className: 'w-16 text-center', skeletonClassName: 'mx-auto w-8' },
        cell: ({ row }) => (
            <TableActionButton
                variant="warning"
                href={lt.edit(row.original.id).url}
                label={`Kelola ${row.original.name}`}
                icon={Edit04Icon}
                tooltip="Kelola"
            />
        ),
    }),
]);
