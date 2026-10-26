import { Children, isValidElement } from 'react';
import { beforeEach, expect, it, vi } from 'vite-plus/test';
import { DocxPreview } from './docx-preview';

const state = vi.hoisted(() => ({
    states: [],
    effects: [],
    index: 0,
    refIndex: 0,
    document: null,
    fetch: vi.fn(),
    fonts: vi.fn(),
    render: vi.fn(),
}));
vi.mock('react', async (original) => ({
    ...(await original()),
    useState(initial) {
        const index = state.index++;
        if (!(index in state.states)) state.states[index] = initial;
        return [
            state.states[index],
            (next) => {
                state.states[index] = next;
            },
        ];
    },
    useRef: () => ({ current: state.refIndex++ === 0 ? { contentDocument: state.document } : [] }),
    useEffect: (effect) => state.effects.push(effect),
    useEffectEvent: (handler) => handler,
}));
vi.mock('@/lib/docx-preview', () => ({
    DOCX_PREVIEW_FRAME: '',
    fetchPreviewDocx: state.fetch,
    loadPreviewFonts: state.fonts,
    renderPreviewDocx: state.render,
}));
const nodes = (node) => (!isValidElement(node) ? [] : [node, ...Children.toArray(node.props.children).flatMap(nodes)]);
beforeEach(() => {
    vi.clearAllMocks();
    state.states = [true];
    state.index = 0;
    state.refIndex = 0;
    state.effects = [];
    state.document = { body: { dataset: {} }, fonts: { clear: vi.fn() } };
    state.fetch.mockResolvedValue(new Uint8Array([80, 75]));
    state.fonts.mockResolvedValue([]);
});
function documentComponent(onReady) {
    const tree = DocxPreview({ url: '/preview', maxBytes: 1000, draft: true, onReady });
    const child = nodes(tree).find((node) => typeof node.type === 'function' && node.type.name === 'PreviewDocument');
    state.states = [true];
    state.index = 0;
    state.refIndex = 0;
    state.effects = [];
    child.type(child.props);
    return state.effects[0]();
}
it('acknowledges the document only after rendering and marks the print document as draft', async () => {
    let finish;
    state.render.mockReturnValue(
        new Promise((resolve) => {
            finish = resolve;
        }),
    );
    const onReady = vi.fn().mockResolvedValue(undefined);
    const cleanup = documentComponent(onReady);
    await vi.waitFor(() => expect(state.render).toHaveBeenCalled());
    expect(onReady).not.toHaveBeenCalled();
    finish([{}]);
    await vi.waitFor(() => expect(onReady).toHaveBeenCalledOnce());
    expect(state.document.body.dataset.draft).toBe('true');
    cleanup();
    expect(state.document.fonts.clear).toHaveBeenCalled();
});
it('does not acknowledge an unsuccessful render', async () => {
    state.render.mockRejectedValue(new Error('invalid docx'));
    const onReady = vi.fn();
    const cleanup = documentComponent(onReady);
    await vi.waitFor(() => expect(state.states[2]?.error).toBe('invalid docx'));
    expect(onReady).not.toHaveBeenCalled();
    cleanup();
});
it('does not acknowledge a render that finished after the viewer closed', async () => {
    let finish;
    state.render.mockReturnValue(
        new Promise((resolve) => {
            finish = resolve;
        }),
    );
    const onReady = vi.fn();
    const cleanup = documentComponent(onReady);
    await vi.waitFor(() => expect(state.render).toHaveBeenCalled());
    cleanup();
    finish([{}]);
    await Promise.resolve();
    await Promise.resolve();
    expect(onReady).not.toHaveBeenCalled();
});
