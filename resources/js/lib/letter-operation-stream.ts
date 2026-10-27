import type { LetterOperation } from '@/schemas/letter-preview';

export function connectLetterOperations({
    url,
    onOperation,
    onNotifications,
    onConnection,
    onNewNotification,
}: {
    url: string;
    onOperation: (operation: LetterOperation) => void;
    onNotifications: () => void;
    onConnection: (connected: boolean) => void;
    onNewNotification?: (id: string) => void;
}) {
    let closed = false;
    let source: EventSource | null = null;
    let timer: ReturnType<typeof setTimeout> | undefined;
    let delay = 1000;
    const revisions = new Map<string, number>();
    const notificationIds = new Set<string>();
    function connect() {
        if (closed || typeof EventSource === 'undefined') return;
        const connection = new EventSource(url, { withCredentials: true });
        source = connection;
        const current = () => !closed && source === connection;
        connection.onopen = () => {
            if (current()) {
                delay = 1000;
                onConnection(true);
                if (current()) onNotifications();
            }
        };
        connection.addEventListener('notifications', (event) => {
            if (!current()) return;
            onNotifications();
            if (!current() || !onNewNotification) return;
            let payload: unknown;
            try {
                payload = JSON.parse((event as MessageEvent).data);
            } catch {
                return;
            }
            if (
                !payload ||
                typeof payload !== 'object' ||
                !('reason' in payload) ||
                payload.reason !== 'created' ||
                !('notification_id' in payload) ||
                typeof payload.notification_id !== 'string' ||
                !/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(payload.notification_id)
            )
                return;
            const key = payload.notification_id.toLowerCase();
            if (notificationIds.has(key)) return;
            notificationIds.add(key);
            if (notificationIds.size > 1000) notificationIds.delete(notificationIds.values().next().value!);
            onNewNotification(payload.notification_id);
        });
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
                    if (!current()) return;
                    if (
                        !operation ||
                        typeof operation !== 'object' ||
                        typeof operation.id !== 'string' ||
                        typeof operation.letter_id !== 'string' ||
                        !Number.isInteger(operation.status_revision) ||
                        !['queued', 'running', 'succeeded', 'failed', 'stale'].includes(operation.status)
                    )
                        continue;
                    if ((revisions.get(operation.id) ?? 0) >= operation.status_revision) continue;
                    revisions.set(operation.id, operation.status_revision);
                    onOperation(operation);
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
            if (closed) return;
            timer = setTimeout(connect, delay);
            delay = Math.min(30000, delay * 2);
        };
    }
    connect();
    return () => {
        if (closed) return;
        closed = true;
        source?.close();
        source = null;
        clearTimeout(timer);
    };
}
