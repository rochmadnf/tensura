import { Children, isValidElement } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vite-plus/test';
import { LetterTemplateDocumentForm } from './document-form';
import { DocumentValidationResult } from './document-validation-result';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';

const state = vi.hoisted(() => ({ validation: null }));
vi.mock('react', async (original) => ({ ...(await original()), useRef: () => ({ current: null }) }));
vi.mock('@inertiajs/react', async (original) => ({ ...(await original()), useForm: () => state.validation }));
vi.mock('@/hooks/use-valibot-form', () => ({
    useValibotForm: () => ({ form: { errors: {}, processing: false }, submit: vi.fn() }),
}));
function findAll(node, predicate) {
    if (!isValidElement(node)) return [];
    return [
        ...(predicate(node) ? [node] : []),
        ...Children.toArray(node.props.children).flatMap((child) => findAll(child, predicate)),
    ];
}
const text = (node) =>
    !isValidElement(node)
        ? typeof node === 'string' || typeof node === 'number'
            ? String(node)
            : ''
        : Children.toArray(node.props.children).map(text).join('');
const props = (overrides = {}) => ({
    templateId: 'template',
    editable: true,
    maxKb: 20480,
    versions: [],
    disabled: false,
    editing: false,
    onProcessingChange: vi.fn(),
    onEdit: vi.fn(),
    onClose: vi.fn(),
    document: {
        id: 'version',
        source: { name: 'surat.docx', size: 2000 },
        state: 'invalid',
        errors: ['Variabel belum terdaftar.'],
        placeholders: ['tujuan'],
    },
    ...overrides,
});
beforeEach(() => {
    state.validation = { errors: {}, processing: false, post: vi.fn(), clearErrors: vi.fn() };
});

describe('DOCX validation feedback', () => {
    it('shows persistent errors once and preserves unrelated request errors', () => {
        state.validation.errors.docx = 'Variabel belum terdaftar.';
        const tree = LetterTemplateDocumentForm(props());
        const result = findAll(tree, (item) => item.type === DocumentValidationResult)[0];
        expect(text(tree)).not.toContain('Variabel belum terdaftar.');
        expect(text(DocumentValidationResult(result.props)).match(/Variabel belum terdaftar\./g)).toHaveLength(1);
        state.validation.errors.docx = 'Draft tidak dapat divalidasi.';
        expect(text(LetterTemplateDocumentForm(props()))).toContain('Draft tidak dapat divalidasi.');
    });
    it('clears stale request errors before another validation and blocks requests while busy', () => {
        const tree = LetterTemplateDocumentForm(props());
        findAll(tree, (item) => item.type === Button && text(item) === 'Validasi DOCX')[0].props.onClick();
        expect(state.validation.clearErrors).toHaveBeenCalledOnce();
        expect(state.validation.post).toHaveBeenCalledOnce();
        expect(state.validation.clearErrors.mock.invocationCallOrder[0]).toBeLessThan(
            state.validation.post.mock.invocationCallOrder[0],
        );
        state.validation.processing = true;
        const busy = findAll(
            LetterTemplateDocumentForm(props()),
            (item) => item.type === Button && text(item) === 'Memvalidasi...',
        )[0];
        expect(busy.props.disabled).toBe(true);
        busy.props.onClick();
        expect(state.validation.post).toHaveBeenCalledOnce();
    });
    it('counts unique variable names and provides an empty result after checking', () => {
        const result = DocumentValidationResult({ errors: [], names: ['tujuan', 'tujuan', 'pegawai'] });
        expect(findAll(result, (item) => item.type === Badge)).toHaveLength(2);
        expect(text(result)).toContain('Variabel dalam Dokumen · 2');
        expect(text(result)).toContain('${tujuan}');
        expect(text(DocumentValidationResult({ errors: [], names: [] }))).toContain(
            'Tidak ada variabel yang ditemukan',
        );
        const pending = props();
        pending.document.state = 'pending';
        expect(
            findAll(LetterTemplateDocumentForm(pending), (item) => item.type === DocumentValidationResult),
        ).toHaveLength(0);
    });
});
