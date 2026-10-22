import { createColumnHelper } from '@tanstack/react-table';

import { type DataTableFeatures } from './features';

export type LetterNumberColumns = {
    id: string;
    name: string;
    code: string;
    page_number: number;
    sequence_number: number;
};

const columnHelper = createColumnHelper<DataTableFeatures, LetterNumberColumns>();

export const columns = columnHelper.columns([
    columnHelper.display({
        id: 'no',
        header: '#',
        cell: ({ row, table }) => {
            const { pageIndex, pageSize } = table.store.state.pagination;

            return pageIndex * pageSize + row.index + 1;
        },
    }),
    columnHelper.accessor('name', {
        header: 'Nama Klasifikasi',
    }),
    columnHelper.accessor('code', {
        header: 'Kode Klasifikasi',
    }),
    columnHelper.accessor('page_number', {
        header: 'Nomor Halaman',
    }),
    columnHelper.accessor('sequence_number', {
        header: 'Nomor Urut',
    }),
]);
