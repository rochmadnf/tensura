import { decodeAudioData, getAudioContext, playSound, type SoundPlayback } from './sound-engine';
import { switch007Sound } from './switch-007';

const COOLDOWN = 1000;
const MAX_IDS = 1000;
const ENABLE_TIMEOUT = 2000;
const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

export interface NotificationAlertsSnapshot {
    arrivalRevision: number;
    soundReady: boolean;
    soundSupported: boolean;
    enablingSound: boolean;
}

export interface NotificationAlertsRuntime {
    now: () => number;
    document: EventTarget | null;
    storage: () => Pick<Storage, 'getItem' | 'setItem'> | null;
    locks: () => Pick<LockManager, 'request'> | null;
    soundSupported: () => boolean;
    getAudioContext: typeof getAudioContext;
    decodeAudioData: typeof decodeAudioData;
    playSound: typeof playSound;
}

interface Receipt {
    ids: string[];
    lastPlayedAt: number;
}

function readReceipt(value: string | null): Receipt {
    if (value === null) return { ids: [], lastPlayedAt: -COOLDOWN };
    const receipt: unknown = JSON.parse(value);
    if (
        typeof receipt !== 'object' ||
        receipt === null ||
        !('ids' in receipt) ||
        !Array.isArray(receipt.ids) ||
        receipt.ids.length > MAX_IDS ||
        !receipt.ids.every((id: unknown) => typeof id === 'string' && UUID.test(id)) ||
        !('lastPlayedAt' in receipt) ||
        typeof receipt.lastPlayedAt !== 'number' ||
        !Number.isFinite(receipt.lastPlayedAt) ||
        receipt.lastPlayedAt < -COOLDOWN
    ) {
        throw new Error('Invalid sound receipt.');
    }
    return { ids: [...new Set(receipt.ids.map((id: string) => id.toLowerCase()))], lastPlayedAt: receipt.lastPlayedAt };
}

export class NotificationAlerts {
    private runtime: NotificationAlertsRuntime;
    private snapshot: NotificationAlertsSnapshot;
    private listeners = new Set<() => void>();
    private seen = new Set<string>();
    private lastArrivalAt = -Infinity;
    private lifetime: AbortController | null = null;
    private context: AudioContext | null = null;
    private decoded = false;
    private enabling: Promise<void> | null = null;
    private attempts = new Set<AbortController>();
    private playbacks = new Set<SoundPlayback>();
    private receiptKey: string;

    constructor(actor: string, runtime: Partial<NotificationAlertsRuntime> = {}) {
        this.runtime = {
            now: Date.now,
            document: typeof document === 'undefined' ? null : document,
            storage: () => (typeof window === 'undefined' ? null : window.localStorage),
            locks: () => (typeof navigator === 'undefined' ? null : navigator.locks),
            soundSupported: () => typeof globalThis.AudioContext === 'function',
            getAudioContext,
            decodeAudioData,
            playSound,
            ...runtime,
        };
        this.receiptKey = `notification-alerts:v1:${encodeURIComponent(actor)}`;
        this.snapshot = {
            arrivalRevision: 0,
            soundReady: false,
            soundSupported: this.runtime.soundSupported(),
            enablingSound: false,
        };
    }

    getSnapshot = (): NotificationAlertsSnapshot => this.snapshot;

    subscribe = (listener: () => void): (() => void) => {
        this.listeners.add(listener);
        return () => this.listeners.delete(listener);
    };

    private update(patch: Partial<NotificationAlertsSnapshot>): void {
        if (
            Object.entries(patch).every(
                ([key, value]) => this.snapshot[key as keyof NotificationAlertsSnapshot] === value,
            )
        )
            return;
        this.snapshot = { ...this.snapshot, ...patch };
        this.listeners.forEach((listener) => listener());
    }

    start = (): void => {
        if (this.lifetime) return;
        this.lifetime = new AbortController();
        this.runtime.document?.addEventListener('pointerdown', this.gesture);
        this.runtime.document?.addEventListener('keydown', this.gesture);
    };

    dispose = (): void => {
        this.lifetime?.abort();
        this.lifetime = null;
        this.runtime.document?.removeEventListener('pointerdown', this.gesture);
        this.runtime.document?.removeEventListener('keydown', this.gesture);
        this.context?.removeEventListener('statechange', this.contextChanged);
        this.context = null;
        this.decoded = false;
        this.enabling = null;
        this.attempts.forEach((attempt) => attempt.abort());
        this.attempts.clear();
        this.playbacks.forEach((playback) => playback.stop());
        this.playbacks.clear();
        this.update({ soundReady: false, enablingSound: false });
    };

    private gesture = (event: Event): void => {
        if (event.isTrusted) void this.enableSound();
    };

    private contextChanged = (): void => {
        this.update({ soundReady: !!this.lifetime && this.decoded && this.context?.state === 'running' });
    };

    enableSound = (): Promise<void> => {
        const lifetime = this.lifetime;
        if (!lifetime || !this.snapshot.soundSupported) return Promise.resolve();
        if (this.enabling) return this.enabling;
        if (this.snapshot.soundReady && this.context?.state === 'running') return Promise.resolve();

        this.update({ enablingSound: true, soundReady: false });
        const attempt = new AbortController();
        const abort = () => attempt.abort();
        lifetime.signal.addEventListener('abort', abort, { once: true });
        const timeout = setTimeout(abort, ENABLE_TIMEOUT);
        let detachAbort = () => {};
        const cancelled = new Promise<never>((_, reject) => {
            const cancel = () => reject(attempt.signal.reason);
            attempt.signal.addEventListener('abort', cancel, { once: true });
            detachAbort = () => attempt.signal.removeEventListener('abort', cancel);
        });

        const prepare = async () => {
            const ctx = this.runtime.getAudioContext();
            this.context?.removeEventListener('statechange', this.contextChanged);
            this.context = ctx;
            ctx.addEventListener('statechange', this.contextChanged);
            // Resume in the gesture's call stack, including previously activated origins.
            const resume = ctx.state === 'running' ? Promise.resolve() : ctx.resume();
            await Promise.all([resume, this.runtime.decodeAudioData(switch007Sound.dataUri, attempt.signal)]);
            if (attempt.signal.aborted || this.lifetime !== lifetime) return;
            this.decoded = true;
            this.contextChanged();
        };

        this.enabling = Promise.race([prepare(), cancelled])
            .catch(() => {})
            .finally(() => {
                clearTimeout(timeout);
                detachAbort();
                lifetime.signal.removeEventListener('abort', abort);
                if (this.lifetime !== lifetime) return;
                this.enabling = null;
                this.update({ enablingSound: false });
            });
        return this.enabling;
    };

    /** Call only for explicit newly-created notification signals. */
    receive = (notificationId: string): void => {
        notificationId = notificationId.toLowerCase();
        if (!this.lifetime || this.seen.has(notificationId)) return;
        this.seen.add(notificationId);
        if (this.seen.size > MAX_IDS) this.seen.delete(this.seen.values().next().value!);

        const arrivedAt = this.runtime.now();
        if (arrivedAt - this.lastArrivalAt >= COOLDOWN) {
            this.lastArrivalAt = arrivedAt;
            this.update({ arrivalRevision: this.snapshot.arrivalRevision + 1 });
        }
        if (!this.snapshot.soundReady || this.context?.state !== 'running' || !UUID.test(notificationId)) return;
        void this.claimSound(notificationId, arrivedAt, this.lifetime);
    };

    private async claimSound(id: string, arrivedAt: number, lifetime: AbortController): Promise<void> {
        const attempt = new AbortController();
        this.attempts.add(attempt);
        const expiry = setTimeout(() => attempt.abort(), COOLDOWN);
        let playback: SoundPlayback | undefined;
        try {
            const locks = this.runtime.locks();
            if (!locks) return;
            await locks.request(this.receiptKey, { mode: 'exclusive', signal: attempt.signal }, async () => {
                if (attempt.signal.aborted || this.lifetime !== lifetime || this.context?.state !== 'running') return;
                const storage = this.runtime.storage();
                if (!storage) return;
                const receipt = readReceipt(storage.getItem(this.receiptKey));
                const now = this.runtime.now();
                const expired = now - arrivedAt < 0 || now - arrivedAt >= COOLDOWN;
                const play = !expired && !receipt.ids.includes(id) && now - receipt.lastPlayedAt >= COOLDOWN;
                receipt.ids = [...receipt.ids.filter((seen) => seen !== id), id].slice(-MAX_IDS);
                if (play) receipt.lastPlayedAt = now;
                const claim = JSON.stringify(receipt);
                storage.setItem(this.receiptKey, claim);
                if (storage.getItem(this.receiptKey) !== claim || !play || attempt.signal.aborted) return;

                playback = await this.runtime.playSound(switch007Sound.dataUri, {
                    volume: 0.35,
                    signal: attempt.signal,
                    onEnd: () => {
                        if (playback) this.playbacks.delete(playback);
                    },
                });
                if (attempt.signal.aborted || this.lifetime !== lifetime) playback.stop();
                else this.playbacks.add(playback);
            });
        } catch {
            // Unavailable coordination, storage, or audio must leave arrivals silent.
        } finally {
            clearTimeout(expiry);
            this.attempts.delete(attempt);
        }
    }
}
