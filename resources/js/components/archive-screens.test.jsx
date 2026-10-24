import { App } from '@inertiajs/react';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vite-plus/test';
import { NavSecondary } from '@/components/nav-secondary';
import { SidebarProvider } from '@/components/ui/sidebar';
import ArchiveIndex from '@/pages/archive/index';
import ArchiveShow from '@/pages/archive/show';

const archive = {
    uuid: '01911111-1111-7111-8111-111111111111',
    module: 'reference',
    kind: 'package',
    entity: 'reference_group',
    label: '<script>Nama grup</script>',
    parent: { uuid: '01922222-2222-7222-8222-222222222222', label: 'Induk asal' },
    parent_status: 'archived',
    parent_resolved: false,
    target: null,
    records_count: 1,
    items_count: 0,
    archived_at: '2026-10-01T00:00:00Z',
    archived_by_name: 'Penghapus',
};
const meta = { current_page: 2, per_page: 5, last_page: 2, total: 6, from: 6, to: 6 };

function render(Component, props, url = '/archives') {
    return renderToStaticMarkup(
        <App
            initialComponent={Component}
            initialPage={{
                component: url.startsWith('/archives/') ? 'archive/show' : 'archive/index',
                props: { page: { id: 'archive' }, ...props },
                url,
                version: null,
                clearHistory: false,
                encryptHistory: false,
            }}
        />,
    );
}

function ArchiveNavigation() {
    return (
        <SidebarProvider>
            <NavSecondary items={[{ id: 'archive', title: 'Archive', url: '/archives', icon: null }]} />
        </SidebarProvider>
    );
}

describe('Archive read screens', () => {
    it.each([null, 'reference'])('renders later server pages and links with context %s', (context) => {
        const markup = render(ArchiveIndex, {
            resources: { data: [archive], meta },
            filters: { keyword: '', module: context ?? '', kind: '' },
            context,
            modules: ['reference'],
        });
        expect(markup).toContain('&lt;script&gt;Nama grup&lt;/script&gt;');
        expect(markup).not.toContain('<script>Nama grup</script>');
        expect(markup).toContain('Detail');
        expect(markup).toContain(`/archives/${archive.uuid}${context ? '?context=reference' : ''}`);
        expect(markup.includes('>Modul</th>')).toBe(context === null);
        expect(markup).toContain('aria-label="Cari data"');
        expect(markup).toContain('aria-haspopup="dialog"');
        expect(markup).not.toContain('Tambah Data');
        expect(markup).toContain('>Masih diarsipkan<');
        expect(markup).toContain(`#restore`);
        expect(markup).toContain('aria-label="Pulihkan &lt;script&gt;Nama grup&lt;/script&gt;"');
        expect(markup).not.toContain('Konfirmasi pemulihan');
        expect(markup).toContain('aria-label="Hapus permanen &lt;script&gt;Nama grup&lt;/script&gt;"');
        expect(markup).not.toContain('role="alertdialog"');
        expect(markup).not.toContain('type="checkbox"');
    });

    it('renders an empty archive with a search reset, filter dialog, and zero totals', () => {
        const markup = render(ArchiveIndex, {
            resources: { data: [], meta: { ...meta, current_page: 1, total: 0, from: null, to: null } },
            filters: { keyword: 'kosong', module: '', kind: '' },
            context: null,
            modules: [],
        });
        expect(markup).toContain('Tidak ada arsip yang sesuai.');
        expect(markup).toContain('aria-label="Hapus pencarian"');
        expect(markup).toContain('>Filter</button>');
        expect(markup).toContain('value="kosong"');
    });

    it('shows parent details and unsupported reasons with the reference back link', () => {
        const markup = render(
            ArchiveShow,
            {
                resource: { data: archive },
                parent_record: {
                    data: {
                        label: 'Induk arsip',
                        description: 'Deskripsi tersimpan',
                        source_uuid: archive.parent.uuid,
                        unsupported_reason: 'Versi belum didukung.',
                    },
                },
                resources: { data: [], meta },
                context: 'reference',
            },
            `/archives/${archive.uuid}?context=reference`,
        );
        expect(markup).toContain('Deskripsi tersimpan');
        expect(markup).toContain('Versi belum didukung.');
        expect(markup).toContain('href="/reference/archive"');
        expect(markup).toContain('Tidak ada item tersisa di arsip ini.');
    });

    it('shows original identities and unsupported reasons without executable actions', () => {
        const record = {
            uuid: '01933333-3333-7333-8333-333333333333',
            source_uuid: '01944444-4444-7444-8444-444444444444',
            label: 'Item arsip',
            sort_order: 7,
            schema_version: 99,
            supported: false,
            unsupported_reason: 'Snapshot tidak didukung.',
        };
        const markup = render(
            ArchiveShow,
            {
                resource: { data: { ...archive, kind: 'record', parent_status: 'missing' } },
                parent_record: null,
                resources: { data: [record], meta },
                context: null,
            },
            `/archives/${archive.uuid}`,
        );
        expect(markup).toContain(record.source_uuid);
        expect(markup).toContain('Snapshot tidak didukung.');
        expect(markup).toContain('Induk: Tidak tersedia');
        expect(markup).not.toContain('type="checkbox"');
        expect(markup).toMatch(/<button\b(?=[^>]*aria-label="Pulihkan Item arsip")(?=[^>]*disabled)[^>]*>/);
    });

    it.each(['/archives', '/archives?kind=record', `/archives/${archive.uuid}`, '/reference/archive'])(
        'marks secondary Archive navigation active at %s',
        (url) => {
            const markup = render(ArchiveNavigation, {}, url);
            expect(markup).toContain('aria-current="page"');
            expect(markup).toContain('data-active=""');
            expect(markup).toContain('href="/archives"');
        },
    );

    it('does not mark Archive active on other application pages', () => {
        const markup = render(ArchiveNavigation, { page: { id: 'reference' } }, '/reference');
        expect(markup).not.toContain('aria-current="page"');
        expect(markup).not.toContain('data-active=""');
    });
});
