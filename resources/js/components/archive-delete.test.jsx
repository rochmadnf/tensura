import { App } from '@inertiajs/react';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it, vi } from 'vite-plus/test';
import { DeleteSummary } from '@/pages/archive/components/delete-dialog';
import { RecordTable } from '@/pages/archive/components/record-table';
import ArchiveShow from '@/pages/archive/show';

const archive = {
    uuid: 'archive-uuid',
    module: 'reference',
    kind: 'package',
    entity: 'reference_group',
    label: 'Grup asal',
    parent: { uuid: 'parent-uuid', label: 'Grup asal' },
    items_count: 2,
    parent_status: 'archived',
    parent_resolved: false,
    target: null,
    records_count: 3,
    archived_at: null,
    archived_by_name: 'Penghapus',
};
const meta = { current_page: 1, per_page: 5, from: 1, to: 2, last_page: 1, total: 2 };
const record = (uuid, supported = true) => ({
    uuid,
    entity: 'reference_item',
    label: `Item ${uuid}`,
    source_uuid: `original-${uuid}`,
    sort_order: 1,
    schema_version: supported ? 1 : 99,
    supported,
    unsupported_reason: supported ? null : 'Snapshot belum didukung.',
});
function render(Component, props) {
    return renderToStaticMarkup(
        <App
            initialComponent={Component}
            initialPage={{
                component: 'archive/show',
                props: { page: { id: 'archive' }, context: null, ...props },
                url: '/archives/archive-uuid',
                version: null,
                clearHistory: false,
                encryptHistory: false,
            }}
        />,
    );
}
function deleteButtons(markup) {
    return (markup.match(/<button\b[^>]*>/g) ?? []).filter((button) => button.includes('aria-label="Hapus permanen'));
}

describe('Delete Forever controls', () => {
    it('lists only the server preview names and counts and escapes archived labels', () => {
        const preview = {
            counts: { total: 3 },
            candidates: [
                { record_uuid: 'parent-record', entity: 'reference_group', label: 'Grup arsip' },
                { record_uuid: 'first-record', entity: 'reference_item', label: '<script>Nama item</script>' },
                { record_uuid: 'off-page-record', entity: 'reference_item', label: 'Item dari halaman lain' },
            ],
        };
        const markup = renderToStaticMarkup(<DeleteSummary preview={preview} />);
        expect(markup).toContain('<strong>3</strong> data arsip');
        expect(markup).toContain('1 grup dan 2 item');
        expect(markup).toContain('Item dari halaman lain');
        expect(markup).toContain('&lt;script&gt;Nama item&lt;/script&gt;');
        expect(markup).not.toContain('<script>');
    });

    it('offers deletion for unsupported snapshots while keeping restore disabled', () => {
        const markup = render(RecordTable, {
            resources: { data: [record('unsupported', false)], meta },
            archiveUuid: archive.uuid,
            rowSelection: {},
            onRowSelectionChange: vi.fn(),
            onRestore: vi.fn(),
            onDelete: vi.fn(),
            selectable: true,
            restorable: true,
            disabled: false,
        });
        expect(deleteButtons(markup)).toHaveLength(1);
        expect(deleteButtons(markup)[0]).not.toContain('disabled=""');
        expect(markup).toMatch(/<button\b(?=[^>]*aria-label="Pulihkan Item unsupported")(?=[^>]*disabled)[^>]*>/);
    });

    it('disables delete buttons when restore or another deletion is active', () => {
        const props = {
            resources: { data: [record('one')], meta },
            archiveUuid: archive.uuid,
            rowSelection: {},
            onRowSelectionChange: vi.fn(),
            onRestore: vi.fn(),
            onDelete: vi.fn(),
            selectable: true,
            restorable: true,
            disabled: false,
        };
        expect(
            deleteButtons(render(RecordTable, { ...props, disabled: true })).every((button) =>
                button.includes('disabled=""'),
            ),
        ).toBe(true);
        expect(
            deleteButtons(render(RecordTable, { ...props, deletionDisabled: true })).every((button) =>
                button.includes('disabled=""'),
            ),
        ).toBe(true);
    });

    it('never offers separate parent deletion in the item table', () => {
        const markup = render(RecordTable, {
            resources: { data: [{ ...record('parent'), entity: 'reference_group' }], meta },
            archiveUuid: archive.uuid,
            rowSelection: {},
            onRowSelectionChange: vi.fn(),
            onRestore: vi.fn(),
            onDelete: vi.fn(),
            selectable: false,
            restorable: false,
            disabled: false,
        });
        expect(deleteButtons(markup)).toHaveLength(0);
    });

    it('separates the package danger action and selection action from restore', () => {
        const markup = render(ArchiveShow, {
            resource: { data: archive },
            parent_record: null,
            resources: { data: [record('one'), record('two')], meta },
        });
        expect(markup).toContain('Hapus Permanen Arsip…');
        expect(markup).toContain('Hapus Permanen Terpilih…');
        expect(markup).toContain('Penghapusan permanen');
        expect(markup).toContain('Data tidak dapat dipulihkan setelah dihapus permanen.');
        expect(deleteButtons(markup)).toHaveLength(2);
        expect(markup).not.toContain('role="alertdialog"');
    });
});
