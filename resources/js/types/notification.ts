export type NotificationFilter = 'all' | 'unread';

export type NotificationItem = {
    id: string;
    type: string;
    title: string;
    subject: string;
    message: string | null;
    letter_id: string;
    operation_id: string;
    status: 'succeeded' | 'failed' | 'stale';
    created_at: string;
    read_at: string | null;
};

export type NotificationSummary = { unread_count: number };

export type NotificationPage = NotificationSummary & {
    data: NotificationItem[];
    next_cursor: string | null;
};
