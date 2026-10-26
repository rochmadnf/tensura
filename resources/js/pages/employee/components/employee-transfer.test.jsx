import { Children, isValidElement } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vite-plus/test';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose } from '@/components/ui/dialog';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { OptionSelect } from '@/components/option-select';
import { EmployeeExcelUpload } from './employee-excel-upload';
import { EmployeeImportHelp } from './employee-import-help';
import { EmployeeTransferMenu } from './employee-transfer-menu';

const state = vi.hoisted(() => ({
    cells: [],
    cursor: 0,
    files: [],
    options: null,
    add: vi.fn(),
    clear: vi.fn(),
    choose: vi.fn(),
}));
vi.mock('react', async (original) => ({
    ...(await original()),
    useEffect: () => {},
    useRef: () => ({ current: null }),
    useState: (initial) => {
        const slot = state.cursor++;
        if (!(slot in state.cells)) state.cells[slot] = initial;
        return [
            state.cells[slot],
            (next) => {
                state.cells[slot] = next;
            },
        ];
    },
}));
vi.mock('@/hooks/use-file-upload', () => ({
    formatBytes: (bytes) => `${bytes} Bytes`,
    useFileUpload: (options) => {
        state.options = options;
        state.add.mockImplementation((files) => {
            state.files = files.map((file) => ({ id: file.name, file }));
            options.onFilesAdded(state.files);
        });
        state.clear.mockImplementation(() => {
            state.files = [];
        });
        return [
            { files: state.files, isDragging: false },
            {
                addFiles: state.add,
                clearFiles: state.clear,
                openFileDialog: state.choose,
                getInputProps: (props) => ({ ...props, type: 'file', accept: '.xlsx', multiple: false }),
                handleDragEnter: vi.fn(),
                handleDragLeave: vi.fn(),
                handleDragOver: vi.fn(),
            },
        ];
    },
}));

function findAll(node, predicate) {
    if (!isValidElement(node)) return [];
    return [
        ...(predicate(node) ? [node] : []),
        ...Children.toArray(node.props.children).flatMap((child) => findAll(child, predicate)),
    ];
}
function text(node) {
    if (!isValidElement(node)) return typeof node === 'string' || typeof node === 'number' ? String(node) : '';
    return Children.toArray(node.props.children).map(text).join('');
}
function render(Component, props = {}) {
    state.cursor = 0;
    return Component(props);
}
function button(tree, label) {
    return findAll(tree, (item) => item.type === Button && text(item) === label)[0];
}
function drop(tree, files) {
    findAll(tree, (item) => item.props.onDrop)[0].props.onDrop({
        preventDefault: vi.fn(),
        stopPropagation: vi.fn(),
        dataTransfer: { files },
    });
}

beforeEach(() => {
    state.cells = [];
    state.cursor = 0;
    state.files = [];
    vi.clearAllMocks();
    vi.stubGlobal('window', {
        location: { search: '?keyword=Siti&filter=status%3A1&page=3&per_page=5', assign: vi.fn() },
    });
});

describe('petunjuk impor', () => {
    const props = { instructions: ['NIK harus berupa teks.'], hasJobTitles: true, processing: false };
    it('opens guidance without downloading and requires the dialog download action', () => {
        let tree = render(EmployeeImportHelp, props);
        expect(tree.props.open).toBe(false);
        const trigger = button(tree, 'Unduh Template');
        expect(trigger.props.href).toBeUndefined();
        expect(trigger.props.render).toBeUndefined();
        trigger.props.onClick();
        tree = render(EmployeeImportHelp, props);
        expect(tree.props.open).toBe(true);
        expect(text(tree)).toContain('NIK harus berupa teks.');
        const download = findAll(tree, (item) => item.type === Button && item.props.render?.type === 'a')[0];
        expect(download.props.render.props.href).toContain('/employees/import/template');
        download.props.onClick();
        expect(render(EmployeeImportHelp, props).props.open).toBe(false);
        button(render(EmployeeImportHelp, props), 'Petunjuk').props.onClick();
        expect(render(EmployeeImportHelp, props).props.open).toBe(true);
        expect(findAll(tree, (item) => item.type === DialogClose)).toHaveLength(1);
    });
    it('keeps guidance available while master jobs are empty and blocks downloads', () => {
        const unavailable = { ...props, hasJobTitles: false };
        let tree = render(EmployeeImportHelp, unavailable);
        expect(button(tree, 'Unduh Template').props.disabled).toBe(true);
        button(tree, 'Petunjuk').props.onClick();
        tree = render(EmployeeImportHelp, unavailable);
        expect(tree.props.open).toBe(true);
        expect(findAll(tree, (item) => item.props.render?.type === 'a')).toHaveLength(0);
    });
});

describe('satu file Excel', () => {
    const props = () => ({ disabled: false, invalid: false, onChange: vi.fn(), onError: vi.fn() });
    const file = (name = 'pegawai.xlsx', size = 20) => new File(['x'.repeat(size)], name);
    it('passes the selected file to the form, replaces it, and clears it on removal', () => {
        const callbacks = props();
        let tree = render(EmployeeExcelUpload, callbacks);
        const first = file();
        const input = findAll(tree, (item) => item.type === 'input')[0];
        expect(input.props.multiple).toBe(false);
        input.props.onChange({ target: { files: [first], value: 'chosen' } });
        expect(callbacks.onChange).toHaveBeenLastCalledWith(first);
        tree = render(EmployeeExcelUpload, callbacks);
        expect(text(tree)).toContain('pegawai.xlsx');
        expect(text(tree)).toContain('20 Bytes');
        button(tree, 'Ganti File').props.onClick();
        expect(state.choose).toHaveBeenCalledOnce();
        const second = file('baru.xlsx');
        drop(tree, [second]);
        expect(callbacks.onChange).toHaveBeenLastCalledWith(second);
        tree = render(EmployeeExcelUpload, callbacks);
        button(tree, 'Hapus File').props.onClick();
        expect(callbacks.onChange).toHaveBeenLastCalledWith(null);
        expect(state.clear).toHaveBeenCalledOnce();
        expect(button(render(EmployeeExcelUpload, callbacks), 'Pilih File Excel')).toBeDefined();
    });
    it.each([
        ['beberapa file', () => [file(), file('dua.xlsx')], 'satu file Excel'],
        ['tipe salah', () => [file('pegawai.csv')], '.xlsx'],
        ['terlalu besar', () => [file('besar.xlsx', 2 * 1024 * 1024 + 1)], '2 MB'],
    ])('rejects %s without replacing the current file', (_, files, message) => {
        const callbacks = props();
        const original = file();
        state.files = [{ id: 'existing', file: original }];
        drop(render(EmployeeExcelUpload, callbacks), files());
        expect(callbacks.onError).toHaveBeenCalledWith(expect.stringContaining(message));
        expect(state.add).not.toHaveBeenCalled();
        expect(callbacks.onChange).not.toHaveBeenCalled();
        expect(state.files[0].file).toBe(original);
    });
    it('ignores selection, replacement, removal, and drop while disabled', () => {
        const callbacks = { ...props(), disabled: true };
        state.files = [{ id: 'existing', file: file() }];
        const tree = render(EmployeeExcelUpload, callbacks);
        for (const label of ['Ganti File', 'Hapus File']) {
            expect(button(tree, label).props.disabled).toBe(true);
            button(tree, label).props.onClick();
        }
        drop(tree, [file('dua.xlsx')]);
        expect(state.add).not.toHaveBeenCalled();
        expect(state.clear).not.toHaveBeenCalled();
        expect(state.choose).not.toHaveBeenCalled();
        expect(callbacks.onChange).not.toHaveBeenCalled();
    });
});

describe('menu impor/ekspor', () => {
    it('offers two actions and exports all data by default without sending filters', () => {
        let tree = render(EmployeeTransferMenu);
        const items = findAll(tree, (item) => item.type === DropdownMenuItem);
        expect(items.map(text)).toEqual(['Impor Data Pegawai', 'Ekspor Data Pegawai']);
        expect(items[0].props.render.props.href).toContain('/employees/import');
        items[1].props.onClick();
        tree = render(EmployeeTransferMenu);
        expect(findAll(tree, (item) => item.type === Dialog)[0].props.open).toBe(true);
        button(tree, 'Ekspor Excel').props.onClick();
        const url = new URL(window.location.assign.mock.calls[0][0], 'http://localhost');
        expect(url.searchParams.get('scope')).toBe('all');
        expect(url.searchParams.has('filter')).toBe(false);
        expect(url.searchParams.has('page')).toBe(false);
    });
    it('exports the active filter and keyword without pagination', () => {
        let tree = render(EmployeeTransferMenu);
        findAll(tree, (item) => item.type === DropdownMenuItem)[1].props.onClick();
        tree = render(EmployeeTransferMenu);
        findAll(tree, (item) => item.type === OptionSelect)[0].props.onValueChange('filtered');
        tree = render(EmployeeTransferMenu);
        button(tree, 'Ekspor Excel').props.onClick();
        const url = new URL(window.location.assign.mock.calls[0][0], 'http://localhost');
        expect(url.searchParams.get('scope')).toBe('filtered');
        expect(url.searchParams.get('keyword')).toBe('Siti');
        expect(url.searchParams.get('filter')).toBe('status:1');
        expect(url.searchParams.has('page')).toBe(false);
        expect(url.searchParams.has('per_page')).toBe(false);
    });
});
