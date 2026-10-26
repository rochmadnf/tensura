import { Children, isValidElement } from 'react';
import { beforeEach, expect, it, vi } from 'vite-plus/test';
import { LetterPreviewPanel } from './preview-panel';
import { DocxPreview } from '@/components/docx-preview';
import { Button } from '@/components/ui/button';

const state = vi.hoisted(() => ({
    values: [],
    index: 0,
    http: 0,
    effects: [],
    generate: vi.fn(),
    check: vi.fn(),
    reload: vi.fn(),
    transform: vi.fn(),
}));
vi.mock('react', async (original) => ({
    ...(await original()),
    useState(initial) {
        const index = state.index++;
        if (!(index in state.values)) state.values[index] = initial;
        return [
            state.values[index],
            (value) => {
                state.values[index] = typeof value === 'function' ? value(state.values[index]) : value;
            },
        ];
    },
    useEffect: (effect) => state.effects.push(effect),
}));
vi.mock('@inertiajs/react', () => ({
    router: { reload: state.reload },
    useHttp: () => ({
        processing: false,
        errors: {},
        transform: state.transform,
        post: state.http++ % 2 === 0 ? state.generate : state.check,
    }),
}));
vi.mock('@/components/ui/goey-toaster', () => ({ gooeyToast: { success: vi.fn() } }));
const nodes = (node) => (!isValidElement(node) ? [] : [node, ...Children.toArray(node.props.children).flatMap(nodes)]);
const preview = {
    revision: 1,
    operation: null,
    artifact: { id: 'artifact', fingerprint: 'proof', confirmed: false, font_report: {}, expires_at: '2026-10-27' },
    can_generate: true,
    reason: null,
    max_bytes: 1000,
};
const props = { letterId: 'letter', revision: 1, preview, disabled: false, onOpenChange: vi.fn() };
function render(overrides = {}) {
    state.index = 0;
    state.http = 0;
    state.effects = [];
    return nodes(LetterPreviewPanel({ ...props, ...overrides }));
}
beforeEach(() => {
    vi.clearAllMocks();
    state.values = [];
    state.effects = [];
    state.generate.mockResolvedValue({ operation: { id: 'operation', revision: 1, status: 'queued' } });
    state.check.mockResolvedValue({});
});
it('requires viewer-ready acknowledgment before human confirmation and only refreshes preview metadata', async () => {
    let viewer = render().find((node) => node.type === DocxPreview);
    expect(viewer.props.footer.props.disabled).toBe(true);
    await viewer.props.onReady();
    expect(state.check).toHaveBeenCalledWith(expect.stringContaining('/rendered'));
    viewer = render().find((node) => node.type === DocxPreview);
    expect(viewer.props.footer.props.disabled).toBe(false);
    await viewer.props.footer.props.onClick();
    await Promise.resolve();
    expect(state.check).toHaveBeenCalledWith(expect.stringContaining('/confirm'));
    expect(state.reload).toHaveBeenCalledWith({ only: ['preview', 'audits'], showProgress: false });
});
it('does not acknowledge a failed render request and keeps confirmation disabled', async () => {
    state.check.mockRejectedValueOnce(new Error('stale'));
    const viewer = render().find((node) => node.type === DocxPreview);
    await expect(viewer.props.onReady()).rejects.toThrow('stale');
    expect(render().find((node) => node.type === DocxPreview).props.footer.props.disabled).toBe(true);
});
it('blocks generation while editing, when a worker is active, or when the visible draft is stale', () => {
    for (const overrides of [
        { disabled: true },
        { preview: { ...preview, revision: 2 } },
        { preview: { ...preview, operation: { id: 'op', revision: 1, status: 'running' } } },
    ]) {
        state.values = [];
        const button = render(overrides).find((node) => node.type === Button);
        expect(button.props.disabled).toBe(true);
    }
});
it('requests preview without navigating and only reloads preview props', async () => {
    const button = render().find((node) => node.type === Button);
    button.props.onClick();
    await Promise.resolve();
    await Promise.resolve();
    expect(state.generate).toHaveBeenCalledWith('/letters/letter/previews');
    expect(state.reload).toHaveBeenCalledWith({ only: ['preview'], showProgress: false });
});
