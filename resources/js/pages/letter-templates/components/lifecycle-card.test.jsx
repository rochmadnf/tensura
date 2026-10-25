import { Children, isValidElement } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vite-plus/test';
import { LetterTemplateLifecycleCard, lifecycleOperationText } from './lifecycle-card';
import { LetterTemplateVersionTimeline } from './version-timeline';
import { LetterTemplateForm } from './letter-template-form';
import { Button } from '@/components/ui/button';
import { FormCard } from '@/components/form-card';
import { AlertDialog, AlertDialogFooter } from '@/components/ui/alert-dialog';

const state = vi.hoisted(() => ({ operation: null, form: null, data: null }));
vi.mock('react', async (importOriginal) => ({
    ...(await importOriginal()),
    useEffect: vi.fn(),
    useState: (initial) => [
        initial === null ? state.operation : initial,
        (value) => {
            state.operation = value;
        },
    ],
    useRef: () => ({ current: false }),
}));
vi.mock('@inertiajs/react', async (importOriginal) => ({ ...(await importOriginal()), useForm: () => state.form }));
vi.mock('@/hooks/use-valibot-form', () => ({
    useValibotForm: () => ({ form: { data: {}, processing: false }, submit: vi.fn(), bind: vi.fn() }),
}));

function findAll(node, predicate) {
    if (!isValidElement(node)) return [];
    return [
        ...(predicate(node) ? [node] : []),
        ...Children.toArray(node.props.children).flatMap((child) => findAll(child, predicate)),
    ];
}
const byType = (tree, type) => findAll(tree, (item) => item.type === type);
const text = (node) =>
    !isValidElement(node)
        ? typeof node === 'string' || typeof node === 'number'
            ? String(node)
            : ''
        : Children.toArray(node.props.children).map(text).join('');
const button = (tree, label) => byType(tree, Button).find((item) => text(item) === label);
const lifecycle = (overrides) => ({
    id: 'version',
    number: 2,
    timezone: 'Asia/Makassar',
    status: 1,
    status_label: 'Draft',
    editable: true,
    is_current: false,
    version_token: 'current-version',
    classification: { id: 'classification', code: '007', name: 'Klasifikasi versi ini' },
    first_used_at: null,
    readiness: { ready: true, checks: [] },
    can_archive: true,
    can_start_draft: false,
    draft_mode: 'copy',
    draft: { id: 'version', number: 2 },
    versions: [],
    audits: [],
    ...overrides,
});
const props = (overrides) => ({
    templateId: 'template',
    lifecycle: lifecycle(),
    testToken: 'current-result',
    isActive: true,
    disabled: false,
    onOpen: vi.fn(),
    onClose: vi.fn(),
    onProcessingChange: vi.fn(),
    ...overrides,
});
const confirmButton = (tree) => byType(byType(tree, AlertDialogFooter)[0], Button).find((item) => item.props.onClick);

beforeEach(() => {
    state.operation = null;
    state.data = null;
    state.form = {
        errors: {},
        processing: false,
        clearErrors: vi.fn(),
        transform: vi.fn((transform) => {
            state.data = transform({});
        }),
        submit: vi.fn(),
    };
});

describe('Publication and version controls', () => {
    it('keeps Publish disabled until all checks and a result token are available', () => {
        expect(
            button(
                LetterTemplateLifecycleCard(
                    props({ lifecycle: lifecycle({ readiness: { ready: false, checks: [] } }) }),
                ),
                'Publikasikan',
            ).props.disabled,
        ).toBe(true);
        expect(button(LetterTemplateLifecycleCard(props({ testToken: null })), 'Publikasikan').props.disabled).toBe(
            true,
        );
        expect(button(LetterTemplateLifecycleCard(props()), 'Publikasikan').props.disabled).toBe(false);
    });
    it('opens a confirmation without submitting and sends current version/result tokens after confirmation', () => {
        const current = props();
        button(LetterTemplateLifecycleCard(current), 'Publikasikan').props.onClick();
        expect(current.onOpen).toHaveBeenCalledOnce();
        expect(state.form.submit).not.toHaveBeenCalled();
        const updated = { ...current, lifecycle: lifecycle({ version_token: 'new-version' }), testToken: 'new-result' };
        confirmButton(LetterTemplateLifecycleCard(updated)).props.onClick();
        expect(state.data).toEqual({ confirmed: true, version_token: 'new-version', test_token: 'new-result' });
        expect(state.form.submit).toHaveBeenCalledWith(
            'post',
            expect.stringContaining('/versions/version/publish'),
            expect.any(Object),
        );
    });
    it('locks duplicate confirmations and retains the dialog on a failed request', () => {
        state.operation = 'publish';
        const current = props();
        const tree = LetterTemplateLifecycleCard(current);
        const confirm = confirmButton(tree);
        confirm.props.onClick();
        confirm.props.onClick();
        expect(state.form.submit).toHaveBeenCalledOnce();
        const options = state.form.submit.mock.calls[0][2];
        options.onError({ publication: 'Hasil uji kedaluwarsa.' });
        expect(current.onClose).not.toHaveBeenCalled();
        options.onFinish();
        expect(current.onProcessingChange).toHaveBeenCalledWith(false);
    });
    it('cancels the dialog without a request and releases the page editor lock', () => {
        state.operation = 'archive';
        const current = props();
        byType(LetterTemplateLifecycleCard(current), AlertDialog)[0].props.onOpenChange(false);
        expect(state.form.submit).not.toHaveBeenCalled();
        expect(current.onClose).toHaveBeenCalledOnce();
        expect(state.operation).toBeNull();
    });
    it('disables operations during another editor or request', () => {
        const tree = LetterTemplateLifecycleCard(props({ disabled: true }));
        expect(button(tree, 'Publikasikan').props.disabled).toBe(true);
        expect(button(tree, 'Arsipkan Draft').props.disabled).toBe(true);
        expect(button(tree, 'Nonaktifkan Templat').props.disabled).toBe(true);
    });
    it('changes activation explicitly without sending unrelated version or result data', () => {
        state.operation = 'activation';
        confirmButton(LetterTemplateLifecycleCard(props({ isActive: false }))).props.onClick();
        expect(state.data).toEqual({ confirmed: true, is_active: true });
        expect(state.form.submit).toHaveBeenCalledWith(
            'patch',
            expect.stringContaining('/activation'),
            expect.any(Object),
        );
    });
    it('explains reopening an unused publication versus copying a used or archived version', () => {
        const unused = lifecycleOperationText('draft', lifecycle({ draft_mode: 'reopen', is_current: true }), true);
        expect(unused.action).toBe('Ubah Versi Ini');
        expect(unused.description).toContain('nomor versinya tetap sama');
        expect(unused.description).toContain('tidak memiliki versi terbit aktif');
        expect(lifecycleOperationText('draft', lifecycle({ status: 2 }), true).action).toBe('Buat Draft Versi Baru');
        expect(lifecycleOperationText('draft', lifecycle({ status: 3 }), true).action).toBe('Buat Draft dari Arsip');
    });
    it('offers the existing draft rather than starting a second one', () => {
        const tree = LetterTemplateLifecycleCard(
            props({
                lifecycle: lifecycle({
                    id: 'history',
                    status: 2,
                    status_label: 'Terbit',
                    editable: false,
                    can_archive: false,
                }),
            }),
        );
        expect(button(tree, 'Publikasikan')).toBeUndefined();
        expect(button(tree, 'Arsipkan Draft')).toBeUndefined();
        expect(button(tree, 'Buat Draft Versi Baru')).toBeUndefined();
        expect(button(tree, 'Lanjutkan Draft Versi 2').props.render.props.href).toContain('version=version');
    });
    it('keeps historical identity read-only and shows its own classification despite another draft', () => {
        const tree = LetterTemplateForm({
            classifications: [{ id: 'draft-class', label: '999 — Klasifikasi draft' }],
            letterTemplate: {
                id: 'template',
                name: 'Surat Tugas',
                is_active: true,
                classification: null,
                draft: { id: 'draft', number: 3, letter_number: 'draft-class' },
            },
            selectedVersion: lifecycle({ editable: false, status_label: 'Terbit' }),
            editing: true,
            onEdit: vi.fn(),
        });
        expect(byType(tree, FormCard)[0].props.action).toBeUndefined();
        expect(findAll(tree, (item) => item.type === 'form')).toHaveLength(0);
        expect(text(tree)).toContain('007 — Klasifikasi versi ini');
        expect(text(tree)).not.toContain('999 — Klasifikasi draft');
    });
    it('renders timeline times in the configured application timezone', () => {
        const selected = lifecycle({
            versions: [{ id: 'version', number: 2, status_label: 'Draft', created_at: '2026-10-25T00:00:00Z' }],
        });
        const makassar = LetterTemplateVersionTimeline({
            templateId: 'template',
            lifecycle: selected,
            disabled: false,
        });
        const jakarta = LetterTemplateVersionTimeline({
            templateId: 'template',
            lifecycle: { ...selected, timezone: 'Asia/Jakarta' },
            disabled: false,
        });
        expect(text(makassar)).toContain('08.00');
        expect(text(jakarta)).toContain('07.00');
    });
    it('links timeline rows to their version and blocks navigation while editing', () => {
        const tree = LetterTemplateVersionTimeline({
            templateId: 'template',
            lifecycle: lifecycle({
                versions: [
                    { id: 'version', number: 2, status_label: 'Draft' },
                    { id: 'history', number: 1, status_label: 'Terbit' },
                ],
            }),
            disabled: true,
        });
        const history = button(tree, 'Lihat Versi 1');
        expect(history.props.render.props.href).toContain('version=history');
        expect(history.props.disabled).toBe(true);
    });
});
