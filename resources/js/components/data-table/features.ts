import {
    columnFilteringFeature,
    columnVisibilityFeature,
    createFilteredRowModel,
    createPaginatedRowModel,
    createSortedRowModel,
    createTableHook,
    filterFn_includesString,
    metaHelper,
    rowPaginationFeature,
    rowSelectionFeature,
    rowSortingFeature,
    sortFn_alphanumeric,
    sortFn_text,
    tableFeatures,
} from '@tanstack/react-table';

export interface DataTableColumnMeta {
    className?: string;
    skeletonClassName?: string;
}

export const features = tableFeatures({
    columnFilteringFeature,
    columnVisibilityFeature,
    rowPaginationFeature,
    rowSelectionFeature,
    rowSortingFeature,
    filteredRowModel: createFilteredRowModel(),
    paginatedRowModel: createPaginatedRowModel(),
    sortedRowModel: createSortedRowModel(),
    filterFns: { includesString: filterFn_includesString },
    sortFns: { alphanumeric: sortFn_alphanumeric, text: sortFn_text },
    columnMeta: metaHelper<DataTableColumnMeta>(),
});

export type DataTableFeatures = typeof features;

export const { createAppColumnHelper: createDataTableColumnHelper, useAppTable: useDataTable } = createTableHook({
    features,
});
