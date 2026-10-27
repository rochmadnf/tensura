import { afterEach, beforeEach, describe, expect, it, vi } from 'vite-plus/test';
import { NotificationController, type NotificationApi } from './notification-center';
import type { NotificationItem, NotificationPage, NotificationSummary } from '@/types/notification';

function item(id = 'one', read_at: string | null = null): NotificationItem {
    return {
        id,
        type: 'letter-operation',
        title: 'PDF surat siap',
        subject: 'Surat tugas',
        message: null,
        letter_id: `letter-${id}`,
        operation_id: `operation-${id}`,
        status: 'succeeded',
        created_at: '2026-10-27T08:00:00Z',
        read_at,
    };
}

function deferred<T>() {
    let resolve!: (value: T) => void;
    let reject!: (reason: Error) => void;
    const promise = new Promise<T>((yes, no) => {
        resolve = yes;
        reject = no;
    });
    return { promise, resolve, reject };
}

const page = (data = [item()], next_cursor: string | null = 'next', unread_count = 2): NotificationPage => ({
    data,
    next_cursor,
    unread_count,
});
const flush = async () => {
    await Promise.resolve();
    await Promise.resolve();
};
const controllers: NotificationController[] = [];
function setup() {
    const api = {
        summary: vi.fn<NotificationApi['summary']>().mockResolvedValue({ unread_count: 2 }),
        index: vi.fn<NotificationApi['index']>().mockResolvedValue(page()),
        read: vi.fn<NotificationApi['read']>().mockResolvedValue({ unread_count: 1 }),
    };
    const controller = new NotificationController(api);
    controllers.push(controller);
    controller.start();
    return { controller, api };
}

beforeEach(() => vi.useFakeTimers());
afterEach(() => {
    controllers.splice(0).forEach((controller) => controller.dispose());
    vi.useRealTimers();
});

describe('notification synchronization', () => {
    it.each([true, false])(
        'applies a successful summary despite a newer list failing (list fails first: %s)',
        async (listFailsFirst) => {
            const { controller, api } = setup();
            const summary = deferred<NotificationSummary>();
            const list = deferred<NotificationPage>();
            api.summary.mockReturnValueOnce(summary.promise);
            api.index.mockReturnValueOnce(list.promise);
            controller.setOpen(true);

            if (listFailsFirst) {
                list.reject(new Error('list offline'));
                await flush();
                summary.resolve({ unread_count: 7 });
            } else {
                summary.resolve({ unread_count: 7 });
                await flush();
                list.reject(new Error('list offline'));
            }
            await flush();

            expect(controller.getSnapshot()).toMatchObject({
                unreadCount: 7,
                listError: true,
                summaryError: false,
                loading: false,
            });
        },
    );

    it('does not replace a newer successful list count with an older successful summary', async () => {
        const { controller, api } = setup();
        const summary = deferred<NotificationSummary>();
        api.summary.mockReturnValueOnce(summary.promise);
        api.index.mockResolvedValueOnce(page([], null, 7));
        controller.setOpen(true);
        await flush();
        summary.resolve({ unread_count: 99 });
        await flush();

        expect(controller.getSnapshot().unreadCount).toBe(7);
    });

    it('loads only the summary initially and coalesces signals, reconnect and activation', async () => {
        const { controller, api } = setup();
        await flush();
        expect(controller.getSnapshot()).toMatchObject({ unreadCount: 2, open: false });
        expect(api.index).not.toHaveBeenCalled();
        controller.invalidate();
        controller.invalidate();
        controller.setConnected(false);
        controller.setConnected(true);
        await vi.advanceTimersByTimeAsync(100);

        expect(api.summary).toHaveBeenCalledTimes(2);
        expect(api.index).not.toHaveBeenCalled();
        expect(api.read).not.toHaveBeenCalled();
    });

    it('opening does not mark anything read; closing cancels a list and ignores its late response', async () => {
        const { controller, api } = setup();
        const delayed = deferred<NotificationPage>();
        api.index.mockReturnValueOnce(delayed.promise);
        controller.setOpen(true);
        const signal = api.index.mock.calls[0][2];
        controller.setOpen(false);
        delayed.resolve(page());
        await flush();

        expect(signal.aborted).toBe(true);
        expect(controller.getSnapshot()).toMatchObject({ open: false, items: [], loading: false });
        expect(api.read).not.toHaveBeenCalled();
    });

    it('rejects out-of-order filter responses, including failures and loading completion', async () => {
        const { controller, api } = setup();
        const older = deferred<NotificationPage>();
        const newer = deferred<NotificationPage>();
        api.index.mockReturnValueOnce(older.promise).mockReturnValueOnce(newer.promise);
        controller.setOpen(true);
        controller.setFilter('unread');
        older.reject(new Error('old filter failed'));
        await flush();
        expect(controller.getSnapshot()).toMatchObject({ filter: 'unread', loading: true, listError: false });
        newer.resolve(page([item('unread')], null, 1));
        await flush();

        expect(controller.getSnapshot()).toMatchObject({
            items: [item('unread')],
            nextCursor: null,
            unreadCount: 1,
            loading: false,
        });
        expect(api.index.mock.calls[1].slice(0, 2)).toEqual(['unread', null]);
    });

    it('deduplicates pagination, retries the failed cursor, and retains current rows', async () => {
        const { controller, api } = setup();
        controller.setOpen(true);
        await flush();
        const next = deferred<NotificationPage>();
        api.index.mockReturnValueOnce(next.promise);
        controller.loadMore();
        controller.loadMore();
        expect(api.index).toHaveBeenCalledTimes(2);
        next.reject(new Error('offline'));
        await flush();
        expect(controller.getSnapshot()).toMatchObject({
            items: [item()],
            pageError: true,
            nextCursor: 'next',
            loadingMore: false,
        });
        api.index.mockResolvedValueOnce(page([item(), item('two')], null));
        controller.loadMore();
        await flush();

        expect(api.index.mock.calls[2].slice(0, 2)).toEqual(['all', 'next']);
        expect(controller.getSnapshot().items.map((notification) => notification.id)).toEqual(['one', 'two']);
        expect(controller.getSnapshot()).toMatchObject({ pageError: false, nextCursor: null });
    });

    it('a refresh supersedes pagination rather than appending an obsolete page', async () => {
        const { controller, api } = setup();
        controller.setOpen(true);
        await flush();
        const older = deferred<NotificationPage>();
        api.index.mockReturnValueOnce(older.promise).mockResolvedValueOnce(page([item('latest')], 'new-cursor', 5));
        controller.loadMore();
        controller.refresh();
        await flush();
        older.resolve(page([item('obsolete')], 'old-cursor', 8));
        await flush();

        expect(controller.getSnapshot()).toMatchObject({
            items: [item('latest')],
            nextCursor: 'new-cursor',
            unreadCount: 5,
            loadingMore: false,
        });
    });

    it('keeps a newer summary when an older list resolves last', async () => {
        const { controller, api } = setup();
        const older = deferred<NotificationPage>();
        api.index.mockReturnValueOnce(older.promise);
        controller.setOpen(true);
        controller.setOpen(false);
        api.summary.mockResolvedValueOnce({ unread_count: 7 });
        controller.refresh();
        await flush();
        older.resolve(page([], null, 1));
        await flush();

        expect(controller.getSnapshot().unreadCount).toBe(7);
    });

    it('reports summary/list errors in state and recovers on retry', async () => {
        const { controller, api } = setup();
        api.summary.mockRejectedValueOnce(new Error('offline'));
        api.index.mockRejectedValueOnce(new Error('offline'));
        controller.setOpen(true);
        await flush();
        expect(controller.getSnapshot()).toMatchObject({ summaryError: true, listError: true, loading: false });
        controller.refresh();
        await flush();

        expect(controller.getSnapshot()).toMatchObject({ summaryError: false, listError: false, items: [item()] });
    });
});

describe('notification read actions', () => {
    it.each(['one', null])(
        'retries the exact failed mark-read target %s after a filter change and clears the error',
        async (id) => {
            const { controller, api } = setup();
            controller.setOpen(true);
            await flush();
            api.read.mockRejectedValueOnce(new Error('write offline'));
            expect(await controller.markRead(id)).toBe(false);
            expect(controller.getSnapshot()).toMatchObject({
                mutationError: true,
                failedAction: id === null ? 'readAll' : 'read',
            });
            controller.setFilter('unread');
            await flush();
            const retry = deferred<NotificationSummary>();
            api.read.mockReturnValueOnce(retry.promise);

            const saving = controller.retryMutation();
            expect(controller.getSnapshot()).toMatchObject({
                pending: id ?? 'all',
                mutationError: false,
                failedAction: null,
            });
            await controller.retryMutation();
            expect(api.read.mock.calls.map(([target]) => target)).toEqual([id, id]);
            retry.resolve({ unread_count: id === null ? 0 : 1 });
            await saving;

            expect(controller.getSnapshot()).toMatchObject({
                pending: null,
                mutationError: false,
                failedAction: null,
                items: [],
                unreadCount: id === null ? 0 : 1,
            });
            await controller.retryMutation();
            expect(api.read).toHaveBeenCalledTimes(2);
        },
    );

    it('retrying a failed open preserves its original letter and navigates only after the write succeeds', async () => {
        const { controller, api } = setup();
        controller.setOpen(true);
        await flush();
        const navigate = vi.fn();
        api.read.mockRejectedValueOnce(new Error('write offline'));
        await controller.openLetter(item(), navigate);
        expect(controller.getSnapshot()).toMatchObject({ mutationError: true, failedAction: 'open', open: true });
        expect(navigate).not.toHaveBeenCalled();
        api.index.mockResolvedValueOnce(page([item('different')], null, 2));
        controller.refresh();
        await flush();
        const retry = deferred<NotificationSummary>();
        api.read.mockReturnValueOnce(retry.promise);
        const opening = controller.retryMutation();
        expect(navigate).not.toHaveBeenCalled();
        retry.resolve({ unread_count: 1 });
        await opening;

        expect(api.read.mock.calls.map(([id]) => id)).toEqual(['one', 'one']);
        expect(navigate).toHaveBeenCalledExactlyOnceWith('letter-one');
        expect(controller.getSnapshot()).toMatchObject({
            open: false,
            unreadCount: 1,
            mutationError: false,
            failedAction: null,
        });
    });

    it('marks read before opening the letter and rejects GETs from before the write', async () => {
        const { controller, api } = setup();
        controller.setOpen(true);
        await flush();
        const oldSummary = deferred<NotificationSummary>();
        const oldList = deferred<NotificationPage>();
        const write = deferred<NotificationSummary>();
        api.summary.mockReturnValueOnce(oldSummary.promise);
        api.index.mockReturnValueOnce(oldList.promise);
        api.read.mockReturnValueOnce(write.promise);
        controller.refresh();
        const navigate = vi.fn();
        const opening = controller.openLetter(item(), navigate);
        controller.invalidate();
        await vi.advanceTimersByTimeAsync(100);
        expect(navigate).not.toHaveBeenCalled();
        expect(api.index).toHaveBeenCalledTimes(2);
        write.resolve({ unread_count: 1 });
        await opening;
        oldSummary.resolve({ unread_count: 99 });
        oldList.resolve(page([item()], null, 99));
        await flush();

        expect(navigate).toHaveBeenCalledExactlyOnceWith('letter-one');
        expect(controller.getSnapshot()).toMatchObject({ open: false, unreadCount: 1, pending: null });
        expect(controller.getSnapshot().items[0].read_at).not.toBeNull();
    });

    it('serializes writes and removes read entries from unread without resurrecting them in later pages', async () => {
        const { controller, api } = setup();
        controller.setOpen(true);
        controller.setFilter('unread');
        await flush();
        const write = deferred<NotificationSummary>();
        api.read.mockReturnValueOnce(write.promise);
        const saving = controller.markRead('one');
        expect(await controller.markRead(null)).toBe(false);
        write.resolve({ unread_count: 1 });
        expect(await saving).toBe(true);
        expect(controller.getSnapshot().items).toEqual([]);
        api.index.mockResolvedValueOnce(page([item(), item('two')], null, 1));
        controller.loadMore();
        await flush();

        expect(controller.getSnapshot().items).toEqual([item('two')]);
        expect(api.read).toHaveBeenCalledTimes(1);
        expect(await controller.markRead('one')).toBe(true);
        expect(api.read).toHaveBeenCalledTimes(1);
    });

    it('mark all cancels pending pagination and ignores its older unread rows and count', async () => {
        const { controller, api } = setup();
        controller.setOpen(true);
        controller.setFilter('unread');
        await flush();
        const older = deferred<NotificationPage>();
        api.index.mockReturnValueOnce(older.promise);
        controller.loadMore();
        const signal = api.index.mock.calls.at(-1)![2];
        api.read.mockResolvedValueOnce({ unread_count: 0 });
        expect(await controller.markRead(null)).toBe(true);
        older.resolve(page([item('off-page')], null, 2));
        await flush();

        expect(signal.aborted).toBe(true);
        expect(api.read.mock.calls[0][0]).toBeNull();
        expect(controller.getSnapshot()).toMatchObject({
            items: [],
            unreadCount: 0,
            pending: null,
            loadingMore: false,
        });
    });

    it('preserves unread state and stays in the panel on a failed open; the action can retry', async () => {
        const { controller, api } = setup();
        controller.setOpen(true);
        await flush();
        api.read.mockRejectedValueOnce(new Error('expired session'));
        const navigate = vi.fn();
        await controller.openLetter(item(), navigate);
        expect(navigate).not.toHaveBeenCalled();
        expect(controller.getSnapshot()).toMatchObject({
            open: true,
            items: [item()],
            unreadCount: 2,
            mutationError: true,
            pending: null,
        });
        await controller.openLetter(item(), navigate);

        expect(navigate).toHaveBeenCalledExactlyOnceWith('letter-one');
        expect(controller.getSnapshot().mutationError).toBe(false);
    });

    it('opening an already read entry navigates without another write', async () => {
        const { controller, api } = setup();
        api.index.mockResolvedValueOnce(page([item('one', '2026-10-27T09:00:00Z')]));
        controller.setOpen(true);
        await flush();
        const navigate = vi.fn();
        await controller.openLetter(item('one', '2026-10-27T09:00:00Z'), navigate);

        expect(api.read).not.toHaveBeenCalled();
        expect(navigate).toHaveBeenCalledExactlyOnceWith('letter-one');
    });
});

describe('notification account lifecycle', () => {
    it('cancels reads, timers, and subscriptions updates on cleanup even if transports resolve late', async () => {
        const { controller, api } = setup();
        const summary = deferred<NotificationSummary>();
        const list = deferred<NotificationPage>();
        api.summary.mockReturnValueOnce(summary.promise);
        api.index.mockReturnValueOnce(list.promise);
        controller.setOpen(true);
        controller.invalidate();
        const listener = vi.fn();
        controller.subscribe(listener);
        const state = controller.getSnapshot();
        controller.dispose();
        expect(api.summary.mock.calls.at(-1)![0].aborted).toBe(true);
        expect(api.index.mock.calls.at(-1)![2].aborted).toBe(true);
        summary.resolve({ unread_count: 50 });
        list.resolve(page());
        await vi.advanceTimersByTimeAsync(200);

        expect(controller.getSnapshot()).toBe(state);
        expect(listener).not.toHaveBeenCalled();
        expect(api.summary).toHaveBeenCalledTimes(2);
    });

    it('cancels a write and suppresses navigation after account change', async () => {
        const { controller, api } = setup();
        const write = deferred<NotificationSummary>();
        api.read.mockReturnValueOnce(write.promise);
        const navigate = vi.fn();
        const opening = controller.openLetter(item(), navigate);
        controller.dispose();
        const nextAccount = setup();
        write.resolve({ unread_count: 0 });
        await opening;
        await flush();

        expect(api.read.mock.calls[0][1].aborted).toBe(true);
        expect(navigate).not.toHaveBeenCalled();
        expect(nextAccount.controller.getSnapshot()).toMatchObject({
            open: false,
            filter: 'all',
            items: [],
            pending: null,
            unreadCount: 2,
        });
    });

    it('supports effect teardown and restart without accepting an earlier lifecycle response', async () => {
        const { controller, api } = setup();
        const old = deferred<NotificationSummary>();
        api.summary.mockReturnValueOnce(old.promise);
        controller.refresh();
        controller.dispose();
        api.summary.mockResolvedValueOnce({ unread_count: 4 });
        controller.start();
        await flush();
        old.resolve({ unread_count: 88 });
        await flush();

        expect(controller.getSnapshot().unreadCount).toBe(4);
    });
});
