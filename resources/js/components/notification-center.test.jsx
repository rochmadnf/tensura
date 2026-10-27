import { renderToStaticMarkup } from 'react-dom/server';
import { beforeEach, describe, expect, it, vi } from 'vite-plus/test';
import { NotificationCenter, NotificationPanel } from './notification-center';

const context = vi.hoisted(() => ({ value: null }));
vi.mock('@/components/letter-operation-notifications', () => ({ useNotifications: () => context.value }));

const item = {
    id: 'notice',
    type: 'letter-operation',
    title: 'PDF surat siap',
    subject: 'Surat tugas <penting>',
    message: 'Dokumen tersedia & dapat dibuka.',
    letter_id: 'letter',
    operation_id: 'operation',
    status: 'succeeded',
    created_at: '2026-10-27T08:00:00Z',
    read_at: null,
};
beforeEach(() => {
    context.value = {
        enabled: true,
        arrivalRevision: 0,
        soundSupported: false,
        soundReady: false,
        enablingSound: false,
        alerts: { enableSound: vi.fn() },
        open: false,
        filter: 'all',
        items: [],
        unreadCount: 0,
        nextCursor: null,
        connected: true,
        loading: false,
        loadingMore: false,
        summaryError: false,
        listError: false,
        pageError: false,
        mutationError: false,
        failedAction: null,
        pending: null,
        controller: {
            setOpen: vi.fn(),
            setFilter: vi.fn(),
            markRead: vi.fn(),
            loadMore: vi.fn(),
            refresh: vi.fn(),
            retryMutation: vi.fn(),
        },
        openLetter: vi.fn(),
    };
});

describe('notification bell', () => {
    it.each([
        [0, null],
        [1, '1'],
        [99, '99'],
        [100, '99+'],
        [1800, '99+'],
    ])('shows the capped badge for %s unread', (count, badge) => {
        context.value.unreadCount = count;
        const markup = renderToStaticMarkup(<NotificationCenter />);

        expect(markup).toContain(`aria-label="${count ? `Notifikasi, ${count} belum dibaca` : 'Notifikasi'}"`);
        expect(markup).toContain('aria-haspopup="dialog"');
        if (badge) {
            expect(markup).toContain('data-slot="badge"');
            expect(markup).toContain(`>${badge}</span>`);
        } else {
            expect(markup).not.toContain('data-slot="badge"');
        }
        expect(markup).not.toContain('data-slot="sheet-content"');
    });

    it('renders nothing for a signed out account', () => {
        context.value.enabled = false;
        expect(renderToStaticMarkup(<NotificationCenter />)).toBe('');
    });

    it('keeps connection errors out of the header', () => {
        context.value.connected = false;
        context.value.summaryError = true;
        const markup = renderToStaticMarkup(<NotificationCenter />);

        expect(markup).not.toContain('Menyambungkan');
        expect(markup).not.toContain('role="alert"');
    });
});

describe('notification sound readiness', () => {
    it('offers explicit audio activation while preserving browser autoplay restrictions', () => {
        context.value.soundSupported = true;
        const markup = renderToStaticMarkup(<NotificationPanel />);

        expect(markup).toContain('Aktifkan suara untuk notifikasi baru.');
        expect(markup).toContain('>Aktifkan suara</button>');
        expect(context.value.alerts.enableSound).not.toHaveBeenCalled();
    });

    it('shows audio readiness without an activation button once unlocked', () => {
        context.value.soundSupported = true;
        context.value.soundReady = true;
        const markup = renderToStaticMarkup(<NotificationPanel />);

        expect(markup).toContain('Suara notifikasi aktif');
        expect(markup).not.toContain('>Aktifkan suara</button>');
    });

    it('disables repeated activation while audio is being enabled', () => {
        context.value.soundSupported = true;
        context.value.enablingSound = true;
        const markup = renderToStaticMarkup(<NotificationPanel />);

        expect(markup).toMatch(/<button\b(?=[^>]*disabled)[^>]*>Mengaktifkan…<\/button>/);
    });
});

describe('notification panel', () => {
    it.each([
        ['read', 'Coba tandai dibaca lagi'],
        ['readAll', 'Coba tandai semua dibaca lagi'],
        ['open', 'Coba buka surat lagi'],
    ])('retries a failed %s action through the write retry control', (action, label) => {
        context.value.mutationError = true;
        context.value.failedAction = action;
        const markup = renderToStaticMarkup(<NotificationPanel />);
        expect(markup).toContain(label);
        expect(markup).not.toContain('>Coba lagi</button>');

        function findAction(element) {
            if (!element || typeof element !== 'object') return null;
            if (element.props?.children === label) return element;
            const children = element.props?.children;
            for (const child of Array.isArray(children) ? children : [children]) {
                const match = findAction(child);
                if (match) return match;
            }
            return null;
        }
        const retry = findAction(NotificationPanel());
        expect(retry).not.toBeNull();
        retry.props.onClick();

        expect(context.value.controller.retryMutation).toHaveBeenCalledTimes(1);
        expect(context.value.controller.refresh).not.toHaveBeenCalled();
    });

    it('offers separate read and write retries when both requests failed', () => {
        context.value.mutationError = true;
        context.value.failedAction = 'readAll';
        context.value.listError = true;
        const markup = renderToStaticMarkup(<NotificationPanel />);

        expect(markup).toContain('Coba tandai semua dibaca lagi');
        expect(markup).toContain('>Coba lagi</button>');
        expect(markup.match(/role="alert"/g)).toHaveLength(2);
    });

    it.each(['all', 'unread'])('renders the %s empty state with filters and no read action on render', (filter) => {
        context.value.filter = filter;
        const markup = renderToStaticMarkup(<NotificationPanel />);

        expect(markup).toContain(filter === 'all' ? 'Belum ada notifikasi' : 'Semua notifikasi sudah dibaca');
        expect(markup).toContain('data-slot="empty"');
        expect(markup).toContain('aria-label="Filter notifikasi"');
        expect(markup).toContain('aria-pressed="true"');
        expect(context.value.controller.markRead).not.toHaveBeenCalled();
    });

    it('shows skeletons and an accessible loading state without claiming the list is empty', () => {
        context.value.loading = true;
        const markup = renderToStaticMarkup(<NotificationPanel />);

        expect(markup).toContain('Memuat notifikasi…');
        expect(markup).toContain('aria-busy="true"');
        expect(markup).toContain('data-slot="skeleton"');
        expect(markup).not.toContain('Belum ada notifikasi');
    });

    it('shows a quiet reconnect status, failure message and retry inside the panel', () => {
        context.value.connected = false;
        context.value.listError = true;
        const markup = renderToStaticMarkup(<NotificationPanel />);

        expect(markup).toContain('Menyambungkan ulang.');
        expect(markup).toContain('role="status"');
        expect(markup).toContain('Notifikasi belum dapat diperbarui');
        expect(markup).toContain('Coba lagi');
        expect(markup).not.toContain('data-slot="empty"');
    });

    it.each([
        ['succeeded', 'Berhasil'],
        ['failed', 'Gagal'],
        ['stale', 'Usang'],
    ])('renders %s details and textual unread indication with escaped content', (status, label) => {
        context.value.items = [{ ...item, status }];
        const markup = renderToStaticMarkup(<NotificationPanel />);

        expect(markup).toContain(label);
        expect(markup).toContain('PDF surat siap');
        expect(markup).toContain('Surat tugas &lt;penting&gt;');
        expect(markup).toContain('Dokumen tersedia &amp; dapat dibuka.');
        expect(markup).toContain('dateTime="2026-10-27T08:00:00Z"');
        expect(markup).toContain('Tandai dibaca');
        expect(markup).toContain('Buka surat');
        expect(markup.match(/Belum dibaca/g)).toHaveLength(2);
    });

    it('omits the individual mark-read action for read entries', () => {
        context.value.items = [{ ...item, read_at: '2026-10-27T09:00:00Z', message: null }];
        const markup = renderToStaticMarkup(<NotificationPanel />);

        expect(markup).not.toContain('Tandai dibaca');
        expect(markup).not.toContain('Dokumen tersedia');
        expect(markup).toContain('Buka surat');
    });

    it('retains rows while refreshing and offers pagination retry', () => {
        context.value.items = [item];
        context.value.loading = true;
        context.value.pageError = true;
        context.value.nextCursor = 'cursor';
        const markup = renderToStaticMarkup(<NotificationPanel />);

        expect(markup).toContain('Surat tugas &lt;penting&gt;');
        expect(markup).toContain('Notifikasi berikutnya gagal dimuat');
        expect(markup).toContain('Coba lagi');
        expect(markup).not.toContain('data-slot="skeleton"');
    });

    it('disables conflicting row actions while a write is pending and explains failed writes', () => {
        context.value.items = [item];
        context.value.pending = item.id;
        context.value.mutationError = true;
        const markup = renderToStaticMarkup(<NotificationPanel />);
        const row = markup.slice(markup.indexOf('<li '));

        expect(markup).toContain('Status dibaca belum tersimpan');
        expect(row).toContain('Menyimpan…');
        const buttons = row.match(/<button\b[^>]*>/g);
        expect(buttons).toHaveLength(2);
        expect(buttons.every((button) => button.includes('disabled=""'))).toBe(true);
    });
});
