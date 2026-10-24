import { router } from '@inertiajs/react';
import {
    DataTable,
    DataTableFooter,
    createDataTableColumnHelper,
    useDataTable,
    type PaginationParams,
} from '@/components/data-table';
import { Badge } from '@/components/ui/badge';
import { useTableLoading } from '@/hooks/use-table-loading';
import { buildListingQuery } from '@/lib/listing-query';
import { show } from '@/routes/archives';
import type { ArchivePage, ArchiveRecordResource } from '@/types/archive';

const helper = createDataTableColumnHelper<ArchiveRecordResource>();
const columns = helper.columns([
    helper.accessor('label', {
        header: 'Konten',
        cell: ({ cell }) => <span className="font-medium break-words whitespace-normal">{cell.getValue()}</span>,
    }),
    helper.accessor('sort_order', {
        header: 'Urutan asal',
        meta: { className: 'text-right tabular-nums' },
        cell: ({ cell }) => cell.getValue() ?? '—',
    }),
    helper.accessor('source_uuid', {
        header: 'UUID asal',
        cell: ({ cell }) => <span className="text-xs text-muted-foreground">{cell.getValue() ?? '—'}</span>,
    }),
    helper.accessor('supported', {
        header: 'Snapshot',
        cell: ({ row }) => (
            <div className="flex flex-col items-start gap-1">
                <Badge variant={row.original.supported ? 'outline' : 'destructive'}>
                    {row.original.supported ? `Versi ${row.original.schema_version}` : 'Belum didukung'}
                </Badge>
                {row.original.unsupported_reason && (
                    <span className="max-w-64 text-xs whitespace-normal text-muted-foreground">
                        {row.original.unsupported_reason}
                    </span>
                )}
            </div>
        ),
    }),
]);

export function RecordTable({
    resources,
    archiveUuid,
}: {
    resources: ArchivePage<ArchiveRecordResource>;
    archiveUuid: string;
}) {
    const loading = useTableLoading();
    const table = useDataTable({
        data: resources.data,
        columns,
        getRowId: (row) => row.uuid,
        manualPagination: true,
        rowCount: resources.meta.total,
        state: { pagination: { pageIndex: resources.meta.current_page - 1, pageSize: resources.meta.per_page } },
    });
    function visit(params: PaginationParams) {
        router.get(show(archiveUuid).url, buildListingQuery(window.location.search, params), {
            preserveState: true,
            preserveScroll: true,
            only: ['resources'],
        });
    }
    return (
        <DataTable
            table={table}
            isLoading={loading}
            emptyMessage="Tidak ada item tersisa di arsip ini."
            footer={<DataTableFooter meta={resources.meta} onPaginationChange={visit} disabled={loading} />}
        />
    );
}
