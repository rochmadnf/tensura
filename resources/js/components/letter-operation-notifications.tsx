import { http, router } from '@inertiajs/react';
import { createContext, useContext, useEffect, useState, useSyncExternalStore, type ReactNode } from 'react';
import { connectLetterOperations } from '@/lib/letter-operation-stream';
import { NotificationAlerts } from '@/lib/notification-alerts';
import { NotificationController, type NotificationApi } from '@/lib/notification-center';
import { events, show } from '@/routes/ltr';
import { index, read, readAll, summary } from '@/routes/notifications';
import type { NotificationItem } from '@/types/notification';

async function request<T>(url: string, method: 'get' | 'patch', signal: AbortSignal): Promise<T> {
    const response = await http.getClient().request({ url, method, signal, headers: { Accept: 'application/json' } });
    return JSON.parse(response.data) as T;
}

const api: NotificationApi = {
    summary: (signal) => request(summary().url, 'get', signal),
    index: (filter, cursor, signal) =>
        request(index({ query: { filter, ...(cursor ? { cursor } : {}) } }).url, 'get', signal),
    read: (id, signal) => request(id === null ? readAll().url : read(id).url, 'patch', signal),
};

const NotificationContext = createContext<{
    controller: NotificationController;
    alerts: NotificationAlerts;
    enabled: boolean;
} | null>(null);

export function NotificationProvider({ children, userId }: { children: ReactNode; userId: string | number | null }) {
    const [context] = useState(() => ({
        controller: new NotificationController(api),
        alerts: new NotificationAlerts(String(userId ?? 'guest')),
        enabled: userId !== null,
    }));
    const { controller, alerts } = context;
    useEffect(() => {
        if (!context.enabled) return;
        controller.start();
        alerts.start();
        const disconnect = connectLetterOperations({
            url: events().url,
            onOperation: (operation) =>
                window.dispatchEvent(new CustomEvent('letter-operation', { detail: operation })),
            onNotifications: controller.invalidate,
            onNewNotification: alerts.receive,
            onConnection: controller.setConnected,
        });
        const onFocus = () => controller.invalidate();
        const onVisible = () => {
            if (document.visibilityState === 'visible') controller.invalidate();
        };
        window.addEventListener('focus', onFocus);
        document.addEventListener('visibilitychange', onVisible);
        return () => {
            controller.dispose();
            alerts.dispose();
            disconnect();
            window.removeEventListener('focus', onFocus);
            document.removeEventListener('visibilitychange', onVisible);
        };
    }, [context, controller, alerts]);
    return <NotificationContext.Provider value={context}>{children}</NotificationContext.Provider>;
}

export function useNotifications() {
    const context = useContext(NotificationContext);
    if (!context) throw new Error('useNotifications requires NotificationProvider');
    const { controller, alerts, enabled } = context;
    const state = useSyncExternalStore(controller.subscribe, controller.getSnapshot, controller.getSnapshot);
    const alertState = useSyncExternalStore(alerts.subscribe, alerts.getSnapshot, alerts.getSnapshot);

    async function openLetter(item: NotificationItem): Promise<void> {
        await controller.openLetter(item, (letterId) => router.visit(show(letterId).url));
    }

    return { ...state, ...alertState, enabled, controller, alerts, openLetter };
}
