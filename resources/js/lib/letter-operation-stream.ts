import type { LetterOperation } from '@/schemas/letter-preview';

export function connectLetterOperations({
    url,
    actor,
    onOperation,
    onTerminal,
    onConnection,
}: {
    url: string;
    actor: string;
    onOperation: (operation: LetterOperation) => void;
    onTerminal: (operation: LetterOperation) => void;
    onConnection: (connected: boolean) => void;
}) {
    let closed = false;
    let source: EventSource | null = null;
    let timer: ReturnType<typeof setTimeout> | undefined;
    let delay = 1000;
    const revisions = new Map<string, number>();
    const storageKey = `letters.notifications.${actor}`;
    let announced: Record<string, number> = {};
    try {
        const stored: unknown = JSON.parse(sessionStorage.getItem(storageKey) ?? '{}');
        if (stored && typeof stored === 'object' && !Array.isArray(stored)) {
            announced = Object.fromEntries(Object.entries(stored).filter(([, value]) => Number.isSafeInteger(value)));
        }
    } catch {
        /* Storage can be unavailable. */
    }
    function connect() {
        if (closed || typeof EventSource === 'undefined') return;
        const connection = new EventSource(url, { withCredentials: true });
        source = connection;
        const current = () => !closed && source === connection;
        connection.onopen = () => {
            if (current()) {
                delay = 1000;
                onConnection(true);
            }
        };
        connection.addEventListener('operations', (event) => {
            if (!current()) return;
            try {
                const payload: unknown = JSON.parse((event as MessageEvent).data);
                if (
                    !payload ||
                    typeof payload !== 'object' ||
                    !('operations' in payload) ||
                    !Array.isArray(payload.operations)
                )
                    return;
                for (const operation of payload.operations as LetterOperation[]) {
                    if (
                        typeof operation.id !== 'string' ||
                        typeof operation.letter_id !== 'string' ||
                        !Number.isInteger(operation.status_revision) ||
                        !['queued', 'running', 'succeeded', 'failed', 'stale'].includes(operation.status)
                    )
                        continue;
                    if ((revisions.get(operation.id) ?? 0) >= operation.status_revision) continue;
                    revisions.set(operation.id, operation.status_revision);
                    onOperation(operation);
                    if (
                        ['succeeded', 'failed', 'stale'].includes(operation.status) &&
                        (announced[operation.id] ?? 0) < operation.status_revision
                    ) {
                        announced[operation.id] = operation.status_revision;
                        onTerminal(operation);
                    }
                }
                // Bound per-tab storage while retaining the latest announcements.
                announced = Object.fromEntries(Object.entries(announced).slice(-1000));
                try {
                    sessionStorage.setItem(storageKey, JSON.stringify(announced));
                } catch {
                    /* In-memory dedup remains active. */
                }
            } catch {
                /* Ignore malformed events; reconnect snapshots restore durable state. */
            }
        });
        connection.onerror = () => {
            if (!current()) return;
            source = null;
            connection.close();
            clearTimeout(timer);
            onConnection(false);
            timer = setTimeout(connect, delay);
            delay = Math.min(30000, delay * 2);
        };
    }
    connect();
    return () => {
        closed = true;
        source?.close();
        clearTimeout(timer);
    };
}
