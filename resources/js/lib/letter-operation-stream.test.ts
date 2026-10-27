import type { LetterOperation } from '@/schemas/letter-preview';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vite-plus/test';
import { connectLetterOperations } from './letter-operation-stream';

class Source {
    static instances: Source[] = [];
    onopen: (() => void) | null = null;
    onerror: (() => void) | null = null;
    listeners = new Map<string, (event: { data: string }) => void>();
    close = vi.fn();

    constructor(
        public url: string,
        public options: EventSourceInit,
    ) {
        Source.instances.push(this);
    }

    addEventListener(type: string, listener: (event: { data: string }) => void) {
        this.listeners.set(type, listener);
    }

    emit(type: string, data: string) {
        this.listeners.get(type)?.({ data });
    }

    send(operations: unknown[]) {
        this.emit('operations', JSON.stringify({ operations }));
    }
}

const operation: LetterOperation = {
    id: 'operation-a',
    letter_id: 'letter-a',
    subject: 'Surat tugas',
    kind: 'preview',
    revision: 1,
    status: 'succeeded',
    status_label: 'Selesai',
    status_revision: 3,
    message: null,
    updated_at: '2026-10-20T00:00:00Z',
};

beforeEach(() => {
    Source.instances = [];
    vi.useFakeTimers();
    vi.stubGlobal('EventSource', Source);
});

afterEach(() => {
    vi.clearAllTimers();
    vi.unstubAllGlobals();
    vi.useRealTimers();
});

function start() {
    const onOperation = vi.fn<(operation: LetterOperation) => void>();
    const onNotifications = vi.fn();
    const onConnection = vi.fn<(connected: boolean) => void>();
    const onNewNotification = vi.fn<(id: string) => void>();
    const stop = connectLetterOperations({
        url: '/events',
        onOperation,
        onNotifications,
        onConnection,
        onNewNotification,
    });
    return { stop, onOperation, onNotifications, onConnection, onNewNotification };
}

const notificationId = 'a0a2a2d2-0b87-4a18-83f2-2529882be2de';
const createdNotification = JSON.stringify({ changed: true, reason: 'created', notification_id: notificationId });

describe('durable operation event stream', () => {
    it('announces a created notification after invalidating counts and deduplicates across reconnects', () => {
        const stream = start();
        const first = Source.instances[0];

        first.emit('notifications', createdNotification);

        expect(stream.onNewNotification).toHaveBeenCalledExactlyOnceWith(notificationId);
        expect(stream.onNotifications.mock.invocationCallOrder[0]).toBeLessThan(
            stream.onNewNotification.mock.invocationCallOrder[0],
        );
        first.emit('notifications', createdNotification);
        first.onerror?.();
        vi.advanceTimersByTime(1000);
        Source.instances[1].emit('notifications', createdNotification);
        Source.instances[1].emit(
            'notifications',
            JSON.stringify({ reason: 'created', notification_id: notificationId.toUpperCase() }),
        );

        expect(stream.onNotifications).toHaveBeenCalledTimes(4);
        expect(stream.onNewNotification).toHaveBeenCalledOnce();
        stream.stop();
    });

    it.each([
        '',
        'invalid',
        'null',
        '42',
        '[]',
        '{}',
        JSON.stringify({ changed: true }),
        JSON.stringify({ reason: 'sync', notification_id: notificationId }),
        JSON.stringify({ reason: 'read', notification_id: notificationId }),
        JSON.stringify({ reason: 'unknown', notification_id: notificationId }),
        JSON.stringify({ reason: 'created' }),
        JSON.stringify({ reason: 'created', notification_id: null }),
        JSON.stringify({ reason: 'created', notification_id: 42 }),
        JSON.stringify({ reason: 'created', notification_id: 'invalid' }),
        JSON.stringify({ reason: 'created', notification_id: `${notificationId}\n` }),
    ])('invalidates counts without announcing legacy or malformed notification payload %s', (data) => {
        const stream = start();

        Source.instances[0].emit('notifications', data);

        expect(stream.onNotifications).toHaveBeenCalledOnce();
        expect(stream.onNewNotification).not.toHaveBeenCalled();
        stream.stop();
    });

    it('keeps creation announcements optional for existing stream consumers', () => {
        const onNotifications = vi.fn();
        const stop = connectLetterOperations({
            url: '/events',
            onOperation: vi.fn(),
            onNotifications,
            onConnection: vi.fn(),
        });

        Source.instances[0].emit('notifications', createdNotification);

        expect(onNotifications).toHaveBeenCalledOnce();
        stop();
    });

    it('bounds remembered notification IDs to the latest 1000 creations', () => {
        const stream = start();
        const source = Source.instances[0];
        const event = (index: number) =>
            JSON.stringify({
                reason: 'created',
                notification_id: `00000000-0000-4000-8000-${index.toString(16).padStart(12, '0')}`,
            });
        for (let index = 0; index <= 1000; index++) source.emit('notifications', event(index));

        source.emit('notifications', event(1));
        expect(stream.onNewNotification).toHaveBeenCalledTimes(1001);
        source.emit('notifications', event(0));
        expect(stream.onNewNotification).toHaveBeenCalledTimes(1002);
        stream.stop();
    });

    it('does not announce a creation if count invalidation closes the stream', () => {
        const stream = start();
        stream.onNotifications.mockImplementationOnce(stream.stop);

        Source.instances[0].emit('notifications', createdNotification);

        expect(stream.onNotifications).toHaveBeenCalledOnce();
        expect(stream.onNewNotification).not.toHaveBeenCalled();
    });

    it('updates state for a large historical snapshot without invalidating notifications', () => {
        const stream = start();
        const statuses = ['succeeded', 'failed', 'stale'] as const;
        const operations = Array.from({ length: 1500 }, (_, index) => ({
            ...operation,
            id: `operation-${index}`,
            letter_id: `letter-${index}`,
            status: statuses[index % statuses.length],
        }));

        Source.instances[0].send(operations);

        expect(stream.onOperation.mock.calls.map(([value]) => value)).toEqual(operations);
        expect(stream.onNotifications).not.toHaveBeenCalled();
        stream.stop();
    });

    it('refreshes notifications on each successful open, including reconnects', () => {
        const stream = start();
        const first = Source.instances[0];
        expect(first.url).toBe('/events');
        expect(first.options).toEqual({ withCredentials: true });
        expect(stream.onNotifications).not.toHaveBeenCalled();

        first.onopen?.();

        expect(stream.onConnection).toHaveBeenLastCalledWith(true);
        expect(stream.onNotifications).toHaveBeenCalledOnce();
        first.onerror?.();
        expect(stream.onConnection).toHaveBeenLastCalledWith(false);
        expect(stream.onNotifications).toHaveBeenCalledOnce();
        vi.advanceTimersByTime(1000);
        expect(stream.onNotifications).toHaveBeenCalledOnce();

        Source.instances[1].onopen?.();

        expect(stream.onConnection.mock.calls).toEqual([[true], [false], [true]]);
        expect(stream.onNotifications).toHaveBeenCalledTimes(2);
        expect(stream.onOperation).not.toHaveBeenCalled();
        expect(stream.onNewNotification).not.toHaveBeenCalled();
        stream.stop();
    });

    it('invalidates notifications for explicit empty notifications events independently of operations', () => {
        const stream = start();
        const source = Source.instances[0];

        source.emit('notifications', '{}');
        source.emit('notifications', '{}');

        expect(stream.onNotifications).toHaveBeenCalledTimes(2);
        expect(stream.onOperation).not.toHaveBeenCalled();
        expect(stream.onConnection).not.toHaveBeenCalled();

        source.send([operation]);
        source.emit('message', '{}');

        expect(stream.onOperation).toHaveBeenCalledExactlyOnceWith(operation);
        expect(stream.onNotifications).toHaveBeenCalledTimes(2);
        stream.stop();
    });

    it('deduplicates revisions per operation across reconnect snapshots while accepting newer state', () => {
        const stream = start();
        const other = { ...operation, id: 'operation-b', status_revision: 1 };
        const newer = { ...operation, status_revision: 4 };
        Source.instances[0].send([operation, operation, { ...operation, status_revision: 2 }, other]);
        expect(stream.onOperation.mock.calls).toEqual([[operation], [other]]);

        Source.instances[0].onerror?.();
        expect(Source.instances[0].close).toHaveBeenCalledOnce();
        vi.advanceTimersByTime(1000);
        Source.instances[1].send([operation, other, newer, { ...operation, status_revision: 1 }, newer]);

        expect(stream.onOperation.mock.calls).toEqual([[operation], [other], [newer]]);
        expect(stream.onNotifications).not.toHaveBeenCalled();
        stream.stop();
    });

    it.each(['invalid', 'null', '42', '[]', '{}', '{"operations":null}', '{"operations":{}}'])(
        'ignores malformed operation payload %s and accepts the next valid event',
        (data) => {
            const stream = start();
            const source = Source.instances[0];

            source.emit('operations', data);

            expect(stream.onOperation).not.toHaveBeenCalled();
            expect(stream.onNotifications).not.toHaveBeenCalled();
            source.send([operation]);
            expect(stream.onOperation).toHaveBeenCalledExactlyOnceWith(operation);
            stream.stop();
        },
    );

    it('skips null and malformed entries without dropping valid operations in the same batch', () => {
        const stream = start();

        Source.instances[0].send([
            null,
            false,
            42,
            'invalid',
            [],
            {},
            { ...operation, id: null },
            { ...operation, letter_id: 1 },
            { ...operation, status: 'unknown', status_revision: 99 },
            { ...operation, status_revision: '3' },
            { ...operation, status_revision: 1.5 },
            { ...operation, status_revision: null },
            { ...operation, status_revision: 0 },
            { ...operation, status_revision: -1 },
            operation,
            null,
            { ...operation, id: 'operation-b', status_revision: 1 },
        ]);

        expect(stream.onOperation.mock.calls).toEqual([
            [operation],
            [{ ...operation, id: 'operation-b', status_revision: 1 }],
        ]);
        expect(stream.onNotifications).not.toHaveBeenCalled();
        stream.stop();
    });

    it('starts with fresh revision state after account remount and ignores callbacks from the previous account', () => {
        const first = start();
        const old = Source.instances[0];
        old.send([operation]);
        first.stop();
        const second = start();

        old.onopen?.();
        old.send([{ ...operation, status_revision: 4 }]);
        old.emit('notifications', '{}');
        old.emit('notifications', createdNotification);
        old.onerror?.();
        Source.instances[1].send([operation]);
        vi.advanceTimersByTime(60000);

        expect(first.onOperation).toHaveBeenCalledExactlyOnceWith(operation);
        expect(first.onNotifications).not.toHaveBeenCalled();
        expect(first.onConnection).not.toHaveBeenCalled();
        expect(first.onNewNotification).not.toHaveBeenCalled();
        expect(second.onOperation).toHaveBeenCalledExactlyOnceWith(operation);
        expect(second.onNotifications).not.toHaveBeenCalled();
        expect(Source.instances).toHaveLength(2);
        Source.instances[1].emit('notifications', createdNotification);
        expect(second.onNewNotification).toHaveBeenCalledExactlyOnceWith(notificationId);
        second.stop();
    });

    it.each(['connected', 'reconnecting'])('cleans up on logout while %s and ignores all late callbacks', (state) => {
        const stream = start();
        const source = Source.instances[0];
        source.onopen?.();
        if (state === 'reconnecting') source.onerror?.();
        stream.onConnection.mockClear();
        stream.onNotifications.mockClear();

        stream.stop();
        stream.stop();
        source.onopen?.();
        source.send([operation]);
        source.emit('notifications', '{}');
        source.emit('notifications', createdNotification);
        source.onerror?.();
        vi.advanceTimersByTime(60000);

        expect(source.close).toHaveBeenCalledOnce();
        expect(Source.instances).toHaveLength(1);
        expect(vi.getTimerCount()).toBe(0);
        expect(stream.onOperation).not.toHaveBeenCalled();
        expect(stream.onNotifications).not.toHaveBeenCalled();
        expect(stream.onConnection).not.toHaveBeenCalled();
        expect(stream.onNewNotification).not.toHaveBeenCalled();
    });

    it('ignores stale source callbacks before and after reconnect without opening duplicate streams', () => {
        const stream = start();
        const old = Source.instances[0];
        old.onerror?.();

        old.onopen?.();
        old.emit('notifications', '{}');
        old.emit('notifications', createdNotification);
        old.send([operation]);
        old.onerror?.();
        vi.advanceTimersByTime(1000);
        const current = Source.instances[1];
        current.onopen?.();
        stream.onConnection.mockClear();
        stream.onNotifications.mockClear();

        old.onopen?.();
        old.emit('notifications', '{}');
        old.emit('notifications', createdNotification);
        old.send([operation]);
        old.onerror?.();
        vi.advanceTimersByTime(60000);

        expect(Source.instances).toHaveLength(2);
        expect(old.close).toHaveBeenCalledOnce();
        expect(current.close).not.toHaveBeenCalled();
        expect(stream.onOperation).not.toHaveBeenCalled();
        expect(stream.onNotifications).not.toHaveBeenCalled();
        expect(stream.onConnection).not.toHaveBeenCalled();
        expect(stream.onNewNotification).not.toHaveBeenCalled();

        current.send([operation]);
        current.emit('notifications', '{}');

        expect(stream.onOperation).toHaveBeenCalledExactlyOnceWith(operation);
        expect(stream.onNotifications).toHaveBeenCalledOnce();
        stream.stop();
    });

    it('backs off failed connections up to 30 seconds and resets after connecting', () => {
        const stream = start();

        for (const milliseconds of [1000, 2000, 4000, 8000, 16000, 30000, 30000]) {
            const count = Source.instances.length;
            const source = Source.instances.at(-1)!;
            source.onerror?.();
            expect(source.close).toHaveBeenCalledOnce();
            vi.advanceTimersByTime(milliseconds - 1);
            expect(Source.instances).toHaveLength(count);
            vi.advanceTimersByTime(1);
            expect(Source.instances).toHaveLength(count + 1);
        }

        Source.instances.at(-1)?.onopen?.();
        Source.instances.at(-1)?.onerror?.();
        const count = Source.instances.length;
        vi.advanceTimersByTime(999);
        expect(Source.instances).toHaveLength(count);
        vi.advanceTimersByTime(1);
        expect(Source.instances).toHaveLength(count + 1);
        expect(stream.onNotifications).toHaveBeenCalledOnce();
        stream.stop();
    });

    it('does not refresh notifications if the open callback cleans up the stream', () => {
        const stream = start();
        stream.onConnection.mockImplementationOnce(() => stream.stop());

        Source.instances[0].onopen?.();

        expect(stream.onConnection).toHaveBeenCalledExactlyOnceWith(true);
        expect(stream.onNotifications).not.toHaveBeenCalled();
        expect(Source.instances[0].close).toHaveBeenCalledOnce();
    });

    it('stops processing the remaining snapshot when an operation callback cleans up the stream', () => {
        const stream = start();
        stream.onOperation.mockImplementationOnce(() => stream.stop());

        Source.instances[0].send([operation, { ...operation, id: 'operation-b' }]);

        expect(stream.onOperation).toHaveBeenCalledExactlyOnceWith(operation);
        expect(stream.onNotifications).not.toHaveBeenCalled();
        expect(Source.instances[0].close).toHaveBeenCalledOnce();
    });

    it('does not leave a reconnect timer when the disconnect callback cleans up the stream', () => {
        const stream = start();
        stream.onConnection.mockImplementationOnce(() => stream.stop());

        Source.instances[0].onerror?.();

        expect(stream.onConnection).toHaveBeenCalledExactlyOnceWith(false);
        expect(vi.getTimerCount()).toBe(0);
        vi.advanceTimersByTime(60000);
        expect(Source.instances).toHaveLength(1);
    });

    it('does not connect or schedule retries when EventSource is unavailable', () => {
        vi.stubGlobal('EventSource', undefined);

        const stream = start();
        vi.advanceTimersByTime(60000);

        expect(Source.instances).toHaveLength(0);
        expect(vi.getTimerCount()).toBe(0);
        expect(stream.onOperation).not.toHaveBeenCalled();
        expect(stream.onNotifications).not.toHaveBeenCalled();
        expect(stream.onConnection).not.toHaveBeenCalled();
        stream.stop();
    });
});
