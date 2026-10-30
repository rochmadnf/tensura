import { Children, isValidElement } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vite-plus/test';
import { LetterForm } from './letter-form';
import { FormActions } from '@/components/form-actions';
import { OptionSelect } from '@/components/option-select';
import { Button } from '@/components/ui/button';

const state = vi.hoisted(() => ({ form: null, submit: vi.fn(), definition: null, get: vi.fn() }));
vi.mock('react', async (original) => ({
    ...(await original()),
    useEffect: vi.fn(),
    useState: (initial) => {
        state.definition ??= initial;
        return [state.definition, vi.fn()];
    },
    useRef: (initial) => ({ current: initial }),
    useCallback: (fn) => fn,
}));
vi.mock('@inertiajs/react', async (original) => ({ ...(await original()), router: { get: state.get } }));
vi.mock('@/hooks/use-valibot-form', () => ({ useValibotForm: () => ({ form: state.form, submit: state.submit }) }));
const all = (node, predicate) =>
    !isValidElement(node)
        ? []
        : [
              ...(predicate(node) ? [node] : []),
              ...Children.toArray(node.props.children).flatMap((child) => all(child, predicate)),
          ];
const text = (node) =>
    !isValidElement(node)
        ? typeof node === 'string' || typeof node === 'number'
            ? String(node)
            : ''
        : Children.toArray(node.props.children).map(text).join('');
const configuration = {
    version: 'version',
    number: 1,
    date_format: 'd-m-Y',
    classification: '445',
    variables: [
        {
            id: 'person',
            name: 'pegawai',
            label: 'Pegawai',
            type: 4,
            multiple: false,
            required: true,
            options: [{ value: 'a', label: 'Nama tersimpan' }],
        },
    ],
    signers: [],
};
const resource = {
    id: 'letter',
    subject: 'Surat',
    template: { id: 'template', name: 'Tugas' },
    letter_date: '2026-10-26',
    revision: 1,
    editable: true,
    draft: {
        revision: 1,
        numbering_mode: 1,
        number_year: 2026,
        number_month: 10,
        updated_by: 'Ani',
        values: { person: 'a' },
        signers: {},
        resolved: { pegawai: 'Nama tersimpan' },
    },
};
const props = (overrides = {}) => ({
    configuration,
    templateId: 'template',
    today: '2026-10-26',
    resource,
    editing: true,
    onClose: vi.fn(),
    ...overrides,
});
beforeEach(() => {
    state.definition = null;
    state.form = {
        data: {
            subject: 'Belum disimpan',
            revision: '1',
            letter_date: '2026-10-26',
            numbering_mode: '1',
            number_year: '2026',
            number_month: '10',
            page_number: null,
            sequence_number: null,
            values: { person: 'a' },
            signers: {},
        },
        processing: false,
        errors: {},
        setData: vi.fn(),
        reset: vi.fn(),
        clearErrors: vi.fn(),
    };
    state.submit.mockReset().mockReturnValue(true);
});

describe('letter draft editor', () => {
    it('retains unsaved input when a stale edit is rejected and offers an explicit reload', () => {
        state.form.errors.revision = 'Draft telah diperbarui oleh Ani.';
        const tree = LetterForm(props());
        expect(text(tree)).toContain('Draft telah diperbarui oleh Ani.');
        expect(text(tree)).toContain('Salin input');
        expect(all(tree, (node) => node.props.id === 'letter-subject')[0].props.value).toBe('Belum disimpan');
        expect(state.form.reset).not.toHaveBeenCalled();
        expect(all(tree, (node) => node.type === Button && text(node) === 'Muat versi terbaru')).toHaveLength(1);
    });
    it('keeps selection labels frozen, prevents duplicate busy submits, and cancels locally', () => {
        const properties = props();
        state.form.processing = true;
        const tree = LetterForm(properties);
        all(tree, (node) => node.type === 'form')[0].props.onSubmit({ preventDefault: vi.fn() });
        expect(state.submit).not.toHaveBeenCalled();
        expect(
            all(tree, (node) => node.type === OptionSelect && node.props.name === 'values.person')[0].props.remote
                .preserveSelectedLabels,
        ).toBe(true);
        all(tree, (node) => node.type === FormActions)[0].props.onCancel();
        expect(state.form.reset).toHaveBeenCalledOnce();
        expect(properties.onClose).toHaveBeenCalledOnce();
    });
    it('starts saved drafts in read mode and preserves visible snapshot values', () => {
        const tree = LetterForm(props({ editing: false }));
        expect(all(tree, (node) => node.type === 'form')).toHaveLength(0);
        expect(text(tree)).toContain('Nama tersimpan');
        expect(text(tree)).toContain('Nilai dokumen yang tersimpan');
    });
    it('closes the editor on Escape when idle and resets hidden manual positions when switching modes', () => {
        const properties = props();
        const tree = LetterForm(properties);
        all(tree, (node) => node.type === 'form')[0].props.onKeyDown({
            key: 'Escape',
            defaultPrevented: false,
            preventDefault: vi.fn(),
        });
        expect(properties.onClose).toHaveBeenCalledOnce();
        all(tree, (node) => node.type === OptionSelect && node.props.name === 'numbering_mode')[0].props.onValueChange(
            '1',
        );
        expect(state.form.setData).toHaveBeenCalledWith('page_number', null);
        expect(state.form.setData).toHaveBeenCalledWith('sequence_number', null);
    });
    it('reloads the current template on a rejected new-draft version without accessing a missing letter', () => {
        state.form.errors.template_version = 'Versi templat aktif berubah.';
        const tree = LetterForm(props({ resource: undefined }));
        all(tree, (node) => node.type === Button && text(node) === 'Muat versi terbaru')[0].props.onClick();
        expect(state.get).toHaveBeenCalledWith(
            expect.stringContaining('/letters/create?template=template'),
            {},
            { preserveState: false, preserveScroll: true },
        );
    });
    it('keeps old fields available for copying when another operator changes the template during an edit', () => {
        LetterForm(props());
        state.form.errors.revision = 'Draft diperbarui oleh Ani.';
        const changed = {
            ...configuration,
            version: 'new-version',
            variables: [{ ...configuration.variables[0], id: 'replacement', label: 'Isian Baru' }],
        };
        const tree = LetterForm(props({ configuration: changed }));
        expect(all(tree, (node) => node.type === OptionSelect && node.props.name === 'values.person')).toHaveLength(1);
        expect(
            all(tree, (node) => node.type === OptionSelect && node.props.name === 'values.replacement'),
        ).toHaveLength(0);
        expect(state.form.reset).not.toHaveBeenCalled();
    });
});
