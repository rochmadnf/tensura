import type { ArchiveParentStatus } from '@/types/archive';

export const parentStatusLabels: Record<ArchiveParentStatus, string> = {
    archived: 'Masih diarsipkan',
    active: 'Aktif',
    missing: 'Tidak tersedia',
    unavailable: 'Belum didukung',
};

export function moduleLabel(module: string): string {
    return module === 'reference' ? 'Reference' : module;
}

export function archivedDate(value: string | null): string {
    if (!value) return '—';
    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'Asia/Makassar',
    }).format(new Date(value));
}
