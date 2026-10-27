let audioContext: AudioContext | null = null;
const bufferCache = new Map<string, AudioBuffer>();

export function getAudioContext(): AudioContext {
    if (!audioContext || audioContext.state === 'closed') {
        if (typeof globalThis.AudioContext !== 'function') {
            throw new Error('Web Audio is unavailable.');
        }
        audioContext = new AudioContext();
    }
    return audioContext;
}

/** Cancel waiting without attempting to close the shared audio context. */
async function abortable<T>(work: Promise<T>, signal?: AbortSignal): Promise<T> {
    signal?.throwIfAborted();
    if (!signal) return work;

    return new Promise<T>((resolve, reject) => {
        const abort = () => {
            signal.removeEventListener('abort', abort);
            reject(signal.reason);
        };
        signal.addEventListener('abort', abort, { once: true });
        work.then(
            (value) => {
                signal.removeEventListener('abort', abort);
                resolve(value);
            },
            (error: unknown) => {
                signal.removeEventListener('abort', abort);
                reject(error);
            },
        );
    });
}

export async function decodeAudioData(dataUri: string, signal?: AbortSignal): Promise<AudioBuffer> {
    signal?.throwIfAborted();
    const cached = bufferCache.get(dataUri);
    if (cached) return cached;

    const ctx = getAudioContext();
    const base64 = dataUri.split(',')[1];
    if (!base64) throw new Error('Invalid audio data URI.');
    const binaryString = atob(base64);
    const bytes = new Uint8Array(binaryString.length);
    for (let i = 0; i < binaryString.length; i++) {
        bytes[i] = binaryString.charCodeAt(i);
    }

    const audioBuffer = await abortable(ctx.decodeAudioData(bytes.buffer), signal);
    signal?.throwIfAborted();
    bufferCache.set(dataUri, audioBuffer);
    return audioBuffer;
}

export interface PlaySoundOptions {
    volume?: number;
    playbackRate?: number;
    onEnd?: () => void;
    signal?: AbortSignal;
}

export interface SoundPlayback {
    stop: () => void;
}

export async function playSound(dataUri: string, options: PlaySoundOptions = {}): Promise<SoundPlayback> {
    const { volume = 1, playbackRate = 1, onEnd, signal } = options;
    signal?.throwIfAborted();
    const ctx = getAudioContext();
    if (ctx.state !== 'running' && ctx.state !== 'closed') {
        await abortable(ctx.resume(), signal);
    }
    signal?.throwIfAborted();
    if (ctx.state !== 'running') throw new Error('Audio context is not running.');

    const buffer = await decodeAudioData(dataUri, signal);
    signal?.throwIfAborted();
    if (ctx.state !== 'running') throw new Error('Audio context is not running.');

    const source = ctx.createBufferSource();
    let gain: GainNode | undefined;
    let ended = false;
    const cleanup = () => {
        if (ended) return;
        ended = true;
        source.onended = null;
        signal?.removeEventListener('abort', stop);
        source.disconnect();
        gain?.disconnect();
    };
    const stop = () => {
        if (ended) return;
        try {
            source.stop();
        } catch {
            // The source may already have ended or failed to start.
        }
        cleanup();
    };

    try {
        gain = ctx.createGain();
        source.buffer = buffer;
        source.playbackRate.value = playbackRate;
        gain.gain.value = volume;
        source.connect(gain);
        gain.connect(ctx.destination);
        source.onended = () => {
            cleanup();
            onEnd?.();
        };
        signal?.addEventListener('abort', stop, { once: true });
        signal?.throwIfAborted();
        source.start(0);
    } catch (error) {
        stop();
        throw error;
    }

    return { stop };
}
