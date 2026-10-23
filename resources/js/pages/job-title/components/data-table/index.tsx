import {
    DataTable,
    DataTableFooter,
    useDataTable,
    type PaginationMeta,
    type PaginationParams,
} from '@/components/data-table';
import { useTableLoading } from '@/hooks/use-table-loading';
import jt from '@/routes/jt';
import { router } from '@inertiajs/react';
import { columns, type JobTitleColumns } from './column';

interface JobTitleDataTableProps {
    data: JobTitleColumns[];
    meta: PaginationMeta;
}

export function JobTitleDataTable({ data, meta }: JobTitleDataTableProps) {
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
        const query = Object.fromEntries(new URLSearchParams(window.location.search));

        router.get(
            jt.index().url,
            { ...query, ...params },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['resources'],
            },
        );
    }

    return (
        <DataTable
            table={table}
            isLoading={isLoading}
            footer={<DataTableFooter meta={meta} onPaginationChange={visit} disabled={isLoading} />}
        />
    );
}
