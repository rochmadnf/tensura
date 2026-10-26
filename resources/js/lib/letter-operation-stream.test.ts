import { afterEach, beforeEach, describe, expect, it, vi } from 'vite-plus/test';
import { connectLetterOperations } from './letter-operation-stream';

class Source {
    static instances: Source[] = [];
    onopen: (() => void) | null = null;
    onerror: (() => void) | null = null;
    listener: ((event: unknown) => void) | null = null;
    close = vi.fn();
    constructor(public url: string) {
        Source.instances.push(this);
    }
    addEventListener(_type: string, listener: (event: unknown) => void) {
        this.listener = listener;
    }
    send(operations: unknown[]) {
        this.listener?.({ data: JSON.stringify({ operations }) });
    }
}
const operation = {
    id: 'operation-a',
    letter_id: 'letter-a',
    subject: 'Surat tugas',
    kind: 'preview',
    revision: 1,
    status: 'succeeded',
    status_revision: 3,
};
let saved: Map<string, string>;
beforeEach(() => {
    Source.instances = [];
    saved = new Map();
    vi.useFakeTimers();
    vi.stubGlobal('EventSource', Source);
    vi.stubGlobal('sessionStorage', {
        getItem: (key: string) => saved.get(key),
        setItem: (key: string, value: string) => saved.set(key, value),
    });
});
afterEach(() => {
    vi.unstubAllGlobals();
    vi.useRealTimers();
});
function start(actor = 'actor-a') {
    const onOperation = vi.fn(),
        onTerminal = vi.fn(),
        onConnection = vi.fn();
    const stop = connectLetterOperations({ url: '/events', actor, onOperation, onTerminal, onConnection });
    return { stop, onOperation, onTerminal, onConnection };
}
describe('durable operation event stream', () => {
    it('deduplicates by operation and status revision, including reconnect snapshots', () => {
        const stream = start();
        Source.instances[0].send([operation, operation, { ...operation, status_revision: 2 }]);
        expect(stream.onOperation).toHaveBeenCalledTimes(1);
        expect(stream.onTerminal).toHaveBeenCalledTimes(1);
        Source.instances[0].onerror?.();
        expect(Source.instances[0].close).toHaveBeenCalledOnce();
        vi.advanceTimersByTime(1000);
        Source.instances[1].send([operation, { ...operation, id: 'operation-b' }]);
        expect(stream.onTerminal).toHaveBeenCalledTimes(2);
        stream.stop();
    });
    it('retains per-actor terminal dedup across remounts without losing the state snapshot', () => {
        const first = start();
        Source.instances[0].send([operation]);
        first.stop();
        const second = start();
        Source.instances[1].send([operation]);
        expect(second.onOperation).toHaveBeenCalledOnce();
        expect(second.onTerminal).not.toHaveBeenCalled();
        second.stop();
        const other = start('actor-b');
        Source.instances[2].send([operation]);
        expect(other.onTerminal).toHaveBeenCalledOnce();
        other.stop();
    });
    it('closes on logout and ignores late callbacks and queued reconnects', () => {
        const stream = start();
        Source.instances[0].onerror?.();
        stream.stop();
        Source.instances[0].send([operation]);
        vi.advanceTimersByTime(60000);
        expect(Source.instances).toHaveLength(1);
        expect(stream.onTerminal).not.toHaveBeenCalled();
    });
    it('backs off failed connections up to 30 seconds and resets after connecting', () => {
        const stream = start();
        for (const milliseconds of [1000, 2000, 4000, 8000, 16000, 30000, 30000]) {
            const count = Source.instances.length;
            Source.instances.at(-1)?.onerror?.();
            vi.advanceTimersByTime(milliseconds - 1);
            expect(Source.instances).toHaveLength(count);
            vi.advanceTimersByTime(1);
            expect(Source.instances).toHaveLength(count + 1);
        }
        Source.instances.at(-1)?.onopen?.();
        Source.instances.at(-1)?.onerror?.();
        const count = Source.instances.length;
        vi.advanceTimersByTime(1000);
        expect(Source.instances).toHaveLength(count + 1);
        stream.stop();
    });
    it('ignores malformed events and handles separate operations with independently ordered revisions', () => {
        const stream = start();
        Source.instances[0].listener?.({ data: 'invalid' });
        Source.instances[0].send([
            {},
            { ...operation, status_revision: '3' },
            operation,
            { ...operation, id: 'new', status_revision: 1 },
        ]);
        expect(stream.onOperation).toHaveBeenCalledTimes(2);
        stream.stop();
    });
    it('ignores errors from an older connection and does not open duplicate streams', () => {
        const stream = start();
        const old = Source.instances[0];
        old.onerror?.();
        old.onerror?.();
        vi.advanceTimersByTime(1000);
        expect(Source.instances).toHaveLength(2);
        old.onerror?.();
        old.send([operation]);
        vi.advanceTimersByTime(60000);
        expect(Source.instances).toHaveLength(2);
        expect(Source.instances[1].close).not.toHaveBeenCalled();
        expect(stream.onTerminal).not.toHaveBeenCalled();
        stream.stop();
    });
});
