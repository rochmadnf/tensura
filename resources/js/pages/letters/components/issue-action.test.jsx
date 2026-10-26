import { Children, isValidElement } from 'react';
import { beforeEach, expect, it, vi } from 'vite-plus/test';
import { LetterIssueAction } from './issue-action';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
const state = vi.hoisted(() => ({
    values: [],
    refs: [],
    index: 0,
    refIndex: 0,
    effects: [],
    post: vi.fn(),
    reload: vi.fn(),
    transform: vi.fn(),
    pending: vi.fn(),
}));
vi.mock('react', async (original) => ({
    ...(await original()),
    useState(initial) {
        const index = state.index++;
        if (!(index in state.values)) state.values[index] = initial;
        return [
            state.values[index],
            (next) => {
                state.values[index] = typeof next === 'function' ? next(state.values[index]) : next;
            },
        ];
    },
    useRef(initial) {
        const index = state.refIndex++;
        return (state.refs[index] ??= { current: initial });
    },
    useEffect: (effect) => state.effects.push(effect),
    useCallback: (callback) => callback,
}));
vi.mock('@inertiajs/react', () => ({
    router: { reload: state.reload },
    useHttp: () => ({
        processing: false,
        errors: {},
        post: state.post,
        transform: state.transform,
        clearErrors: vi.fn(),
    }),
}));
const nodes = (node) => (!isValidElement(node) ? [] : [node, ...Children.toArray(node.props.children).flatMap(nodes)]);
const props = {
    letterId: 'letter',
    revision: 1,
    preview: { revision: 1, artifact: { id: 'artifact', fingerprint: 'fingerprint', confirmed: true } },
    issuance: {
        operation: null,
        crossed_month: false,
        date: '2026-10-26',
        period: '2026-10',
        publication_month: '2026-10',
    },
    disabled: false,
    onOpenChange: vi.fn(),
    onPendingChange: state.pending,
};
function render(overrides = {}) {
    state.index = 0;
    state.refIndex = 0;
    state.effects = [];
    return nodes(LetterIssueAction({ ...props, ...overrides }));
}
beforeEach(() => {
    vi.clearAllMocks();
    state.values = [];
    state.refs = [];
    state.post.mockResolvedValue({
        operation: { id: 'op', letter_id: 'letter', revision: 1, kind: 'issue', status: 'queued', status_revision: 1 },
    });
});
it('blocks issuance without the operators inspection, while editing, or for a stale displayed draft', () => {
    for (const overrides of [
        { disabled: true },
        { preview: { ...props.preview, artifact: { ...props.preview.artifact, confirmed: false } } },
        { preview: { ...props.preview, revision: 2 } },
    ]) {
        state.values = [];
        expect(render(overrides).find((node) => node.type === Button).props.disabled).toBe(true);
    }
});
it('requires an explicit keep-date choice after the draft crosses a month', () => {
    const overrides = { issuance: { ...props.issuance, crossed_month: true } };
    render(overrides)
        .find((node) => node.type === Button)
        .props.onClick();
    let tree = render(overrides);
    expect(tree.filter((node) => node.type === Button).at(-1).props.disabled).toBe(true);
    tree.find((node) => node.type === Checkbox).props.onCheckedChange(true);
    tree = render(overrides);
    expect(tree.filter((node) => node.type === Button).at(-1).props.disabled).toBe(false);
});
it('keeps the same idempotency key after a lost response and refreshes the publication state', async () => {
    state.post.mockRejectedValueOnce(new Error('network'));
    render()
        .find((node) => node.type === Button)
        .props.onClick();
    let tree = render();
    await tree
        .filter((node) => node.type === Button)
        .at(-1)
        .props.onClick();
    await Promise.resolve();
    const first = state.transform.mock.calls[0][0]();
    tree = render();
    await tree
        .filter((node) => node.type === Button)
        .at(-1)
        .props.onClick();
    await Promise.resolve();
    const second = state.transform.mock.calls[1][0]();
    expect(second.idempotency_key).toBe(first.idempotency_key);
    expect(second.artifact).toBe('artifact');
    expect(second.keep_period).toBe(true);
    expect(state.reload).toHaveBeenCalledWith({
        only: ['resource', 'issuance', 'preview', 'audits'],
        showProgress: false,
    });
});
