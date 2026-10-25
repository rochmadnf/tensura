import { TableActionButton } from '@/components/table-action-button';
import { TableHead } from '@/components/ui/table';
import { Switch } from '@/components/ui/switch';
import { Children, isValidElement } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vite-plus/test';
import { LetterTemplateConfigurationForm } from './configuration-form';
import { ConfigurationVariableRow } from './configuration-variable-row';
import { ConfigurationSlotRow } from './configuration-slot-row';
import { ConfigurationVariableDetails } from './configuration-variable';
import { ConfigurationAttributeTable } from './configuration-attribute-table';
import { AddConfigurationItem, EditConfigurationItem, DeleteConfigurationItem } from './configuration-item-actions';
import { FormActions } from '@/components/form-actions';
import { OptionSelect } from '@/components/option-select';
import { FormCard } from '@/components/form-card';
import { LetterTemplateDocumentForm } from './document-form';

const state = vi.hoisted(() => ({ submit: vi.fn(() => true), form: null, visit: vi.fn() }));
vi.mock('react', async (importOriginal) => ({
    ...(await importOriginal()),
    useEffect: vi.fn(),
    useRef: () => ({ current: null }),
    useState: (initial) => [initial, vi.fn()],
}));
vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...(await importOriginal()),
    router: { visit: state.visit },
    useForm: () => ({ processing: false, errors: {}, post: vi.fn() }),
}));
vi.mock('@/hooks/use-valibot-form', () => ({
    useValibotForm: (_schema, values) => {
        state.form ??= {
            data: structuredClone(values),
            errors: {},
            processing: false,
            setData: vi.fn((key, value) => {
                state.form.data[key] = value;
            }),
            clearErrors: vi.fn(),
        };
        return { form: state.form, submit: state.submit };
    },
}));

function findAll(node, predicate) {
    if (!isValidElement(node)) return [];
    return [
        ...(predicate(node) ? [node] : []),
        ...Children.toArray(node.props.children).flatMap((child) => findAll(child, predicate)),
    ];
}
const byType = (node, type) => findAll(node, (item) => item.type === type);
const variable = (id = 'var-one') => ({
    id,
    name: 'tujuan',
    label: 'Tujuan',
    type: 1,
    is_required: false,
    option_source: null,
    reference_group: null,
    is_multiple: false,
    default_text: null,
    employee_attributes: [],
    columns: [],
});
const options = {
    types: [
        { id: 1, label: 'Teks' },
        { id: 4, label: 'Combobox' },
    ],
    sources: [],
    employees: [],
    referenceGroups: [],
    columns: {},
    employeeAttributes: [
        { id: 'name', suffix: 'nama', label: 'Nama', type: 'text' },
        { id: 'raw_name', suffix: 'nama_tanpa_gelar', label: 'Nama tanpa gelar', type: 'text' },
        { id: 'join_date', suffix: 'tanggal_bergabung', label: 'Tanggal Bergabung', type: 'date' },
    ],
};
function manager(overrides = {}) {
    return LetterTemplateConfigurationForm({
        templateId: 'template',
        configuration: {
            id: 'version',
            editable: true,
            variables: [variable(), { ...variable('var-two'), name: 'lain' }],
            signer_slots: [{ id: 'slot-one', placeholder: 'penandatangan', candidates: [], employee_attributes: [] }],
            warnings: [],
        },
        options,
        disabled: false,
        activeEditor: null,
        onEdit: vi.fn(),
        onClose: vi.fn(),
        onProcessingChange: vi.fn(),
        ...overrides,
    });
}
const rowProps = () => ({
    variable: variable(),
    index: 0,
    options,
    templateId: 'template',
    versionId: 'version',
    disabled: false,
    onClose: vi.fn(),
    onProcessingChange: vi.fn(),
});

beforeEach(() => {
    state.form = null;
    state.submit.mockClear();
    state.visit.mockClear();
});

describe('Per-item configuration editing', () => {
    it('separates form and signer cards and starts with read-only rows', () => {
        const page = manager();
        expect(byType(page, FormCard).map((card) => card.props.title)).toEqual([
            'Konfigurasi Formulir Surat',
            'Penandatangan',
        ]);
        expect(byType(page, ConfigurationVariableRow)).toHaveLength(0);
        expect(byType(page, ConfigurationSlotRow)).toHaveLength(0);
        expect(byType(page, FormActions)).toHaveLength(0);
    });
    it('places the current example download once in the DOCX header beside Edit', () => {
        const page = LetterTemplateDocumentForm({
            templateId: 'template',
            document: { id: 'version', source: null, state: 'pending', errors: [], placeholders: [] },
            editable: true,
            maxKb: 10240,
            versions: [],
            disabled: false,
            onProcessingChange: vi.fn(),
            editing: false,
            onEdit: vi.fn(),
            onClose: vi.fn(),
        });
        const card = byType(page, FormCard)[0];
        const headerLinks = findAll(card.props.action, (item) => item.props.render?.type === 'a');
        expect(headerLinks).toHaveLength(1);
        expect(headerLinks[0].props.render.props.href).toContain('/example');
        expect(findAll(page, (item) => item.props.render?.props.href?.includes('/example'))).toHaveLength(0);
    });
    it('opens only the selected variable and blocks other mutations', () => {
        const page = manager({ activeEditor: 'variable:var-two' });
        expect(byType(page, ConfigurationVariableRow).map((row) => row.props.variable.id)).toEqual(['var-two']);
        expect(byType(page, EditConfigurationItem).every((item) => item.props.disabled)).toBe(true);
        expect(byType(page, DeleteConfigurationItem).every((item) => item.props.disabled)).toBe(true);
        expect(byType(page, ConfigurationSlotRow)).toHaveLength(0);
    });
    it('adds a temporary row only after choosing Add', () => {
        const onEdit = vi.fn();
        const page = manager({ onEdit });
        const add = byType(page, FormCard)[0].props.action.props.children;
        expect(add.type).toBe(AddConfigurationItem);
        add.props.onClick();
        expect(onEdit).toHaveBeenCalledWith('variable:new');
        const editing = manager({ activeEditor: 'variable:new' });
        expect(byType(editing, ConfigurationVariableRow)).toHaveLength(1);
        expect(byType(editing, ConfigurationVariableRow)[0].props.variable.id).toBeNull();
        expect(state.visit).not.toHaveBeenCalled();
    });
    it('cancels local edits without a request or changing the saved row', () => {
        const props = rowProps();
        const row = ConfigurationVariableRow(props);
        findAll(row, (item) => item.props.id === 'variable-form-var-one-name')[0].props.onChange({
            target: { value: 'diubah' },
        });
        byType(row, FormActions)[0].props.onCancel();
        expect(props.onClose).toHaveBeenCalledOnce();
        expect(props.variable.name).toBe('tujuan');
        expect(state.submit).not.toHaveBeenCalled();
        expect(state.visit).not.toHaveBeenCalled();
    });
    it('submits the scoped item route and closes only on success', () => {
        const props = rowProps();
        const row = ConfigurationVariableRow(props);
        findAll(row, (item) => item.type === 'form')[0].props.onSubmit({ preventDefault: vi.fn() });
        expect(state.submit).toHaveBeenCalledWith(
            'put',
            expect.stringContaining('/variables/var-one'),
            expect.any(Object),
        );
        expect(props.onClose).not.toHaveBeenCalled();
        state.submit.mock.calls[0][2].onError();
        expect(props.onClose).not.toHaveBeenCalled();
        state.submit.mock.calls[0][2].onSuccess();
        expect(props.onClose).toHaveBeenCalledOnce();
    });
    it('clears incompatible source settings when the variable type changes', () => {
        const props = rowProps();
        props.variable = {
            ...variable(),
            type: 4,
            option_source: 2,
            employee_attributes: [{ source_attribute: 'nip', default_text: '-', date_format: null }],
        };
        const row = ConfigurationVariableRow(props);
        byType(row, OptionSelect)[0].props.onValueChange('1');
        expect(state.form.data.item).toMatchObject({
            type: 1,
            option_source: null,
            columns: [],
            employee_attributes: [],
            default_text: null,
        });
    });
    it.each([
        [false, 'Per atribut'],
        [true, 'Tidak berlaku'],
    ])('prevents scalar fallback editing for employee/multiple rows (%s)', (multiple, label) => {
        const row = ConfigurationVariableRow({
            ...rowProps(),
            variable: { ...variable(), type: 4, option_source: 2, is_multiple: multiple },
        });
        expect(findAll(row, (item) => item.props.id === 'variable-form-var-one-fallback')).toHaveLength(0);
        expect(byType(row, ConfigurationVariableDetails)).toHaveLength(1);
        expect(findAll(row, (item) => item.props.children === label)).toHaveLength(1);
    });
    it('keeps source, group and repeated columns in the inline detail editor', () => {
        const detail = ConfigurationVariableDetails({
            variable: { ...variable(), type: 4, option_source: 1, is_multiple: true },
            options,
            disabled: false,
            errors: {},
            onChange: vi.fn(),
        });
        const names = byType(detail, OptionSelect).map((item) => item.props.name);
        expect(names).toContain('item.option_source');
        expect(names).toContain('item.reference_group');
    });
    it('has per-attribute edit actions without order or delete actions and keeps labels distinct', () => {
        const table = ConfigurationAttributeTable({
            templateId: 'template',
            versionId: 'version',
            ownerId: 'owner',
            kind: 'slot',
            prefix: 'penandatangan',
            attributes: options.employeeAttributes,
            settings: [],
            editable: true,
            activeEditor: null,
            disabled: false,
            onEdit: vi.fn(),
            onClose: vi.fn(),
            onProcessingChange: vi.fn(),
        });
        expect(byType(table, EditConfigurationItem).map((item) => item.props.label)).toContain(
            'teks alternatif Nama beserta gelar',
        );
        expect(byType(table, EditConfigurationItem).map((item) => item.props.label)).toContain(
            'teks alternatif Nama tanpa gelar',
        );
        expect(byType(table, DeleteConfigurationItem)).toHaveLength(0);
        expect(byType(table, AddConfigurationItem)).toHaveLength(0);
    });
});

describe('Editor UI regressions', () => {
    it('disables only the unavailable directions at both ends of the question list', () => {
        const actions = byType(manager(), TableActionButton);
        const up = actions.filter((action) => action.props.tooltip === 'Naikkan');
        const down = actions.filter((action) => action.props.tooltip === 'Turunkan');
        expect(up.map((action) => action.props.disabled)).toEqual([true, false]);
        expect(down.map((action) => action.props.disabled)).toEqual([false, true]);
    });
    it('removes action columns entirely from a published configuration', () => {
        const page = manager({
            configuration: { id: 'version', editable: false, variables: [variable()], signer_slots: [], warnings: [] },
        });
        expect(byType(page, TableHead).some((head) => head.props.children === 'Aksi')).toBe(false);
        expect(byType(page, TableActionButton)).toHaveLength(0);
    });
    it('uses a switch whose value controls whether the question is required', () => {
        const row = ConfigurationVariableRow(rowProps());
        const control = byType(row, Switch)[0];
        expect(control.props.checked).toBe(false);
        control.props.onCheckedChange(true);
        expect(state.form.data.item.is_required).toBe(true);
    });
});
