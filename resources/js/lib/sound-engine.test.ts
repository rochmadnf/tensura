import { afterEach, beforeEach, describe, expect, it, vi } from 'vite-plus/test';

const dataUri = 'data:audio/mpeg;base64,AQID';

function deferred<T = void>() {
    let resolve!: (value: T) => void;
    let reject!: (error: Error) => void;
    const promise = new Promise<T>((yes, no) => {
        resolve = yes;
        reject = no;
    });
    return { promise, resolve, reject };
}

async function setup(state: AudioContextState = 'running') {
    const source = {
        buffer: null as AudioBuffer | null,
        playbackRate: { value: 1 },
        connect: vi.fn(),
        disconnect: vi.fn(),
        start: vi.fn(),
        stop: vi.fn(),
        onended: null as (() => void) | null,
    };
    const gain = { gain: { value: 1 }, connect: vi.fn(), disconnect: vi.fn() };
    const buffer = {} as AudioBuffer;
    const context = {
        state,
        destination: {},
        resume: vi.fn(async () => {
            context.state = 'running';
        }),
        decodeAudioData: vi.fn(async (_bytes: ArrayBuffer) => buffer),
        createBufferSource: vi.fn(() => source),
        createGain: vi.fn(() => gain),
        close: vi.fn(),
    };
    vi.stubGlobal(
        'AudioContext',
        vi.fn(function () {
            return context;
        }),
    );
    const engine = await import('./sound-engine');
    return { ...engine, context, source, gain, buffer };
}

beforeEach(() => vi.resetModules());
afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

describe('sound engine cancellation and cleanup', () => {
    it('does not resume or decode an already aborted request', async () => {
        const { playSound, context } = await setup('suspended');
        const controller = new AbortController();
        controller.abort();

        await expect(playSound(dataUri, { signal: controller.signal })).rejects.toMatchObject({ name: 'AbortError' });
        expect(context.resume).not.toHaveBeenCalled();
        expect(context.decodeAudioData).not.toHaveBeenCalled();
        expect(context.createBufferSource).not.toHaveBeenCalled();
    });

    it('rejects promptly during deferred resume and never starts after the resume resolves', async () => {
        const { playSound, context, source } = await setup('suspended');
        const resume = deferred();
        context.resume.mockImplementation(() => resume.promise);
        const controller = new AbortController();
        const result = playSound(dataUri, { signal: controller.signal });

        controller.abort();
        await expect(result).rejects.toMatchObject({ name: 'AbortError' });
        context.state = 'running';
        resume.resolve();
        await Promise.resolve();
        expect(context.decodeAudioData).not.toHaveBeenCalled();
        expect(source.start).not.toHaveBeenCalled();
        expect(context.close).not.toHaveBeenCalled();
    });

    it('rejects promptly during deferred decode and never creates a source after decode resolves', async () => {
        const { playSound, context, source, buffer } = await setup();
        const decoded = deferred<AudioBuffer>();
        context.decodeAudioData.mockReturnValue(decoded.promise);
        const controller = new AbortController();
        const result = playSound(dataUri, { signal: controller.signal });

        controller.abort();
        await expect(result).rejects.toMatchObject({ name: 'AbortError' });
        decoded.resolve(buffer);
        await Promise.resolve();
        expect(context.createBufferSource).not.toHaveBeenCalled();
        expect(source.start).not.toHaveBeenCalled();
    });

    it('cancels predecode and removes the abort listener without waiting for the decoder', async () => {
        const { decodeAudioData, context } = await setup();
        context.decodeAudioData.mockReturnValue(new Promise(() => {}));
        const controller = new AbortController();
        const remove = vi.spyOn(controller.signal, 'removeEventListener');
        const result = decodeAudioData(dataUri, controller.signal);
        controller.abort();

        await expect(result).rejects.toMatchObject({ name: 'AbortError' });
        expect(remove).toHaveBeenCalledWith('abort', expect.any(Function));
    });

    it('stops and disconnects a playing source on abort, idempotently', async () => {
        const { playSound, source, gain, context } = await setup();
        const controller = new AbortController();
        const remove = vi.spyOn(controller.signal, 'removeEventListener');
        const playback = await playSound(dataUri, { volume: 0.35, playbackRate: 1.25, signal: controller.signal });
        expect(source.start).toHaveBeenCalledWith(0);
        expect(source.playbackRate.value).toBe(1.25);
        expect(gain.gain.value).toBe(0.35);

        controller.abort();
        playback.stop();
        expect(source.stop).toHaveBeenCalledTimes(1);
        expect(source.disconnect).toHaveBeenCalledTimes(1);
        expect(gain.disconnect).toHaveBeenCalledTimes(1);
        expect(source.onended).toBeNull();
        expect(remove).toHaveBeenCalledWith('abort', expect.any(Function));
        expect(context.close).not.toHaveBeenCalled();
    });

    it('disconnects nodes and listeners on natural end and calls onEnd once', async () => {
        const { playSound, source, gain } = await setup();
        const controller = new AbortController();
        const onEnd = vi.fn();
        const remove = vi.spyOn(controller.signal, 'removeEventListener');
        const playback = await playSound(dataUri, { signal: controller.signal, onEnd });
        source.onended!();
        controller.abort();
        playback.stop();

        expect(source.disconnect).toHaveBeenCalledTimes(1);
        expect(gain.disconnect).toHaveBeenCalledTimes(1);
        expect(source.onended).toBeNull();
        expect(source.stop).not.toHaveBeenCalled();
        expect(onEnd).toHaveBeenCalledTimes(1);
        expect(remove).toHaveBeenCalledWith('abort', expect.any(Function));
    });

    it('cleans up even if source.stop throws for an already stopped source', async () => {
        const { playSound, source, gain } = await setup();
        const playback = await playSound(dataUri);
        source.stop.mockImplementation(() => {
            throw new Error('already stopped');
        });
        expect(() => playback.stop()).not.toThrow();
        expect(source.disconnect).toHaveBeenCalledTimes(1);
        expect(gain.disconnect).toHaveBeenCalledTimes(1);
        expect(source.onended).toBeNull();
    });

    it('cleans up nodes and the abort listener when source start fails', async () => {
        const { playSound, source, gain } = await setup();
        source.start.mockImplementation(() => {
            throw new Error('start failed');
        });
        const controller = new AbortController();
        const remove = vi.spyOn(controller.signal, 'removeEventListener');

        await expect(playSound(dataUri, { signal: controller.signal })).rejects.toThrow('start failed');
        expect(source.disconnect).toHaveBeenCalledTimes(1);
        expect(gain.disconnect).toHaveBeenCalledTimes(1);
        expect(source.onended).toBeNull();
        expect(remove).toHaveBeenCalledWith('abort', expect.any(Function));
    });

    it('does not start in a context that stays suspended after resume', async () => {
        const { playSound, context, source } = await setup('suspended');
        context.resume.mockResolvedValue(undefined);
        await expect(playSound(dataUri)).rejects.toThrow('Audio context is not running');
        expect(source.start).not.toHaveBeenCalled();
    });

    it('propagates blocked resume and decoder failures without starting audio', async () => {
        const { playSound, context, source } = await setup('suspended');
        context.resume.mockRejectedValueOnce(new Error('blocked'));
        await expect(playSound(dataUri)).rejects.toThrow('blocked');
        context.state = 'running';
        context.decodeAudioData.mockRejectedValueOnce(new Error('invalid audio'));
        await expect(playSound(dataUri)).rejects.toThrow('invalid audio');
        expect(source.start).not.toHaveBeenCalled();
    });

    it('rechecks context state after deferred decode', async () => {
        const { playSound, context, buffer, source } = await setup();
        const decoded = deferred<AudioBuffer>();
        context.decodeAudioData.mockReturnValue(decoded.promise);
        const result = playSound(dataUri);
        context.state = 'suspended';
        decoded.resolve(buffer);

        await expect(result).rejects.toThrow('Audio context is not running');
        expect(source.start).not.toHaveBeenCalled();
    });

    it('reuses the shared context and predecoded buffer for playback', async () => {
        const { playSound, decodeAudioData, getAudioContext, context, buffer } = await setup();
        expect(await decodeAudioData(dataUri)).toBe(buffer);
        const firstContext = getAudioContext();
        await playSound(dataUri);
        expect(getAudioContext()).toBe(firstContext);
        expect(context.decodeAudioData).toHaveBeenCalledTimes(1);
        expect(context.resume).not.toHaveBeenCalled();
    });

    it('rejects unsupported Web Audio and malformed data URIs safely', async () => {
        const { playSound, decodeAudioData } = await setup();
        await expect(decodeAudioData('invalid')).rejects.toThrow('Invalid audio data URI');
        vi.stubGlobal('AudioContext', undefined);
        vi.resetModules();
        const unsupported = await import('./sound-engine');
        await expect(unsupported.playSound(dataUri)).rejects.toThrow('Web Audio is unavailable');
        // An existing valid shared context can still be used by other callers.
        await expect(playSound(dataUri)).resolves.toEqual({ stop: expect.any(Function) });
    });
});
