import { afterEach, beforeEach, describe, expect, it, vi } from 'vite-plus/test';
import { NotificationAlerts, type NotificationAlertsRuntime } from './notification-alerts';
import type { SoundPlayback } from './sound-engine';
import { switch007Sound } from './switch-007';

function deferred<T = void>() {
    let resolve!: (value: T) => void;
    let reject!: (error: Error) => void;
    const promise = new Promise<T>((yes, no) => {
        resolve = yes;
        reject = no;
    });
    return { promise, resolve, reject };
}

const id = (number: number) => `abcdefab-1234-4567-89ab-${number.toString().padStart(12, '0')}`;
const key = (actor = 'actor') => `notification-alerts:v1:${encodeURIComponent(actor)}`;
const flush = async () => {
    for (let step = 0; step < 30; step++) await Promise.resolve();
};

class Storage {
    values = new Map<string, string>();
    getItem = vi.fn((name: string) => this.values.get(name) ?? null);
    setItem = vi.fn((name: string, value: string) => {
        this.values.set(name, value);
    });
    receipt(actor = 'actor'): { ids: string[]; lastPlayedAt: number } {
        return JSON.parse(this.values.get(key(actor))!);
    }
}

/** Each named queue remains exclusive until its callback settles, like Web Locks. */
class Locks {
    private tails = new Map<string, Promise<unknown>>();
    held = new Set<string>();
    request = vi.fn((name: string, options: LockOptions, callback: () => Promise<unknown>) => {
        const task = (this.tails.get(name) ?? Promise.resolve())
            .catch(() => {})
            .then(async () => {
                options.signal?.throwIfAborted();
                this.held.add(name);
                try {
                    return await callback();
                } finally {
                    this.held.delete(name);
                }
            });
        this.tails.set(name, task);
        return task;
    });
    manager = { request: this.request as unknown as LockManager['request'] };

    hold(actor = 'actor') {
        const barrier = deferred();
        void this.request(key(actor), { mode: 'exclusive' }, () => barrier.promise);
        return barrier;
    }
}

class Context extends EventTarget {
    state: AudioContextState = 'suspended';
    resume = vi.fn(async () => this.setState('running'));
    close = vi.fn();
    setState(state: AudioContextState) {
        this.state = state;
        this.dispatchEvent(new Event('statechange'));
    }
}

const alerts: NotificationAlerts[] = [];
function setup(
    shared = { storage: new Storage(), locks: new Locks() },
    overrides: Partial<NotificationAlertsRuntime> = {},
    actor = 'actor',
) {
    const context = new Context();
    const document = new EventTarget();
    const stop = vi.fn();
    const decode = vi.fn<NotificationAlertsRuntime['decodeAudioData']>().mockResolvedValue({} as AudioBuffer);
    const play = vi.fn<NotificationAlertsRuntime['playSound']>().mockResolvedValue({ stop });
    const runtime: NotificationAlertsRuntime = {
        now: Date.now,
        document,
        storage: () => shared.storage,
        locks: () => shared.locks.manager,
        soundSupported: () => true,
        getAudioContext: () => context as unknown as AudioContext,
        decodeAudioData: decode,
        playSound: play,
        ...overrides,
    };
    const controller = new NotificationAlerts(actor, runtime);
    alerts.push(controller);
    controller.start();
    return { controller, context, document, play, stop, decode, ...shared };
}

beforeEach(() => {
    vi.useFakeTimers();
    vi.setSystemTime(0);
});
afterEach(() => {
    alerts.splice(0).forEach((alert) => alert.dispose());
    vi.useRealTimers();
    vi.restoreAllMocks();
});

describe('notification audio coordination', () => {
    it('starts silently, has a stable exact snapshot, and animates at time zero without trailing burst replays', async () => {
        const { controller, play, context, locks } = setup();
        const initial = controller.getSnapshot();
        const changed = vi.fn();
        const unsubscribe = controller.subscribe(changed);
        expect(initial).toEqual({ arrivalRevision: 0, soundReady: false, soundSupported: true, enablingSound: false });
        expect(controller.getSnapshot()).toBe(initial);
        expect(context.resume).not.toHaveBeenCalled();

        controller.receive(id(1));
        controller.receive(id(1).toUpperCase());
        controller.receive(id(2));
        await vi.advanceTimersByTimeAsync(999);
        controller.receive(id(3));
        expect(controller.getSnapshot().arrivalRevision).toBe(1);
        await vi.advanceTimersByTimeAsync(1);
        controller.receive(id(3));
        expect(controller.getSnapshot().arrivalRevision).toBe(1);
        controller.receive(id(4));
        expect(controller.getSnapshot().arrivalRevision).toBe(2);
        expect(changed).toHaveBeenCalledTimes(2);
        expect(locks.request).not.toHaveBeenCalled();
        expect(play).not.toHaveBeenCalled();
        unsubscribe();
        await vi.advanceTimersByTimeAsync(2000);
        controller.receive(id(5));
        expect(changed).toHaveBeenCalledTimes(2);
    });

    it('predecodes on activation without playing and never replays arrivals from before readiness', async () => {
        const { controller, play, decode } = setup();
        controller.receive(id(1));
        await controller.enableSound();
        controller.receive(id(1));
        await flush();
        expect(decode).toHaveBeenCalledWith(switch007Sound.dataUri, expect.any(AbortSignal));
        expect(controller.getSnapshot().soundReady).toBe(true);
        expect(play).not.toHaveBeenCalled();

        controller.receive(id(2));
        await flush();
        expect(play).toHaveBeenCalledWith(switch007Sound.dataUri, expect.objectContaining({ volume: 0.35 }));
    });

    it('plays the same UUID once across tabs, including a background tab, and persists before starting', async () => {
        const shared = { storage: new Storage(), locks: new Locks() };
        const background = setup(shared);
        Object.defineProperty(background.document, 'visibilityState', { value: 'hidden' });
        const foreground = setup(shared);
        background.play.mockImplementation(async () => {
            expect(shared.locks.held.has(key())).toBe(true);
            expect(shared.storage.receipt()).toEqual({ ids: [id(1)], lastPlayedAt: 0 });
            return { stop: background.stop };
        });
        await Promise.all([background.controller.enableSound(), foreground.controller.enableSound()]);

        background.controller.receive(id(1).toUpperCase());
        foreground.controller.receive(id(1));
        await flush();
        expect(background.play).toHaveBeenCalledTimes(1);
        expect(foreground.play).not.toHaveBeenCalled();
        expect(shared.locks.held.size).toBe(0);
        expect(background.stop).not.toHaveBeenCalled();

        await vi.advanceTimersByTimeAsync(1000);
        const newTab = setup(shared);
        await newTab.controller.enableSound();
        newTab.controller.receive(id(1));
        await flush();
        expect(newTab.play).not.toHaveBeenCalled();
    });

    it('keeps a blocked tab outside the lock and lets another ready tab win', async () => {
        const shared = { storage: new Storage(), locks: new Locks() };
        const blocked = setup(shared);
        const resume = deferred();
        blocked.context.resume.mockImplementation(() => resume.promise);
        const enabling = blocked.controller.enableSound();
        const ready = setup(shared);
        await ready.controller.enableSound();

        blocked.controller.receive(id(1));
        ready.controller.receive(id(1));
        await flush();
        expect(ready.play).toHaveBeenCalledTimes(1);
        expect(blocked.play).not.toHaveBeenCalled();
        expect(shared.locks.request).toHaveBeenCalledTimes(1);
        await vi.advanceTimersByTimeAsync(2000);
        await enabling;
        expect(blocked.controller.getSnapshot()).toMatchObject({ soundReady: false, enablingSound: false });
        blocked.context.setState('running');
        resume.resolve();
        await flush();
        expect(blocked.controller.getSnapshot().soundReady).toBe(false);
    });

    it('does not call a resolved resume ready unless the actual context is running, and allows retry', async () => {
        const { controller, context } = setup();
        context.resume.mockResolvedValueOnce(undefined);
        await controller.enableSound();
        expect(controller.getSnapshot()).toMatchObject({ soundReady: false, enablingSound: false });
        await controller.enableSound();
        expect(controller.getSnapshot().soundReady).toBe(true);
        context.setState('suspended');
        expect(controller.getSnapshot().soundReady).toBe(false);
        await controller.enableSound();
        expect(context.resume).toHaveBeenCalledTimes(3);
        expect(controller.getSnapshot().soundReady).toBe(true);
    });

    it('records every burst ID during the shared cooldown and never replays suppressed arrivals', async () => {
        const shared = { storage: new Storage(), locks: new Locks() };
        const first = setup(shared);
        const second = setup(shared);
        await Promise.all([first.controller.enableSound(), second.controller.enableSound()]);
        first.controller.receive(id(1));
        second.controller.receive(id(2));
        first.controller.receive(id(3));
        await flush();
        expect(first.play).toHaveBeenCalledTimes(1);
        expect(second.play).not.toHaveBeenCalled();
        expect(shared.storage.receipt().ids).toEqual([id(1), id(2), id(3)]);

        await vi.advanceTimersByTimeAsync(1000);
        second.controller.receive(id(3));
        first.controller.receive(id(2));
        await flush();
        expect(first.play).toHaveBeenCalledTimes(1);
        expect(second.play).not.toHaveBeenCalled();
        second.controller.receive(id(4));
        await flush();
        expect(second.play).toHaveBeenCalledTimes(1);
    });

    it('isolates actor receipts, locks, animation, and cooldown clocks', async () => {
        const shared = { storage: new Storage(), locks: new Locks() };
        const first = setup(shared, {}, 'one');
        const second = setup(shared, {}, 'two');
        await Promise.all([first.controller.enableSound(), second.controller.enableSound()]);
        first.controller.receive(id(1));
        second.controller.receive(id(1));
        await flush();
        expect(first.play).toHaveBeenCalledTimes(1);
        expect(second.play).toHaveBeenCalledTimes(1);
        expect(shared.storage.values.size).toBe(2);
        expect(first.controller.getSnapshot().arrivalRevision).toBe(1);
        expect(second.controller.getSnapshot().arrivalRevision).toBe(1);
    });

    it('bounds tab deduplication and stored receipts at 1000 UUIDs', async () => {
        const { controller, storage, play } = setup();
        await controller.enableSound();
        for (let number = 0; number <= 1000; number++) {
            controller.receive(id(number));
            await flush();
        }
        expect(storage.receipt().ids).toHaveLength(1000);
        expect(storage.receipt().ids).not.toContain(id(0));
        expect(play).toHaveBeenCalledTimes(1);
        await vi.advanceTimersByTimeAsync(1000);
        controller.receive(id(1000));
        expect(controller.getSnapshot().arrivalRevision).toBe(1);
        controller.receive(id(0));
        await flush();
        expect(controller.getSnapshot().arrivalRevision).toBe(2);
        expect(play).toHaveBeenCalledTimes(2);
        expect(storage.receipt().ids).toHaveLength(1000);
    });

    it.each([
        '{',
        'null',
        JSON.stringify({ ids: ['not-a-uuid'], lastPlayedAt: 0 }),
        JSON.stringify({ ids: [], lastPlayedAt: '0' }),
        JSON.stringify({ ids: [], lastPlayedAt: -1001 }),
        JSON.stringify({ ids: Array.from({ length: 1001 }, () => id(1)), lastPlayedAt: 0 }),
    ])('leaves sound silent for malformed receipts: %s', async (value) => {
        const { controller, storage, play } = setup();
        storage.values.set(key(), value);
        await controller.enableSound();
        controller.receive(id(1));
        await flush();
        expect(controller.getSnapshot().arrivalRevision).toBe(1);
        expect(play).not.toHaveBeenCalled();
        expect(storage.setItem).not.toHaveBeenCalled();
    });

    it('normalizes UUIDs from existing receipts before deduplication', async () => {
        const { controller, storage, play } = setup();
        storage.values.set(key(), JSON.stringify({ ids: [id(1).toUpperCase()], lastPlayedAt: 0 }));
        vi.setSystemTime(2000);
        await controller.enableSound();
        controller.receive(id(1));
        await flush();
        expect(play).not.toHaveBeenCalled();
        expect(storage.receipt().ids).toEqual([id(1)]);
    });

    it.each([
        'missing-locks',
        'locks-getter',
        'locks-throw',
        'locks-reject',
        'missing-storage',
        'storage-getter',
        'read',
        'write',
        'verify',
    ])('stays silent when coordination fails: %s', async (failure) => {
        const shared = { storage: new Storage(), locks: new Locks() };
        const fail = () => {
            throw new Error('blocked');
        };
        const overrides: Partial<NotificationAlertsRuntime> = {};
        if (failure === 'missing-locks') overrides.locks = () => null;
        if (failure === 'locks-getter') overrides.locks = fail;
        if (failure === 'locks-throw') shared.locks.request.mockImplementation(fail);
        if (failure === 'locks-reject') shared.locks.request.mockRejectedValue(new Error('blocked'));
        if (failure === 'missing-storage') overrides.storage = () => null;
        if (failure === 'storage-getter') overrides.storage = fail;
        if (failure === 'read') shared.storage.getItem.mockImplementation(fail);
        if (failure === 'write') shared.storage.setItem.mockImplementation(fail);
        if (failure === 'verify') shared.storage.setItem.mockImplementation(() => {});
        const { controller, play } = setup(shared, overrides);
        await controller.enableSound();

        controller.receive(id(1));
        await flush();
        expect(play).not.toHaveBeenCalled();
        expect(controller.getSnapshot().arrivalRevision).toBe(1);
    });

    it('drops expired lock callbacks even when a lock implementation ignores cancellation', async () => {
        const shared = { storage: new Storage(), locks: new Locks() };
        const barrier = shared.locks.hold();
        const { controller, play } = setup(shared);
        await controller.enableSound();
        controller.receive(id(1));
        // Move the clock without firing the expiry timer to test the callback's own age check.
        vi.setSystemTime(1000);
        barrier.resolve();
        await flush();
        expect(play).not.toHaveBeenCalled();
        expect(shared.storage.receipt().ids).toEqual([id(1)]);
        expect(controller.getSnapshot().arrivalRevision).toBe(1);
    });

    it('cancels a queued lock request at its deadline', async () => {
        const shared = { storage: new Storage(), locks: new Locks() };
        const barrier = shared.locks.hold();
        const { controller, play } = setup(shared);
        await controller.enableSound();
        controller.receive(id(1));
        const signal = shared.locks.request.mock.calls[1][1].signal!;
        await vi.advanceTimersByTimeAsync(1000);
        expect(signal.aborted).toBe(true);
        barrier.resolve();
        await flush();
        expect(play).not.toHaveBeenCalled();
    });

    it('aborts deferred playback at the arrival deadline without queuing a late chirp', async () => {
        const { controller, play } = setup();
        const pending = deferred<SoundPlayback>();
        play.mockImplementation(
            (_uri, options) =>
                new Promise((resolve, reject) => {
                    options?.signal?.addEventListener('abort', () => reject(options.signal!.reason), { once: true });
                    pending.promise.then(resolve, reject);
                }),
        );
        await controller.enableSound();
        controller.receive(id(1));
        await flush();
        const signal = play.mock.calls[0][1]!.signal!;
        await vi.advanceTimersByTimeAsync(1000);
        expect(signal.aborted).toBe(true);
        expect(controller.getSnapshot().arrivalRevision).toBe(1);
    });

    it('aborts old decode work and removes listeners across a StrictMode restart', async () => {
        const { controller, context, document, decode, play } = setup();
        const pending = deferred<AudioBuffer>();
        decode.mockReturnValueOnce(pending.promise);
        const add = vi.spyOn(document, 'addEventListener');
        const remove = vi.spyOn(document, 'removeEventListener');
        const enabling = controller.enableSound();
        const signal = decode.mock.calls[0][1]!;
        controller.start();
        expect(add).not.toHaveBeenCalled();
        controller.dispose();
        await enabling;
        expect(signal.aborted).toBe(true);
        expect(remove.mock.calls.map(([type]) => type)).toEqual(['pointerdown', 'keydown']);
        controller.start();
        controller.start();
        expect(add.mock.calls.map(([type]) => type)).toEqual(['pointerdown', 'keydown']);
        await controller.enableSound();
        pending.resolve({} as AudioBuffer);
        await flush();
        expect(controller.getSnapshot()).toMatchObject({ soundReady: true, enablingSound: false });
        expect(play).not.toHaveBeenCalled();
        expect(context.close).not.toHaveBeenCalled();
    });

    it('guards callbacks from a prior lifetime even if queued locks ignore AbortSignal', async () => {
        const { controller, locks, play } = setup();
        const callback = deferred<() => Promise<unknown>>();
        locks.request.mockImplementation((_name, _options, work) => {
            callback.resolve(work);
            return Promise.resolve();
        });
        await controller.enableSound();
        controller.receive(id(1));
        const oldCallback = await callback.promise;
        controller.dispose();
        controller.start();
        await controller.enableSound();
        await oldCallback();
        expect(play).not.toHaveBeenCalled();
    });

    it('stops only its own active playback on disposal and ignores arrivals until restarted', async () => {
        const shared = { storage: new Storage(), locks: new Locks() };
        const first = setup(shared, {}, 'one');
        const second = setup(shared, {}, 'two');
        await Promise.all([first.controller.enableSound(), second.controller.enableSound()]);
        first.controller.receive(id(1));
        second.controller.receive(id(1));
        await flush();
        first.controller.dispose();
        first.controller.receive(id(2));
        expect(first.stop).toHaveBeenCalledTimes(1);
        expect(second.stop).not.toHaveBeenCalled();
        expect(first.context.close).not.toHaveBeenCalled();
        expect(first.controller.getSnapshot()).toMatchObject({ soundReady: false, arrivalRevision: 1 });
    });

    it('requires trusted gestures, never plays on activation, and can resume after suspension', async () => {
        const { controller, document, context, play } = setup();
        document.dispatchEvent(new Event('pointerdown'));
        await flush();
        expect(context.resume).not.toHaveBeenCalled();
        const trusted = (type: string) => {
            const event = new Event(type);
            Object.defineProperty(event, 'isTrusted', { value: true });
            document.dispatchEvent(event);
        };
        trusted('pointerdown');
        expect(context.resume).toHaveBeenCalledTimes(1);
        await flush();
        expect(controller.getSnapshot().soundReady).toBe(true);
        context.setState('suspended');
        trusted('keydown');
        await flush();
        expect(context.resume).toHaveBeenCalledTimes(2);
        expect(controller.getSnapshot().soundReady).toBe(true);
        expect(play).not.toHaveBeenCalled();
        controller.dispose();
        trusted('keydown');
        expect(context.resume).toHaveBeenCalledTimes(2);
    });

    it('still animates when audio is unsupported', async () => {
        const { controller, context, play } = setup(undefined, { soundSupported: () => false });
        await controller.enableSound();
        controller.receive(id(1));
        expect(controller.getSnapshot()).toEqual({
            arrivalRevision: 1,
            soundReady: false,
            soundSupported: false,
            enablingSound: false,
        });
        expect(context.resume).not.toHaveBeenCalled();
        expect(play).not.toHaveBeenCalled();
    });
});
