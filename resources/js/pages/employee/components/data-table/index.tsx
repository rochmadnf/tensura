import {
    DataTable,
    DataTableFooter,
    useDataTable,
    type PaginationMeta,
    type PaginationParams,
} from '@/components/data-table';
import { useTableLoading } from '@/hooks/use-table-loading';
import empl from '@/routes/empl';
import { router } from '@inertiajs/react';
import { buildListingQuery } from '@/lib/listing-query';
import { columns } from './column';
import type { EmployeeResource } from '@/schemas/employee';

interface EmployeeDataTableProps {
    data: EmployeeResource[];
    meta: PaginationMeta;
}

export function EmployeeDataTable({ data, meta }: EmployeeDataTableProps) {
    const isLoading = useTableLoading('resources');
    const table = useDataTable({
        data,
        columns,
        getRowId: (row) => row.id,
        manualPagination: true,
        rowCount: meta.total,
        state: {
            pagination: { pageIndex: meta.current_page - 1, pageSize: meta.per_page },
        },
    });

    function visit(params: PaginationParams) {
        const query = buildListingQuery(window.location.search, params);

        router.get(empl.index().url, query, {
            preserveScroll: true,
            preserveState: true,
            only: ['resources', 'filters'],
        });
    }

    return (
        <DataTable
            table={table}
            isLoading={isLoading}
            footer={<DataTableFooter meta={meta} onPaginationChange={visit} disabled={isLoading} />}
        />
    );
}
