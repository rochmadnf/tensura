import { Children, isValidElement } from 'react';
import { beforeEach, expect, it, vi } from 'vite-plus/test';
import { LetterLifecycleActions } from './lifecycle-actions';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
const state = vi.hoisted(() => ({ values: [], index: 0, data: {}, transform: null, posted: null, post: vi.fn() }));
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
    useForm: () => ({
        get data() {
            return state.data;
        },
        errors: {},
        processing: false,
        clearErrors: vi.fn(),
        setData(key, value) {
            state.data = typeof key === 'string' ? { ...state.data, [key]: value } : key;
        },
        transform(callback) {
            state.transform = callback;
        },
        post(url) {
            state.post(url);
            state.posted = state.transform(state.data);
        },
    }),
}));
const nodes = (node) => (!isValidElement(node) ? [] : [node, ...Children.toArray(node.props.children).flatMap(nodes)]);
const text = (node) =>
    !isValidElement(node)
        ? typeof node === 'string'
            ? node
            : ''
        : Children.toArray(node.props.children).map(text).join('');
const props = {
    letterId: 'letter',
    status: 2,
    lifecycle: { base_version: 'base', state_token: 'opened-state', has_draft: false },
    disabled: false,
    onOpenChange: vi.fn(),
};
function render(overrides = {}) {
    state.index = 0;
    return nodes(LetterLifecycleActions({ ...props, ...overrides }));
}
beforeEach(() => {
    state.values = [];
    state.data = {};
    state.posted = null;
    vi.clearAllMocks();
});
it('binds cancellation to the state shown when its dialog opened, even if newer props arrive', () => {
    render()
        .find((node) => node.type === Button && text(node) === 'Batalkan surat')
        .props.onClick();
    render()
        .find((node) => node.type === Textarea)
        .props.onChange({ target: { value: 'Tidak berlaku' } });
    const tree = render({ lifecycle: { ...props.lifecycle, state_token: 'new-state', base_version: 'new-version' } });
    tree.find((node) => node.type === Button && text(node).startsWith('Ya,')).props.onClick();
    expect(state.post).toHaveBeenCalledWith('/letters/letter/cancel');
    expect(state.posted).toEqual({
        state_token: 'opened-state',
        base_version: 'base',
        reason: 'Tidak berlaku',
        confirmed: true,
    });
});
it('offers recovery and draft disposal for cancelled letters without offering another cancellation', () => {
    const tree = render({ status: 3, lifecycle: { ...props.lifecycle, has_draft: true, draft_kind: 3 } });
    const labels = tree.filter((node) => node.type === Button).map(text);
    expect(labels).toContain('Buang draft');
    expect(labels).not.toContain('Batalkan surat');
    expect(labels).not.toContain('Pulihkan surat');
});
