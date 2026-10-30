import { App } from '@inertiajs/react';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vite-plus/test';
import WorkCalendarIndex from './index';

function render(calendarData) {
    return renderToStaticMarkup(
        <App
            initialComponent={WorkCalendarIndex}
            initialPage={{
                component: 'work-calendar/index',
                props: {
                    app: { name: 'Tensura' },
                    auth: { user: { id: 'user-1', name: 'Admin' } },
                    page: { id: 'work-calendar' },
                    calendarData,
                },
                url: '/work-calendar',
                version: null,
                clearHistory: false,
                encryptHistory: false,
            }}
        />,
    );
}

const mockCalendarData = {
    contract_version: 1,
    timezone: 'Asia/Jakarta',
    query: {
        year: 2026,
        date: '2026-10-30',
        view: 'month',
        search: null,
        kind: null,
    },
    calendar: {
        id: 'cal-2026',
        year: 2026,
        revision: 2,
        work_revision: 2,
        status: 'ready',
        working_weekdays: [1, 2, 3, 4, 5, 6],
        confirmed_at: '2026-10-01T08:00:00Z',
        confirmed_by: { id: 'user-1', name: 'Admin' },
    },
    calendar_years: [
        {
            id: 'cal-2026',
            year: 2026,
            revision: 2,
            work_revision: 2,
            status: 'ready',
            working_weekdays: [1, 2, 3, 4, 5, 6],
            confirmed_at: '2026-10-01T08:00:00Z',
            confirmed_by: { id: 'user-1', name: 'Admin' },
        },
    ],
    period: {
        start_date: '2026-09-28',
        end_date: '2026-11-08',
    },
    days: [
        {
            date: '2026-10-30',
            weekday: 5,
            in_selected_month: true,
            is_today: true,
            is_working: true,
            basis: 'weekly',
            exception_id: null,
        },
        {
            date: '2026-10-31',
            weekday: 6,
            in_selected_month: true,
            is_today: false,
            is_working: true,
            basis: 'weekly',
            exception_id: null,
        },
    ],
    events: [
        {
            id: 'ev-1',
            revision: 1,
            title: 'Rapat Anggaran',
            kind: 'activity',
            start_date: '2026-10-30',
            end_date: '2026-10-30',
            all_day: false,
            start_time: '09:00',
            end_time: '11:00',
            location: 'Ruang Rapat Utama',
            description: 'Membahas pagu indikatif',
            origin: 'manual',
            source_url: null,
            imported_at: null,
            calendar_revisions: { 2026: 2 },
        },
        {
            id: 'ev-2',
            revision: 1,
            title: 'Hari Libur Nasional',
            kind: 'holiday',
            start_date: '2026-10-31',
            end_date: '2026-10-31',
            all_day: true,
            start_time: null,
            end_time: null,
            location: null,
            description: null,
            origin: 'manual',
            source_url: null,
            imported_at: null,
            calendar_revisions: { 2026: 2 },
        },
    ],
    missing_years: [],
    truncated: false,
};

describe('Work Calendar Page', () => {
    it('renders month view with toolbar, actions, and events', () => {
        const markup = render(mockCalendarData);

        expect(markup).toContain('Kalender Kerja');
        expect(markup).toContain('Hari Ini');
        expect(markup).toContain('Tambah Kegiatan');
        expect(markup).toContain('Ambil Hari Libur');
        expect(markup).toContain('Pengaturan Tahun');
        expect(markup).toContain('Bulan');
        expect(markup).toContain('Minggu');
        expect(markup).toContain('Hari');
        expect(markup).toContain('Agenda');
        expect(markup).toContain('Rapat Anggaran');
        expect(markup).toContain('Hari Libur Nasional');
    });

    it('renders warning alert when calendar year is unconfigured', () => {
        const data = {
            ...mockCalendarData,
            calendar: null,
            calendar_years: [],
        };
        const markup = render(data);

        expect(markup).toContain('Kalender Belum Diatur');
        expect(markup).toContain('Kalender tahun 2026 belum diinisialisasi');
        expect(markup).toContain('Inisialisasi Tahun 2026');
    });

    it('renders warning alert when calendar year is draft / unconfirmed', () => {
        const data = {
            ...mockCalendarData,
            calendar: {
                ...mockCalendarData.calendar,
                status: 'draft',
                confirmed_at: null,
            },
        };
        const markup = render(data);

        expect(markup).toContain('Konfirmasi Kesiapan Diperlukan');
        expect(markup).toContain('Periksa &amp; Konfirmasi');
    });

    it('renders agenda view when view is set to agenda', () => {
        const data = {
            ...mockCalendarData,
            query: {
                ...mockCalendarData.query,
                view: 'agenda',
            },
        };
        const markup = render(data);

        expect(markup).toContain('Rapat Anggaran');
        expect(markup).toContain('Ruang Rapat Utama');
        expect(markup).toContain('09:00 s/d 11:00');
        expect(markup).toContain('Hari Libur Nasional');
        expect(markup).toContain('Sepanjang hari');
    });
});
