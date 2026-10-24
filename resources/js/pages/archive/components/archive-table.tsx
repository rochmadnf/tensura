import { Link, router, usePage } from '@inertiajs/react';
import { createContext, useContext, useRef } from 'react';
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
import { useArchiveDelete } from './use-archive-delete';
import { DeleteArchiveDialog } from './delete-dialog';

const DeleteContext = createContext<{ busy: boolean; begin: (archive: ArchiveResource) => void }>({
    busy: false,
    begin: () => {},
});

function DeletePackageAction({ archive }: { archive: ArchiveResource }) {
    const deletion = useContext(DeleteContext);
    return (
        <Button
            variant="destructive"
            size="sm"
            disabled={deletion.busy}
            aria-label={`Hapus permanen ${archive.label}`}
            onClick={() => deletion.begin(archive)}
        >
            Hapus Permanen
        </Button>
    );
}

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
            <span className="block max-w-48 wrap-break-word whitespace-normal">
                {row.original.parent?.label ?? '—'}
            </span>
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
        cell: ({ row }) => (
            <div className="flex items-center gap-2">
                <ArchiveLink archive={row.original} action />
                {row.original.module === 'reference' && <ArchiveLink archive={row.original} restore />}
                <DeletePackageAction archive={row.original} />
            </div>
        ),
    }),
]);

function ArchiveLink({
    archive,
    action = false,
    restore: restoreAction = false,
}: {
    archive: ArchiveResource;
    action?: boolean;
    restore?: boolean;
}) {
    const { context } = usePage<{ context: ArchiveContext }>().props;
    const href = show(archive.uuid, { query: { context } });
    if (action || restoreAction) {
        return (
            <Button
                variant="outline"
                size="sm"
                nativeButton={false}
                render={<Link href={restoreAction ? `${href.url}#restore` : href} />}
                aria-label={`${restoreAction ? 'Pulihkan' : 'Detail'} ${archive.label}`}
            >
                {restoreAction ? 'Pulihkan' : 'Detail'}
            </Button>
        );
    }
    return (
        <Link
            href={href}
            className="block max-w-64 min-w-36 font-medium wrap-break-word whitespace-normal text-foreground underline-offset-4 hover:underline"
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
    const operationLock = useRef(false);
    const deletion = useArchiveDelete({ context, location: 'index', operationLock });
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
        <DeleteContext
            value={{ busy: deletion.busy || loading, begin: (archive) => deletion.begin(archive, 'package') }}
        >
            <DataTable
                table={table}
                isLoading={loading}
                emptyMessage="Tidak ada arsip yang sesuai."
                footer={
                    <DataTableFooter
                        meta={resources.meta}
                        onPaginationChange={visit}
                        disabled={loading || deletion.busy}
                    />
                }
            />
            <DeleteArchiveDialog flow={deletion} />
        </DeleteContext>
    );
}
