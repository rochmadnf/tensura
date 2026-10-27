import { afterEach, beforeEach, describe, expect, it, vi } from 'vite-plus/test';
import { NotificationProvider, useNotifications } from './letter-operation-notifications';

const harness = vi.hoisted(() => ({
    values: [],
    index: 0,
    effects: [],
    context: null,
    request: vi.fn(),
    connect: vi.fn(),
    visit: vi.fn(),
}));
vi.mock('react', async (original) => ({
    ...(await original()),
    useState(initial) {
        const index = harness.index++;
        if (!(index in harness.values)) harness.values[index] = typeof initial === 'function' ? initial() : initial;
        return [harness.values[index], vi.fn()];
    },
    useEffect: (effect) => harness.effects.push(effect),
    useContext: () => harness.context,
    useSyncExternalStore: (_subscribe, snapshot) => snapshot(),
}));
vi.mock('@inertiajs/react', () => ({
    http: { getClient: () => ({ request: harness.request }) },
    router: { visit: harness.visit },
}));
vi.mock('@/lib/letter-operation-stream', () => ({ connectLetterOperations: harness.connect }));

const cleanups = [];
const flush = async () => {
    await Promise.resolve();
    await Promise.resolve();
    await Promise.resolve();
};
function mount(userId = 'first-account') {
    harness.values = [];
    harness.index = 0;
    harness.effects = [];
    const provider = NotificationProvider({ children: null, userId });
    harness.context = provider.props.value;
    const effect = harness.effects[0];
    const cleanup = effect();
    if (cleanup) cleanups.push(cleanup);
    return { effect, cleanup, controller: harness.context.controller };
}

beforeEach(() => {
    vi.useFakeTimers();
    vi.clearAllMocks();
    harness.request.mockResolvedValue({ status: 200, data: JSON.stringify({ unread_count: 2 }), headers: {} });
    harness.connect.mockImplementation(() => vi.fn());
    vi.stubGlobal('window', new EventTarget());
    const document = new EventTarget();
    document.visibilityState = 'visible';
    vi.stubGlobal('document', document);
});
afterEach(() => {
    cleanups.splice(0).forEach((cleanup) => cleanup());
    vi.unstubAllGlobals();
    vi.useRealTimers();
});

describe('notification provider integration', () => {
    it('owns one shared stream, forwards operation events, and only invalidates notification state', async () => {
        mount();
        await flush();
        expect(harness.connect).toHaveBeenCalledTimes(1);
        const callbacks = harness.connect.mock.calls[0][0];
        expect(Object.keys(callbacks).sort()).toEqual([
            'onConnection',
            'onNewNotification',
            'onNotifications',
            'onOperation',
            'url',
        ]);
        const operation = { id: 'op', status: 'succeeded' };
        const receive = vi.fn();
        window.addEventListener('letter-operation', receive);
        callbacks.onOperation(operation);
        expect(receive.mock.calls[0][0].detail).toBe(operation);
        callbacks.onNotifications();
        callbacks.onNotifications();
        callbacks.onNotifications();
        await vi.advanceTimersByTimeAsync(100);

        expect(harness.request).toHaveBeenCalledTimes(2);
        expect(harness.request.mock.calls.every(([config]) => config.url === '/notifications/summary')).toBe(true);
        expect(useNotifications()).toMatchObject({ open: false, unreadCount: 2 });
        expect(harness.visit).not.toHaveBeenCalled();
        expect(useNotifications().arrivalRevision).toBe(0);
    });

    it('signals the bell only for explicit new arrivals and stops alerts with the account provider', async () => {
        const { cleanup } = mount();
        await flush();
        const callbacks = harness.connect.mock.calls[0][0];
        callbacks.onNotifications();
        callbacks.onConnection(true);
        expect(useNotifications().arrivalRevision).toBe(0);

        callbacks.onNewNotification('11111111-1111-4111-8111-111111111111');
        callbacks.onNewNotification('11111111-1111-4111-8111-111111111111');
        expect(useNotifications().arrivalRevision).toBe(1);
        cleanup();
        vi.advanceTimersByTime(2000);
        callbacks.onNewNotification('22222222-2222-4222-8222-222222222222');
        expect(useNotifications().arrivalRevision).toBe(1);
    });

    it('refreshes on reconnect, focus and visible but ignores hidden visibility changes', async () => {
        mount();
        const callbacks = harness.connect.mock.calls[0][0];
        callbacks.onConnection(false);
        expect(useNotifications().connected).toBe(false);
        callbacks.onConnection(true);
        window.dispatchEvent(new Event('focus'));
        document.dispatchEvent(new Event('visibilitychange'));
        await vi.advanceTimersByTimeAsync(100);
        expect(harness.request).toHaveBeenCalledTimes(2);
        document.visibilityState = 'hidden';
        document.dispatchEvent(new Event('visibilitychange'));
        await vi.advanceTimersByTimeAsync(100);
        expect(harness.request).toHaveBeenCalledTimes(2);
        document.visibilityState = 'visible';
        document.dispatchEvent(new Event('visibilitychange'));
        await vi.advanceTimersByTimeAsync(100);

        expect(harness.request).toHaveBeenCalledTimes(3);
        expect(useNotifications().connected).toBe(true);
    });

    it('uses generated list routes and PATCH requests through the CSRF-aware Inertia client', async () => {
        const { controller } = mount();
        await flush();
        harness.request.mockImplementation(async ({ url }) => ({
            status: 200,
            headers: {},
            data: JSON.stringify(
                url.includes('?') ? { data: [], unread_count: 2, next_cursor: 'cursor/next' } : { unread_count: 1 },
            ),
        }));
        controller.setOpen(true);
        await flush();
        controller.setFilter('unread');
        await flush();
        controller.loadMore();
        await flush();
        await controller.markRead('notice');
        await controller.markRead(null);
        const configs = harness.request.mock.calls.map(([config]) => config);

        expect(configs).toContainEqual(expect.objectContaining({ url: '/notifications?filter=all', method: 'get' }));
        expect(configs).toContainEqual(
            expect.objectContaining({ url: '/notifications?filter=unread&cursor=cursor%2Fnext', method: 'get' }),
        );
        expect(configs).toContainEqual(expect.objectContaining({ url: '/notifications/notice/read', method: 'patch' }));
        expect(configs).toContainEqual(expect.objectContaining({ url: '/notifications/read-all', method: 'patch' }));
        expect(configs.every((config) => config.signal instanceof AbortSignal)).toBe(true);
        expect(configs.every((config) => Object.keys(config.headers).join() === 'Accept')).toBe(true);
    });

    it('cancels the previous account requests, timers and stream before starting fresh state', async () => {
        let finish;
        harness.request.mockImplementationOnce(
            () =>
                new Promise((resolve) => {
                    finish = resolve;
                }),
        );
        const previous = mount();
        const previousSignal = harness.request.mock.calls[0][0].signal;
        previous.controller.setOpen(true);
        previous.controller.invalidate();
        previous.cleanup();
        const disconnect = harness.connect.mock.results[0].value;
        const current = mount('second-account');
        finish({ status: 200, headers: {}, data: JSON.stringify({ unread_count: 99 }) });
        await vi.advanceTimersByTimeAsync(200);

        expect(previousSignal.aborted).toBe(true);
        expect(disconnect).toHaveBeenCalledTimes(1);
        expect(current.controller).not.toBe(previous.controller);
        expect(useNotifications()).toMatchObject({
            open: false,
            filter: 'all',
            items: [],
            unreadCount: 2,
            pending: null,
        });
        const calls = harness.request.mock.calls.length;
        current.cleanup();
        window.dispatchEvent(new Event('focus'));
        document.dispatchEvent(new Event('visibilitychange'));
        await vi.advanceTimersByTimeAsync(200);
        expect(harness.request).toHaveBeenCalledTimes(calls);
    });

    it('effect teardown/replay keeps at most one live SSE connection', () => {
        const active = new Set();
        harness.connect.mockImplementation(() => {
            const connection = {};
            active.add(connection);
            return () => active.delete(connection);
        });
        const { effect, cleanup } = mount();
        expect(active.size).toBe(1);
        cleanup();
        expect(active.size).toBe(0);
        const replayCleanup = effect();
        expect(active.size).toBe(1);
        replayCleanup();
        expect(active.size).toBe(0);
    });

    it('does not connect or request notifications without an authenticated user', () => {
        mount(null);

        expect(harness.request).not.toHaveBeenCalled();
        expect(harness.connect).not.toHaveBeenCalled();
        expect(useNotifications()).toMatchObject({ enabled: false, items: [], unreadCount: 0 });
    });
});
