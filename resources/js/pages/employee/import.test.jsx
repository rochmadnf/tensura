import { Children, isValidElement } from 'react';
import { beforeEach, expect, it, vi } from 'vite-plus/test';
import { Alert } from '@/components/reui/alert';
import { FormCard } from '@/components/form-card';
import { Button } from '@/components/ui/button';
import { EmployeeExcelUpload } from './components/employee-excel-upload';
import EmployeeImport from './import';

const state = vi.hoisted(() => ({ values: [], cursor: 0, http: null }));

vi.mock('react', async (original) => ({
    ...(await original()),
    useState(initial) {
        const slot = state.cursor++;
        if (!(slot in state.values)) state.values[slot] = initial;
        return [
            state.values[slot],
            (next) => {
                state.values[slot] = next;
            },
        ];
    },
}));

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original()),
    useHttp: () => state.http,
}));

const nodes = (node) => (!isValidElement(node) ? [] : [node, ...Children.toArray(node.props.children).flatMap(nodes)]);
const text = (node) =>
    !isValidElement(node)
        ? typeof node === 'string' || typeof node === 'number'
            ? String(node)
            : ''
        : Children.toArray(node.props.children).map(text).join('');
const props = {
    hasJobTitles: true,
    importSettings: { maxSizeBytes: 5242880, maxSizeLabel: '5 MB', maxRows: 1000 },
    instructionSections: [],
};
const report = { total: 1, processed: 1, imported: 1, duplicate: 0, invalid: 0, interrupted: false, issues: [] };

function render() {
    state.cursor = 0;
    return EmployeeImport(props);
}

function submit(tree) {
    return nodes(tree)
        .find((node) => node.type === 'form')
        .props.onSubmit({ preventDefault: vi.fn() });
}

function upload(tree) {
    return nodes(tree).find((node) => node.type === EmployeeExcelUpload);
}

function importButtons(tree) {
    return nodes(tree).filter((node) => node.type === Button && node.props.type === 'submit');
}

function results(tree) {
    return nodes(tree).filter((node) => node.type === FormCard && node.props.title === 'Hasil Impor');
}

beforeEach(() => {
    state.values = [];
    state.cursor = 0;
    state.http = {
        data: { file: new File(['pegawai'], 'pegawai.xlsx') },
        processing: false,
        errors: {},
        post: vi.fn().mockResolvedValue(report),
        clearErrors: vi.fn(),
        setError: vi.fn(),
        setData: vi.fn((field, value) => {
            state.http.data[field] = value;
        }),
    };
});

it.each([
    ['berhasil', report],
    ['duplikat atau tidak valid', { ...report, imported: 0, duplicate: 1 }],
    ['terhenti', { ...report, interrupted: true }],
])('hides import and prevents resubmission after receiving a %s report', async (_, response) => {
    state.http.post.mockResolvedValue(response);
    let tree = render();
    expect(importButtons(tree)).toHaveLength(1);

    await submit(tree);
    tree = render();

    expect(importButtons(tree)).toHaveLength(0);
    expect(results(tree)).toHaveLength(1);
    const alert = nodes(tree).find((node) => node.type === Alert && node.props.variant === 'info');
    expect(alert.props.role).toBe('status');
    expect(text(alert)).toContain('Ganti atau pilih ulang file Excel terlebih dahulu');

    await submit(tree);
    expect(state.http.post).toHaveBeenCalledOnce();
    expect(results(render())).toHaveLength(1);
});

it('allows another import after selecting a replacement and clears the previous report', async () => {
    await submit(render());
    const replacement = new File(['pegawai diperbaiki'], 'perbaikan.xlsx');

    upload(render()).props.onChange(replacement);
    const tree = render();

    expect(state.http.data.file).toBe(replacement);
    expect(importButtons(tree)).toHaveLength(1);
    expect(results(tree)).toHaveLength(0);
    expect(nodes(tree).filter((node) => node.type === Alert && node.props.variant === 'info')).toHaveLength(0);

    await submit(tree);
    expect(state.http.post).toHaveBeenCalledTimes(2);
    expect(importButtons(render())).toHaveLength(0);
});

it('keeps the previous report and blocks resubmission when a replacement is rejected', async () => {
    await submit(render());

    upload(render()).props.onError('Gunakan file Excel berformat .xlsx.');
    const tree = render();
    await submit(tree);

    expect(state.http.setError).toHaveBeenCalledWith('file', 'Gunakan file Excel berformat .xlsx.');
    expect(importButtons(tree)).toHaveLength(0);
    expect(results(tree)).toHaveLength(1);
    expect(state.http.post).toHaveBeenCalledOnce();
});

it('requires selecting a file after removing the previously imported file', async () => {
    await submit(render());

    upload(render()).props.onChange(null);
    const tree = render();
    expect(importButtons(tree)).toHaveLength(1);
    expect(results(tree)).toHaveLength(0);
    await submit(tree);

    expect(state.http.setError).toHaveBeenCalledWith('file', 'Pilih file Excel yang akan diimpor.');
    expect(state.http.post).toHaveBeenCalledOnce();
});

it('keeps import available after a failed request without a report', async () => {
    state.http.post.mockRejectedValueOnce(new Error('Network error'));
    await submit(render());

    const tree = render();
    expect(importButtons(tree)).toHaveLength(1);
    expect(results(tree)).toHaveLength(0);
    expect(text(tree)).toContain('Permintaan impor gagal.');

    await submit(tree);
    expect(state.http.post).toHaveBeenCalledTimes(2);
    expect(importButtons(render())).toHaveLength(0);
});
