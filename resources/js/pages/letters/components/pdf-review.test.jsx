import { Children, isValidElement } from 'react';
import { beforeEach, expect, it, vi } from 'vite-plus/test';
import { LetterPdfReview } from './pdf-review';
import { PdfPreview } from '@/components/pdf-preview';
import { Button } from '@/components/ui/button';
const state = vi.hoisted(() => ({ values: [], index: 0, post: vi.fn(), transform: vi.fn(), reload: vi.fn() }));
vi.mock('react', async (original) => ({
    ...(await original()),
    useState(initial) {
        const index = state.index++;
        if (!(index in state.values)) state.values[index] = initial;
        return [
            state.values[index],
            (value) => {
                state.values[index] = value;
            },
        ];
    },
}));
vi.mock('@inertiajs/react', () => ({
    router: { reload: state.reload },
    useHttp: () => ({ processing: false, errors: {}, post: state.post, transform: state.transform }),
}));
vi.mock('@/hooks/use-letter-pdf', () => ({
    useLetterPdf: () => ({ operation: null, working: false, generate: vi.fn(), error: '' }),
}));
const nodes = (node) => (!isValidElement(node) ? [] : [node, ...Children.toArray(node.props.children).flatMap(nodes)]);
function render() {
    state.index = 0;
    return nodes(
        LetterPdfReview({
            letterId: 'letter',
            revision: 1,
            pdf: {
                source_id: 'word',
                artifact: { id: 'pdf', fingerprint: 'proof', confirmed: false },
                max_bytes: 1000,
            },
            disabled: false,
            onOpenChange: vi.fn(),
        }),
    ).find((node) => node.type === PdfPreview);
}
beforeEach(() => {
    state.values = [];
    vi.clearAllMocks();
    state.post.mockResolvedValue({});
});
it('requires completed PDF rendering and stores PDF-specific human confirmation', async () => {
    let viewer = render();
    expect(nodes(viewer.props.footer).find((node) => node.type === Button).props.disabled).toBe(true);
    await viewer.props.onReady();
    expect(state.post).toHaveBeenCalledWith('/letters/letter/previews/pdf/rendered');
    expect(state.transform.mock.calls[0][0]()).toEqual({
        revision: 1,
        fingerprint: 'proof',
        preview_kind: 'pdf',
        confirmed: true,
    });
    viewer = render();
    const confirm = nodes(viewer.props.footer).find((node) => node.type === Button);
    expect(confirm.props.disabled).toBe(false);
    await confirm.props.onClick();
    await Promise.resolve();
    expect(state.post).toHaveBeenCalledWith('/letters/letter/previews/pdf/confirm');
    expect(state.reload).toHaveBeenCalledWith({ only: ['pdf', 'preview', 'audits'], showProgress: false });
});
it('keeps confirmation disabled when acknowledgment fails', async () => {
    state.post.mockRejectedValueOnce(new Error('stale'));
    await expect(render().props.onReady()).rejects.toThrow('stale');
    expect(nodes(render().props.footer).find((node) => node.type === Button).props.disabled).toBe(true);
});
