import type { NotificationFilter, NotificationItem, NotificationPage, NotificationSummary } from '@/types/notification';

export type NotificationApi = {
    summary: (signal: AbortSignal) => Promise<NotificationSummary>;
    index: (filter: NotificationFilter, cursor: string | null, signal: AbortSignal) => Promise<NotificationPage>;
    read: (id: string | null, signal: AbortSignal) => Promise<NotificationSummary>;
};

export type NotificationState = {
    open: boolean;
    filter: NotificationFilter;
    items: NotificationItem[];
    nextCursor: string | null;
    unreadCount: number;
    connected: boolean;
    loading: boolean;
    loadingMore: boolean;
    summaryError: boolean;
    listError: boolean;
    pageError: boolean;
    mutationError: boolean;
    failedAction: 'read' | 'readAll' | 'open' | null;
    pending: string | null;
};

/** Owns one account's requests; aborted transports may still resolve, so every response is also checked. */
export class NotificationController {
    private state: NotificationState = {
        open: false,
        filter: 'all',
        items: [],
        nextCursor: null,
        unreadCount: 0,
        connected: true,
        loading: false,
        loadingMore: false,
        summaryError: false,
        listError: false,
        pageError: false,
        mutationError: false,
        failedAction: null,
        pending: null,
    };
    private listeners = new Set<() => void>();
    private active = false;
    private summaryRequest?: AbortController;
    private listRequest?: AbortController;
    private mutationRequest?: AbortController;
    private timer?: ReturnType<typeof setTimeout>;
    private countRevision = 0;
    private appliedCountRevision = 0;
    private retryFailedMutation: (() => Promise<unknown>) | null = null;
    private readItems = new Map<string, string>();

    constructor(private api: NotificationApi) {}

    getSnapshot = (): NotificationState => this.state;

    subscribe = (listener: () => void): (() => void) => {
        this.listeners.add(listener);
        return () => this.listeners.delete(listener);
    };

    private update(patch: Partial<NotificationState>): void {
        this.state = { ...this.state, ...patch };
        this.listeners.forEach((listener) => listener());
    }

    private countUpdate(revision: number, unreadCount: number): Partial<NotificationState> {
        if (revision <= this.appliedCountRevision) return {};
        this.appliedCountRevision = revision;
        return { unreadCount };
    }

    start = (): void => {
        this.active = true;
        this.refresh();
    };

    dispose = (): void => {
        this.active = false;
        clearTimeout(this.timer);
        this.timer = undefined;
        this.summaryRequest?.abort();
        this.listRequest?.abort();
        this.mutationRequest?.abort();
        this.retryFailedMutation = null;
    };

    isActive = (): boolean => this.active;

    openLetter = async (item: NotificationItem, navigate: (letterId: string) => void): Promise<void> => {
        if (!(await this.markRead(item.id))) {
            if (this.active && this.state.mutationError && !this.state.pending) {
                this.retryFailedMutation = () => this.openLetter(item, navigate);
                this.update({ failedAction: 'open' });
            }
            return;
        }
        if (!this.active) return;
        this.setOpen(false);
        navigate(item.letter_id);
    };

    retryMutation = async (): Promise<void> => {
        if (!this.active || this.state.pending || !this.retryFailedMutation) return;
        await this.retryFailedMutation();
    };

    setConnected = (connected: boolean): void => {
        if (!this.active) return;
        this.update({ connected });
        if (connected) this.invalidate();
    };

    /** Snapshot bursts, reconnects, and tab activation share one trailing refresh. */
    invalidate = (): void => {
        if (!this.active || this.timer !== undefined) return;
        this.timer = setTimeout(() => {
            this.timer = undefined;
            this.refresh();
        }, 100);
    };

    refresh = (): void => {
        if (!this.active || this.state.pending) return;
        clearTimeout(this.timer);
        this.timer = undefined;
        void this.loadSummary();
        if (this.state.open) void this.loadList(false);
    };

    setOpen = (open: boolean): void => {
        if (!this.active || open === this.state.open) return;
        this.listRequest?.abort();
        this.update({ open, loading: false, loadingMore: false });
        if (open) this.refresh();
    };

    setFilter = (filter: NotificationFilter): void => {
        if (!this.active || filter === this.state.filter) return;
        this.listRequest?.abort();
        this.update({
            filter,
            items: [],
            nextCursor: null,
            loading: false,
            loadingMore: false,
            listError: false,
            pageError: false,
        });
        if (this.state.open) void this.loadList(false);
    };

    loadMore = (): void => {
        if (!this.state.nextCursor || this.state.loading || this.state.loadingMore) return;
        void this.loadList(true);
    };

    private async loadSummary(): Promise<void> {
        this.summaryRequest?.abort();
        const request = new AbortController();
        this.summaryRequest = request;
        const revision = ++this.countRevision;
        try {
            const result = await this.api.summary(request.signal);
            if (!this.active || request.signal.aborted) return;
            this.update({
                summaryError: false,
                ...this.countUpdate(revision, result.unread_count),
            });
        } catch {
            if (this.active && !request.signal.aborted) this.update({ summaryError: true });
        }
    }

    private async loadList(append: boolean): Promise<void> {
        if (!this.active || !this.state.open || this.state.pending) return;
        this.listRequest?.abort();
        const request = new AbortController();
        this.listRequest = request;
        const revision = ++this.countRevision;
        const filter = this.state.filter;
        const cursor = append ? this.state.nextCursor : null;
        this.update({ loading: !append, loadingMore: append, listError: false, pageError: false });
        try {
            const result = await this.api.index(filter, cursor, request.signal);
            if (!this.active || request.signal.aborted) return;
            const incoming = result.data.map((item) => {
                if (item.read_at) this.readItems.set(item.id, item.read_at);
                return { ...item, read_at: this.readItems.get(item.id) ?? item.read_at };
            });
            const items = [
                ...new Map([...(append ? this.state.items : []), ...incoming].map((item) => [item.id, item])).values(),
            ];
            this.update({
                items: filter === 'unread' ? items.filter((item) => !item.read_at) : items,
                nextCursor: result.next_cursor,
                ...this.countUpdate(revision, result.unread_count),
            });
        } catch {
            if (this.active && !request.signal.aborted) this.update(append ? { pageError: true } : { listError: true });
        } finally {
            if (this.active && !request.signal.aborted) this.update({ loading: false, loadingMore: false });
        }
    }

    /** Mutations are serialized; GETs cannot cross a write and restore an old unread count or row. */
    markRead = async (id: string | null): Promise<boolean> => {
        if (!this.active || this.state.pending) return false;
        this.retryFailedMutation = null;
        if (id && (this.readItems.has(id) || this.state.items.some((item) => item.id === id && item.read_at))) {
            this.update({ mutationError: false, failedAction: null });
            return true;
        }
        this.summaryRequest?.abort();
        this.listRequest?.abort();
        const revision = ++this.countRevision;
        const request = new AbortController();
        this.mutationRequest = request;
        this.update({
            pending: id ?? 'all',
            mutationError: false,
            failedAction: null,
            loading: false,
            loadingMore: false,
        });
        try {
            const result = await this.api.read(id, request.signal);
            if (!this.active || request.signal.aborted) return false;
            const readAt = new Date().toISOString();
            if (id) this.readItems.set(id, readAt);
            const items = this.state.items.map((item) => {
                if (id === null || item.id === id) {
                    this.readItems.set(item.id, item.read_at ?? readAt);
                    return { ...item, read_at: item.read_at ?? readAt };
                }
                return item;
            });
            this.update({
                items: this.state.filter === 'unread' ? items.filter((item) => !item.read_at) : items,
                ...this.countUpdate(revision, result.unread_count),
            });
            return true;
        } catch {
            if (this.active && !request.signal.aborted) {
                this.retryFailedMutation = () => this.markRead(id);
                this.update({ mutationError: true, failedAction: id === null ? 'readAll' : 'read' });
            }
            return false;
        } finally {
            if (this.active && !request.signal.aborted) {
                this.update({ pending: null });
                this.invalidate();
            }
        }
    };
}
