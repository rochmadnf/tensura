import { useTable, type ColumnDef, type RowData } from '@tanstack/react-table';

import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { cn } from '@/lib/utils';
import { features, DataTableFeatures } from './features';
import { DataTableFooter, type PaginationMeta } from './footer';

const columnClassName: Record<string, string> = {
    no: 'w-16 text-center',
    page_number: 'w-40 text-center',
    sequence_number: 'w-40 text-center',
};

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
            <div className="overflow-hidden rounded-lg border bg-card shadow-xs">
                <Table>
                    <TableHeader>
                        {table.getHeaderGroups().map((headerGroup) => (
                            <TableRow key={headerGroup.id} className="bg-muted/40 hover:bg-muted/40">
                                {headerGroup.headers.map((header) => (
                                    <TableHead
                                        key={header.id}
                                        className={cn(
                                            'h-11 px-4 font-medium text-muted-foreground',
                                            columnClassName[header.column.id],
                                        )}
                                    >
                                        {header.isPlaceholder ? null : <table.FlexRender header={header} />}
                                    </TableHead>
                                ))}
                            </TableRow>
                        ))}
                    </TableHeader>
                    <TableBody className="[&_tr:last-child]:border-0">
                        {table.getRowModel().rows?.length ? (
                            table.getRowModel().rows.map((row) => (
                                <TableRow
                                    key={row.id}
                                    data-state={row.getIsSelected() && 'selected'}
                                    className="transition-colors"
                                >
                                    {row.getVisibleCells().map((cell) => (
                                        <TableCell
                                            key={cell.id}
                                            className={cn('px-4 py-3', columnClassName[cell.column.id])}
                                        >
                                            <table.FlexRender cell={cell} />
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))
                        ) : (
                            <TableRow className="hover:bg-transparent">
                                <TableCell colSpan={columns.length} className="h-32 text-center text-muted-foreground">
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
