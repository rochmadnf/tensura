import { App } from '@inertiajs/react';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it, vi } from 'vite-plus/test';
import { RecordTable } from '@/pages/archive/components/record-table';
import { RestoreWorkbench } from '@/pages/archive/components/restore-workbench';
import { RestorePreview } from '@/pages/archive/components/restore-preview';
import { initialRestoreState, restoreReducer, restoreSelection } from '@/pages/archive/components/restore-state';

let currentTable;
vi.mock('@/components/data-table', async (importOriginal) => {
    const original = await importOriginal();
    return {
        ...original,
        useDataTable: (...args) => {
            const table = original.useDataTable(...args);
            currentTable = table;
            return table;
        },
    };
});

const archive = {
    uuid: 'archive-uuid',
    module: 'reference',
    kind: 'package',
    entity: 'reference_group',
    label: 'Grup asal',
    parent: { uuid: 'parent-uuid', label: 'Grup asal' },
    items_count: 3,
    parent_resolved: false,
    target: null,
};
const parent = {
    uuid: 'parent-record',
    label: 'Grup asal',
    supported: true,
    source_uuid: 'parent-uuid',
    description: 'Deskripsi arsip',
};
const record = (uuid, label = uuid) => ({
    uuid,
    source_uuid: `original-${uuid}`,
    entity: 'reference_item',
    label,
    supported: true,
    sort_order: 1,
    schema_version: 1,
});
const meta = { current_page: 1, per_page: 5, from: 1, to: 2, last_page: 2, total: 3 };
const preview = {
    archive_uuid: archive.uuid,
    action: 'restore',
    mode: 'parent_with_children',
    parent: {
        record_uuid: parent.uuid,
        uuid: parent.source_uuid,
        name: parent.label,
        description: parent.description,
        items: [
            { record_uuid: 'item-1', label: 'Konten sama' },
            { record_uuid: 'item-2', label: 'Item aman' },
        ],
    },
    target: null,
    matching_groups: [
        {
            uuid: 'active-group',
            name: 'Grup aktif',
            description: 'Deskripsi aktif',
            items: [{ uuid: 'active-item', content: 'Konten sama', sort_order: 1 }],
        },
    ],
    candidates: [
        {
            record_uuid: 'item-1',
            entity: 'reference_item',
            label: 'Konten sama',
            operation: 'restore',
            status: 'conflict',
            conflicts: [{ code: 'content_conflict', message: 'Konten sudah tersedia dalam grup tujuan.' }],
        },
        {
            record_uuid: 'item-2',
            entity: 'reference_item',
            label: 'Item aman',
            operation: 'restore',
            status: 'ready',
            conflicts: [],
        },
    ],
    counts: { total: 2, ready: 1, conflicted: 1 },
    can_confirm: true,
    confirmation_token: 'token',
    conflicts: [],
};

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
function flow(state) {
    return {
        state,
        busy: state.activity !== 'idle',
        dispatch: vi.fn(),
        requestPreview: vi.fn(),
        confirm: vi.fn(),
        renameGroup: vi.fn(),
        renameErrors: {},
    };
}
function buttons(markup) {
    return markup.match(/<button\b[^>]*>/g) ?? [];
}

describe('Archive restore screens', () => {
    it('compares full archived and active groups with conflict reasons and an edit link', () => {
        const markup = render(RestorePreview, { preview, disabled: false, onTarget: vi.fn() });
        expect(markup).toContain('Deskripsi arsip');
        expect(markup).toContain('Deskripsi aktif');
        expect(markup).toContain('Item aman');
        expect(markup).toContain('Konten sudah tersedia dalam grup tujuan.');
        expect(markup).toContain('Tetap di arsip');
        expect(markup).toContain('href="/reference/active-group/edit"');
        expect(markup).toContain('Gunakan grup ini');
    });

    it('requires explicitly choosing a group even when the original UUID already exists', () => {
        const markup = render(RestorePreview, {
            preview: { ...preview, target: preview.matching_groups[0] },
            disabled: false,
            onTarget: vi.fn(),
        });
        expect(markup).toContain('Gunakan grup ini');
        expect(markup).not.toContain('Tujuan dipilih');
        const chosen = render(RestorePreview, {
            preview,
            disabled: false,
            selectedTargetUuid: 'active-group',
            onTarget: vi.fn(),
        });
        expect(chosen).toContain('Tujuan dipilih');
    });

    it('shows off-page selection and blocks preview until a pending rename is saved or cancelled', () => {
        const state = {
            ...initialRestoreState(true, false),
            mode: 'children_selected',
            rowSelection: { 'page-one': true, 'page-two': true },
            renameDirty: true,
        };
        const markup = render(RestoreWorkbench, { archive, parent, flow: flow(state) });
        expect(markup).toContain('2 item dipilih lintas halaman');
        expect(markup).toContain('Simpan atau batalkan perubahan nama');
        expect(buttons(markup).some((button) => button.includes('disabled=""'))).toBe(true);
        expect(markup).toContain('maxLength="100"');
    });

    it('shows the refreshed preview after archive_changed without opening confirmation', () => {
        const state = {
            ...initialRestoreState(true, false),
            preview,
            previewSelection: { action: 'restore', mode: 'parent_with_children' },
            notice: 'Data berubah. Tinjau ulang dan konfirmasi kembali.',
        };
        const markup = render(RestoreWorkbench, { archive, parent, flow: flow(state) });
        expect(markup).toContain('Data berubah. Tinjau ulang dan konfirmasi kembali.');
        expect(markup).toContain('Lanjutkan konfirmasi');
        expect(markup).not.toContain('role="dialog"');
    });

    it('shows the partial outcome, retained conflicts and their next step', () => {
        const state = {
            ...initialRestoreState(false, false),
            result: {
                counts: { restored: 2, remaining: 1 },
                target: { name: 'Grup aktif' },
                conflicts: [preview.candidates[0]],
            },
        };
        const markup = render(RestoreWorkbench, { archive: { ...archive, parent_resolved: true }, flow: flow(state) });
        expect(markup).toContain('2 data dipulihkan ke Grup aktif; 1 data masih diarsipkan.');
        expect(markup).toContain('Konten sudah tersedia dalam grup tujuan.');
        expect(markup).toContain('mencoba kembali');
        expect(markup).not.toContain('Nama baru grup arsip');
    });

    it('does not expose a restore workflow for unsupported modules', () => {
        const markup = render(RestoreWorkbench, {
            archive: { ...archive, module: 'other' },
            flow: flow(initialRestoreState(false, false)),
        });
        expect(markup).toContain('Pemulihan modul ini belum didukung.');
        expect(markup).not.toContain('Buat pratinjau');
    });

    it('disables scope, target, rename, and table interactions while a request is pending', () => {
        const state = { ...initialRestoreState(true, false), activity: 'restore' };
        const markup = render(RestoreWorkbench, { archive, parent, flow: flow(state) });
        expect(buttons(markup).every((button) => button.includes('disabled=""'))).toBe(true);
        expect(markup).toMatch(/<input\b(?=[^>]*id="archived-group-name")(?=[^>]*disabled)[^>]*>/);
    });
});

describe('Archive table selection across server pages', () => {
    function tableProps(state, data, onChange) {
        return {
            resources: { data, meta },
            archiveUuid: archive.uuid,
            rowSelection: state.rowSelection,
            onRowSelectionChange: onChange,
            onRestore: vi.fn(),
            selectable: true,
            restorable: true,
            disabled: false,
        };
    }
    it('uses archive record UUIDs and only selects or clears the current page', () => {
        let state = { ...initialRestoreState(false, false), mode: 'children_selected' };
        const onChange = (update) => {
            state = restoreReducer(state, { type: 'selection', update });
        };
        render(RecordTable, tableProps(state, [record('page-one-a'), record('page-one-b')], onChange));
        currentTable.toggleAllPageRowsSelected(true);
        expect(restoreSelection(state).record_uuids).toEqual(['page-one-a', 'page-one-b']);
        render(RecordTable, tableProps(state, [record('page-two')], onChange));
        currentTable.toggleAllPageRowsSelected(true);
        expect(restoreSelection(state).record_uuids).toEqual(['page-one-a', 'page-one-b', 'page-two']);
        render(RecordTable, tableProps(state, [record('page-two')], onChange));
        currentTable.toggleAllPageRowsSelected(false);
        expect(restoreSelection(state).record_uuids).toEqual(['page-one-a', 'page-one-b']);
        const markup = render(RecordTable, tableProps(state, [record('page-one-a'), record('page-one-b')], onChange));
        expect(markup).toMatch(
            /<span\b(?=[^>]*role="checkbox")(?=[^>]*aria-label="Pilih semua item pada halaman ini")(?=[^>]*aria-checked="true")[^>]*>/,
        );
    });

    it('shows a partially selected header and prevents selection and restore during processing', () => {
        const state = { ...initialRestoreState(false, false), rowSelection: { 'item-one': true, 'off-page': true } };
        const props = tableProps(state, [record('item-one'), record('item-two')], vi.fn());
        const markup = render(RecordTable, props);
        expect(markup).toContain('aria-checked="mixed"');
        const disabled = render(RecordTable, { ...props, disabled: true });
        expect(buttons(disabled).every((button) => button.includes('disabled=""'))).toBe(true);
        const checkboxes = disabled.match(/<span\b(?=[^>]*role="checkbox")[^>]*>/g);
        expect(checkboxes.every((checkbox) => checkbox.includes('aria-disabled="true"'))).toBe(true);
    });
});
