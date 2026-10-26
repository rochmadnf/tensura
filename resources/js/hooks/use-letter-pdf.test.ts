import { beforeEach, expect, it, vi } from 'vite-plus/test';
import { useLetterPdf } from './use-letter-pdf';
import type { LetterOperation, LetterPdf } from '@/schemas/letter-preview';
const state = vi.hoisted(() => ({
    values: [] as unknown[],
    refs: [] as { current: unknown }[],
    index: 0,
    refIndex: 0,
    effects: [] as (() => void | (() => void))[],
    post: vi.fn(),
    reload: vi.fn(),
    transform: vi.fn(),
}));
vi.mock('react', async (original) => ({
    ...(await original()),
    useState(initial: unknown) {
        const index = state.index++;
        if (!(index in state.values)) state.values[index] = initial;
        return [
            state.values[index],
            (value: unknown) => {
                state.values[index] = typeof value === 'function' ? value(state.values[index]) : value;
            },
        ];
    },
    useRef(initial: unknown) {
        return (state.refs[state.refIndex++] ??= { current: initial });
    },
    useEffect: (effect: () => void | (() => void)) => state.effects.push(effect),
    useCallback: (callback: unknown) => callback,
}));
vi.mock('@inertiajs/react', () => ({
    router: { reload: state.reload },
    useHttp: () => ({ processing: false, errors: {}, post: state.post, transform: state.transform }),
}));
const pdf: LetterPdf = { source_id: 'word', operation: null, artifact: null, reason: null, max_bytes: 100 };
const operation = (revision: number, status: LetterOperation['status']): LetterOperation => ({
    id: 'op',
    letter_id: 'letter',
    source_id: 'word',
    subject: 'Surat',
    kind: 'pdf',
    revision: 1,
    status,
    status_label: status,
    status_revision: revision,
    message: null,
    updated_at: '',
});
function render() {
    state.index = 0;
    state.refIndex = 0;
    state.effects = [];
    return useLetterPdf('letter', pdf);
}
beforeEach(() => {
    state.values = [];
    state.refs = [];
    vi.clearAllMocks();
    vi.stubGlobal('window', new EventTarget());
});
it('does not request PDF on mount and reuses the key after a lost response', async () => {
    state.post.mockRejectedValue(new Error('network'));
    let result = render();
    expect(state.post).not.toHaveBeenCalled();
    await result.generate();
    const first = state.transform.mock.calls[0][0]();
    result = render();
    await result.generate();
    expect(state.transform.mock.calls[1][0]()).toEqual(first);
    expect(state.post).toHaveBeenCalledWith('/letters/letter/artifacts/word/pdf');
});
it('ignores other sources and does not replace terminal SSE state with a late queued response', async () => {
    let resolve!: (value: unknown) => void;
    state.post.mockImplementation(
        () =>
            new Promise((done) => {
                resolve = done;
            }),
    );
    const result = render();
    const cleanups = state.effects.map((effect) => effect());
    const pending = result.generate();
    window.dispatchEvent(
        new CustomEvent('letter-operation', { detail: { ...operation(3, 'succeeded'), source_id: 'other' } }),
    );
    expect(state.reload).not.toHaveBeenCalled();
    window.dispatchEvent(new CustomEvent('letter-operation', { detail: operation(3, 'succeeded') }));
    resolve({ operation: operation(1, 'queued') });
    await pending;
    expect(render().operation?.status).toBe('succeeded');
    expect(state.reload).toHaveBeenCalledWith({ only: ['pdf', 'audits'], showProgress: false });
    cleanups.forEach((cleanup) => cleanup?.());
});
