import { createColumnHelper } from '@tanstack/react-table';

import { type DataTableFeatures } from './features';

export type Payment = {
    id: string;
    amount: number;
    status: 'pending' | 'processing' | 'success' | 'failed';
    email: string;
};

// Use `accessor` for data columns and `display` for columns without one.
const columnHelper = createColumnHelper<DataTableFeatures, Payment>();

export const columns = columnHelper.columns([
    columnHelper.accessor('status', {
        header: 'Status',
    }),
    columnHelper.accessor('email', {
        header: 'Email',
    }),
    columnHelper.accessor('amount', {
        header: 'Amount',
    }),
]);
