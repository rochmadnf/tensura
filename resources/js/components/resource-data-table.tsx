import type { RowData, TableOptions } from '@tanstack/react-table';
import type { ReactNode } from 'react';
import { router } from '@inertiajs/react';
import {
    DataTable,
    DataTableFooter,
    useDataTable,
    type DataTableFeatures,
    type PaginationMeta,
    type PaginationParams,
} from '@/components/data-table';
import { useTableLoading } from '@/hooks/use-table-loading';
import { buildListingQuery } from '@/lib/listing-query';

interface ResourceDataTableProps<TData extends RowData> {
    data: TData[];
    columns: TableOptions<DataTableFeatures, TData>['columns'];
    meta: PaginationMeta;
    url: string;
    getRowId: (row: TData) => string;
    columnVisibility?: Record<string, boolean>;
    disabled?: boolean;
    emptyMessage?: ReactNode;
}

export function ResourceDataTable<TData extends RowData>({
    data,
    columns,
    meta,
    url,
    getRowId,
    columnVisibility,
    disabled = false,
    emptyMessage,
}: ResourceDataTableProps<TData>) {
    const isLoading = useTableLoading('resources');
    const table = useDataTable({
        data,
        columns,
        getRowId,
        manualPagination: true,
        rowCount: meta.total,
        state: {
            pagination: { pageIndex: meta.current_page - 1, pageSize: meta.per_page },
            ...(columnVisibility ? { columnVisibility } : {}),
        },
    });

    function visit(params: PaginationParams) {
        router.get(url, buildListingQuery(window.location.search, params), {
            preserveScroll: true,
            preserveState: true,
            showProgress: false,
            only: ['resources', 'filters'],
        });
    }

    return (
        <DataTable
            table={table}
            isLoading={isLoading}
            emptyMessage={emptyMessage}
            footer={<DataTableFooter meta={meta} onPaginationChange={visit} disabled={disabled || isLoading} />}
        />
    );
}
