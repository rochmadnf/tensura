import { useTable, type ColumnDef, type RowData } from '@tanstack/react-table';

import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { features, DataTableFeatures } from './features';
import { DataTableFooter, type PaginationMeta } from './footer';

interface DataTableProps<TData extends RowData> {
    columns: ColumnDef<DataTableFeatures, TData>[];
    data: TData[];
    meta: PaginationMeta;
}

export function DataTable<TData extends RowData>({ columns, data, meta }: DataTableProps<TData>) {
    const table = useTable(
        {
            features,
            data,
            columns,
            manualPagination: true,
            state: {
                pagination: { pageIndex: meta.current_page - 1, pageSize: meta.per_page },
            },
        },
        (state) => ({ pagination: state.pagination }),
    );

    return (
        <div className="space-y-4">
            <div className="overflow-hidden rounded-md border">
                <Table>
                    <TableHeader>
                        {table.getHeaderGroups().map((headerGroup) => (
                            <TableRow key={headerGroup.id}>
                                {headerGroup.headers.map((header) => {
                                    return (
                                        <TableHead key={header.id}>
                                            {header.isPlaceholder ? null : <table.FlexRender header={header} />}
                                        </TableHead>
                                    );
                                })}
                            </TableRow>
                        ))}
                    </TableHeader>
                    <TableBody>
                        {table.getRowModel().rows?.length ? (
                            table.getRowModel().rows.map((row) => (
                                <TableRow key={row.id} data-state={row.getIsSelected() && 'selected'}>
                                    {row.getVisibleCells().map((cell) => (
                                        <TableCell key={cell.id}>
                                            <table.FlexRender cell={cell} />
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))
                        ) : (
                            <TableRow>
                                <TableCell colSpan={columns.length} className="h-24 text-center">
                                    Belum ada data yang tersedia.
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>

            <DataTableFooter meta={meta} />
        </div>
    );
}
