import type { ReactTable, RowData } from '@tanstack/react-table';
import type { ReactNode } from 'react';

import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { cn } from '@/lib/utils';
import type { DataTableFeatures } from './features';

export { createDataTableColumnHelper, useDataTable } from './features';
export type { DataTableColumnMeta, DataTableFeatures } from './features';
export { DataTableFooter } from './footer';
export type { DataTableFooterProps, PaginationMeta, PaginationParams } from './footer';

const SKELETON_ROW_LIMIT = 10;

export interface DataTableProps<TData extends RowData> {
    table: ReactTable<DataTableFeatures, TData>;
    isLoading?: boolean;
    emptyMessage?: ReactNode;
    footer?: ReactNode;
}

export function DataTable<TData extends RowData>({
    table,
    isLoading = false,
    emptyMessage = 'Belum ada data yang tersedia.',
    footer,
}: DataTableProps<TData>) {
    const skeletonRowCount = Math.min(table.state.pagination.pageSize, SKELETON_ROW_LIMIT);

    return (
        <div className="flex flex-col gap-4">
            <div className="overflow-hidden rounded-lg border bg-card shadow-xs" aria-busy={isLoading}>
                <Table>
                    <TableHeader>
                        {table.getHeaderGroups().map((headerGroup) => (
                            <TableRow key={headerGroup.id} className="bg-muted/40 hover:bg-muted/40">
                                {headerGroup.headers.map((header) => (
                                    <TableHead
                                        key={header.id}
                                        className={cn(
                                            'h-11 px-4 font-medium text-muted-foreground',
                                            header.column.columnDef.meta?.className,
                                        )}
                                        colSpan={header.colSpan}
                                    >
                                        {header.isPlaceholder ? null : <table.FlexRender header={header} />}
                                    </TableHead>
                                ))}
                            </TableRow>
                        ))}
                    </TableHeader>
                    <TableBody className="[&_tr:last-child]:border-0">
                        {isLoading ? (
                            Array.from({ length: skeletonRowCount }, (_, rowIndex) => (
                                <TableRow key={`skeleton-${rowIndex}`} className="hover:bg-transparent">
                                    {table.getVisibleLeafColumns().map((column) => {
                                        const meta = column.columnDef.meta;

                                        return (
                                            <TableCell key={column.id} className={cn('px-4 py-3', meta?.className)}>
                                                <Skeleton className={cn('h-4 w-full', meta?.skeletonClassName)} />
                                            </TableCell>
                                        );
                                    })}
                                </TableRow>
                            ))
                        ) : table.getRowModel().rows?.length ? (
                            table.getRowModel().rows.map((row) => (
                                <TableRow
                                    key={row.id}
                                    data-state={row.getIsSelected() && 'selected'}
                                    className="transition-colors"
                                >
                                    {row.getVisibleCells().map((cell) => (
                                        <TableCell
                                            key={cell.id}
                                            className={cn('px-4 py-3', cell.column.columnDef.meta?.className)}
                                        >
                                            <table.FlexRender cell={cell} />
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))
                        ) : (
                            <TableRow className="hover:bg-transparent">
                                <TableCell
                                    colSpan={Math.max(table.getVisibleLeafColumns().length, 1)}
                                    className="h-32 text-center text-muted-foreground"
                                >
                                    {emptyMessage}
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>

            {footer}
        </div>
    );
}
