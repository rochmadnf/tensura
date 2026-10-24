import { Link, router, usePage } from '@inertiajs/react';
import {
    DataTable,
    DataTableFooter,
    createDataTableColumnHelper,
    useDataTable,
    type PaginationParams,
} from '@/components/data-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTableLoading } from '@/hooks/use-table-loading';
import { buildListingQuery } from '@/lib/listing-query';
import { index, show } from '@/routes/archives';
import { index as referenceArchiveIndex } from '@/routes/ref/archive';
import type { ArchiveContext, ArchivePage, ArchiveResource } from '@/types/archive';
import { archivedDate, moduleLabel, parentStatusLabels } from './presentation';

const helper = createDataTableColumnHelper<ArchiveResource>();
const columns = helper.columns([
    helper.accessor('label', {
        header: 'Data',
        cell: ({ row }) => <ArchiveLink archive={row.original} />,
    }),
    helper.accessor('module', { header: 'Modul', cell: ({ cell }) => moduleLabel(cell.getValue()) }),
    helper.accessor('kind', { header: 'Jenis', cell: ({ cell }) => (cell.getValue() === 'package' ? 'Grup' : 'Item') }),
    helper.display({
        id: 'parent',
        header: 'Induk',
        cell: ({ row }) => (
            <span className="block max-w-48 break-words whitespace-normal">{row.original.parent?.label ?? '—'}</span>
        ),
    }),
    helper.accessor('items_count', {
        header: 'Item tersisa',
        meta: { className: 'text-right tabular-nums' },
    }),
    helper.accessor('parent_status', {
        header: 'Status induk',
        cell: ({ cell }) => <Badge variant="outline">{parentStatusLabels[cell.getValue()]}</Badge>,
    }),
    helper.accessor('archived_at', {
        header: 'Diarsipkan pada (WITA)',
        cell: ({ cell }) => archivedDate(cell.getValue()),
    }),
    helper.accessor('archived_by_name', { header: 'Diarsipkan oleh', cell: ({ cell }) => cell.getValue() ?? '—' }),
    helper.display({
        id: 'actions',
        header: 'Aksi',
        cell: ({ row }) => <ArchiveLink archive={row.original} action />,
    }),
]);

function ArchiveLink({ archive, action = false }: { archive: ArchiveResource; action?: boolean }) {
    const { context } = usePage<{ context: ArchiveContext }>().props;
    const href = show(archive.uuid, { query: { context } });
    if (action) {
        return (
            <Button
                variant="outline"
                size="sm"
                nativeButton={false}
                render={<Link href={href} />}
                aria-label={`Detail ${archive.label}`}
            >
                Detail
            </Button>
        );
    }
    return (
        <Link
            href={href}
            className="block max-w-64 min-w-36 font-medium break-words whitespace-normal text-foreground underline-offset-4 hover:underline"
        >
            {archive.label}
        </Link>
    );
}

export function ArchiveTable({
    resources,
    context,
}: {
    resources: ArchivePage<ArchiveResource>;
    context: ArchiveContext;
}) {
    const loading = useTableLoading();
    const table = useDataTable({
        data: resources.data,
        columns,
        getRowId: (row) => row.uuid,
        manualPagination: true,
        rowCount: resources.meta.total,
        state: {
            pagination: { pageIndex: resources.meta.current_page - 1, pageSize: resources.meta.per_page },
            columnVisibility: { module: context === null },
        },
    });
    function visit(params: PaginationParams) {
        router.get(
            context === 'reference' ? referenceArchiveIndex().url : index().url,
            buildListingQuery(window.location.search, params),
            { preserveState: true, preserveScroll: true, only: ['resources', 'filters'] },
        );
    }
    return (
        <DataTable
            table={table}
            isLoading={loading}
            emptyMessage="Tidak ada arsip yang sesuai."
            footer={<DataTableFooter meta={resources.meta} onPaginationChange={visit} disabled={loading} />}
        />
    );
}
