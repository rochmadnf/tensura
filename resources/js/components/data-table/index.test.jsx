import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vite-plus/test';
import { DataTable, DataTableFooter, createDataTableColumnHelper, useDataTable } from './index';

const columnHelper = createDataTableColumnHelper();
const columns = columnHelper.columns([
    columnHelper.group({
        id: 'product',
        header: 'Product',
        columns: columnHelper.columns([
            columnHelper.accessor('sku', { header: 'SKU' }),
            columnHelper.accessor('price', {
                header: 'Price',
                cell: ({ cell }) => `$${cell.getValue().toFixed(2)}`,
            }),
        ]),
    }),
]);
const products = [{ sku: 'ITEM-001', price: 12.5 }];

function ProductTable({
    data = products,
    hidePrice = false,
    pageSize = 10,
    pageIndex = 0,
    manualPagination = true,
    ...props
}) {
    const table = useDataTable({
        columns,
        data,
        manualPagination,
        getRowId: (row) => row.sku,
        state: {
            columnVisibility: { price: !hidePrice },
            pagination: { pageIndex, pageSize },
        },
    });

    return <DataTable table={table} {...props} />;
}

describe('DataTable', () => {
    it('renders another feature schema, custom cells, grouped headers, and an optional footer', () => {
        const markup = renderToStaticMarkup(<ProductTable footer={<p>Product totals</p>} />);

        expect(markup).toContain('ITEM-001');
        expect(markup).toContain('$12.50');
        expect(markup).toMatch(/<th\b[^>]*colSpan="2"[^>]*>Product<\/th>/);
        expect(markup).toContain('Product totals');
        expect(markup).toContain('aria-busy="false"');
    });

    it('renders a custom empty state spanning only visible leaf columns', () => {
        const markup = renderToStaticMarkup(<ProductTable data={[]} hidePrice emptyMessage="No products found" />);

        expect(markup).toMatch(/<td\b[^>]*colSpan="1"[^>]*>No products found<\/td>/);
        expect(markup).not.toContain('>Price<');
    });

    it.each([5, 100])('shows bounded loading placeholders for a page size of %i', (pageSize) => {
        const markup = renderToStaticMarkup(<ProductTable isLoading hidePrice pageSize={pageSize} />);

        expect(markup).toContain('aria-busy="true"');
        expect(markup.match(/data-slot="skeleton"/g)).toHaveLength(Math.min(pageSize, 10));
        expect(markup).not.toContain('ITEM-001');
        expect(markup).not.toContain('Belum ada data');
    });

    it('renders server-provided rows on later pages without paginating them again', () => {
        const markup = renderToStaticMarkup(<ProductTable pageIndex={3} />);

        expect(markup).toContain('ITEM-001');
        expect(markup).toContain('$12.50');
    });

    it('supports a feature choosing client pagination', () => {
        const markup = renderToStaticMarkup(
            <ProductTable
                data={[...products, { sku: 'ITEM-002', price: 20 }]}
                manualPagination={false}
                pageSize={1}
                pageIndex={1}
            />,
        );

        expect(markup).toContain('ITEM-002');
        expect(markup).not.toContain('ITEM-001');
    });
});

describe('DataTableFooter', () => {
    const meta = { current_page: 1, from: 1, to: 10, last_page: 3, per_page: 10, total: 30 };

    it.each([1, 3])('disables navigation past the boundaries on page %i', (page) => {
        const markup = renderToStaticMarkup(
            <DataTableFooter meta={{ ...meta, current_page: page }} onPaginationChange={() => {}} />,
        );
        const buttons = markup.match(/<button\b[^>]*>/g);

        for (const label of ['Halaman pertama', 'Halaman sebelumnya']) {
            expect(buttons.find((button) => button.includes(`aria-label="${label}"`)).includes(' disabled=""')).toBe(
                page === 1,
            );
        }

        for (const label of ['Halaman berikutnya', 'Halaman terakhir']) {
            expect(buttons.find((button) => button.includes(`aria-label="${label}"`)).includes(' disabled=""')).toBe(
                page === 3,
            );
        }
    });

    it('disables navigation and page-size controls while loading', () => {
        const markup = renderToStaticMarkup(<DataTableFooter meta={meta} disabled onPaginationChange={() => {}} />);
        const buttons = markup.match(/<button\b[^>]*>/g);

        expect(buttons).toHaveLength(6);
        expect(buttons.every((button) => button.includes(' disabled=""'))).toBe(true);
    });
});
