import { router } from '@inertiajs/react';
import { Subscribe, type Row, type RowSelectionState, type Table, type Updater } from '@tanstack/react-table';
import { createContext, useContext } from 'react';
import {
    DataTable,
    DataTableFooter,
    createDataTableColumnHelper,
    useDataTable,
    type DataTableFeatures,
    type PaginationParams,
} from '@/components/data-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { useTableLoading } from '@/hooks/use-table-loading';
import { buildListingQuery } from '@/lib/listing-query';
import { show } from '@/routes/archives';
import type { ArchivePage, ArchiveRecordResource } from '@/types/archive';

const ActionsContext = createContext<{
    disabled: boolean;
    deletionDisabled: boolean;
    restorable: boolean;
    onRestore: (uuid: string) => void;
    onDelete?: (uuid: string) => void;
}>({
    disabled: false,
    deletionDisabled: false,
    restorable: true,
    onRestore: () => {},
});

function SelectionHeader({ table }: { table: Table<DataTableFeatures, ArchiveRecordResource> }) {
    const { disabled } = useContext(ActionsContext);
    return (
        <Subscribe source={table.atoms.rowSelection}>
            {(selection) => {
                const rows = table.getRowModel().rows.filter((row) => row.getCanSelect());
                const all = rows.length > 0 && rows.every((row) => selection[row.id]);
                const some = rows.some((row) => selection[row.id]);
                return (
                    <Checkbox
                        aria-label="Pilih semua item pada halaman ini"
                        checked={all}
                        indeterminate={some && !all}
                        disabled={disabled || rows.length === 0}
                        onCheckedChange={(checked) => table.toggleAllPageRowsSelected(checked)}
                    />
                );
            }}
        </Subscribe>
    );
}

function SelectionCell({ row }: { row: Row<DataTableFeatures, ArchiveRecordResource> }) {
    const { disabled } = useContext(ActionsContext);
    return (
        <Subscribe source={row.table.atoms.rowSelection} selector={(selection) => !!selection[row.id]}>
            {(selected) => (
                <Checkbox
                    checked={selected}
                    aria-label={`Pilih ${row.original.label}`}
                    disabled={disabled || !row.getCanSelect()}
                    onCheckedChange={(checked) => row.toggleSelected(checked)}
                />
            )}
        </Subscribe>
    );
}

function RestoreItemAction({ record }: { record: ArchiveRecordResource }) {
    const { disabled, deletionDisabled, restorable, onRestore, onDelete } = useContext(ActionsContext);
    return (
        <div className="flex items-center gap-2">
            {restorable && (
                <Button
                    variant="outline"
                    size="sm"
                    disabled={disabled || !record.supported}
                    aria-label={`Pulihkan ${record.label}`}
                    onClick={() => onRestore(record.uuid)}
                >
                    Pulihkan
                </Button>
            )}
            {onDelete && record.entity === 'reference_item' && (
                <Button
                    variant="destructive"
                    size="sm"
                    disabled={disabled || deletionDisabled}
                    aria-label={`Hapus permanen ${record.label}`}
                    onClick={() => onDelete(record.uuid)}
                >
                    Hapus Permanen…
                </Button>
            )}
        </div>
    );
}

const helper = createDataTableColumnHelper<ArchiveRecordResource>();
const columns = helper.columns([
    helper.display({
        id: 'selection',
        header: ({ table }) => <SelectionHeader table={table} />,
        cell: ({ row }) => <SelectionCell row={row} />,
        meta: { className: 'w-12' },
    }),
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
    helper.display({ id: 'restore', header: 'Aksi', cell: ({ row }) => <RestoreItemAction record={row.original} /> }),
]);

export function RecordTable({
    resources,
    archiveUuid,
    rowSelection,
    onRowSelectionChange,
    onRestore,
    onDelete,
    selectable,
    restorable,
    disabled,
    deletionDisabled = false,
}: {
    resources: ArchivePage<ArchiveRecordResource>;
    archiveUuid: string;
    rowSelection: RowSelectionState;
    onRowSelectionChange: (update: Updater<RowSelectionState>) => void;
    onRestore: (uuid: string) => void;
    onDelete?: (uuid: string) => void;
    selectable: boolean;
    restorable: boolean;
    disabled: boolean;
    deletionDisabled?: boolean;
}) {
    const loading = useTableLoading();
    const table = useDataTable({
        data: resources.data,
        columns,
        getRowId: (row) => row.uuid,
        manualPagination: true,
        rowCount: resources.meta.total,
        enableRowSelection: (row) => selectable && row.original.entity === 'reference_item',
        state: {
            pagination: { pageIndex: resources.meta.current_page - 1, pageSize: resources.meta.per_page },
            rowSelection,
            columnVisibility: { selection: selectable, restore: restorable || !!onDelete },
        },
        onRowSelectionChange,
    });
    function visit(params: PaginationParams) {
        router.get(show(archiveUuid).url, buildListingQuery(window.location.search, params), {
            preserveState: true,
            preserveScroll: true,
            showProgress: false,
            only: ['resources'],
        });
    }
    return (
        <ActionsContext value={{ disabled: disabled || loading, deletionDisabled, restorable, onRestore, onDelete }}>
            <DataTable
                table={table}
                isLoading={loading}
                emptyMessage="Tidak ada item tersisa di arsip ini."
                footer={
                    <DataTableFooter meta={resources.meta} onPaginationChange={visit} disabled={loading || disabled} />
                }
            />
        </ActionsContext>
    );
}
