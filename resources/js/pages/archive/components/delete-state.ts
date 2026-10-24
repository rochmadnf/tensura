import { buildListingQuery } from '@/lib/listing-query';
import { index, show } from '@/routes/archives';
import { index as referenceArchiveIndex } from '@/routes/ref/archive';
import type {
    ArchiveContext,
    ArchiveDeleteMode,
    ArchiveDeleteResult,
    ArchiveDeleteSelection,
    ArchivePreview,
} from '@/types/archive';

export type DeleteSubject = { uuid: string; label: string };
export interface DeleteState {
    subject: DeleteSubject | null;
    selection: ArchiveDeleteSelection | null;
    preview: ArchivePreview | null;
    phase: 'closed' | 'preview' | 'review' | 'deleting' | 'refresh';
    generation: number;
    notice: string | null;
}
export const initialDeleteState: DeleteState = {
    subject: null,
    selection: null,
    preview: null,
    phase: 'closed',
    generation: 0,
    notice: null,
};

export function deleteSelection(mode: ArchiveDeleteMode, uuids: string[] = []): ArchiveDeleteSelection | null {
    const selected = [...new Set(uuids)].sort();
    if ((mode === 'child' && selected.length !== 1) || (mode === 'children_selected' && selected.length === 0))
        return null;
    return { action: 'delete_forever', mode, ...(mode !== 'package' ? { record_uuids: selected } : {}) };
}

export function canDeleteArchive(state: DeleteState): boolean {
    return (
        state.phase === 'review' &&
        state.preview?.can_confirm === true &&
        !!state.preview.confirmation_token &&
        state.preview.archive_uuid === state.subject?.uuid &&
        state.preview.action === 'delete_forever' &&
        state.preview.mode === state.selection?.mode
    );
}

export type DeleteAction =
    | {
          type: 'preview';
          subject: DeleteSubject;
          selection: ArchiveDeleteSelection;
          generation: number;
          notice?: string;
      }
    | { type: 'review'; preview: ArchivePreview; generation: number }
    | { type: 'failed'; message: string; generation: number }
    | { type: 'delete' }
    | { type: 'deleted' }
    | { type: 'close' };

export function deleteReducer(state: DeleteState, action: DeleteAction): DeleteState {
    switch (action.type) {
        case 'preview':
            return {
                subject: action.subject,
                selection: action.selection,
                preview: null,
                phase: 'preview',
                generation: action.generation,
                notice: action.notice ?? null,
            };
        case 'review':
            return action.generation === state.generation && state.phase === 'preview'
                ? { ...state, preview: action.preview, phase: 'review' }
                : state;
        case 'failed':
            return action.generation === state.generation
                ? { ...state, preview: null, phase: 'review', notice: action.message }
                : state;
        case 'delete':
            return canDeleteArchive(state) ? { ...state, phase: 'deleting' } : state;
        case 'deleted':
            return { ...state, preview: null, phase: 'refresh' };
        case 'close':
            return { ...initialDeleteState, generation: state.generation + 1 };
    }
}

export function deletionFailure(error: unknown): { changed: boolean; message: string } {
    const response = typeof error === 'object' && error !== null && 'response' in error ? error.response : null;
    if (typeof response === 'object' && response !== null && 'status' in response) {
        if (response.status === 409 && 'data' in response) {
            try {
                const data = typeof response.data === 'string' ? JSON.parse(response.data) : response.data;
                if (data?.code === 'archive_changed')
                    return {
                        changed: true,
                        message:
                            'Isi arsip berubah sejak konfirmasi disiapkan. Tinjau nama dan jumlah terbaru, lalu konfirmasi ulang penghapusan permanen.',
                    };
            } catch {
                /* A non-JSON error cannot authorize another delete. */
            }
        }
        if (response.status === 404)
            return {
                changed: false,
                message: 'Arsip sudah diproses atau tidak tersedia. Tutup dialog dan muat ulang daftar arsip.',
            };
        if (response.status === 401 || response.status === 419)
            return {
                changed: false,
                message: 'Sesi berakhir. Muat ulang halaman dan masuk kembali sebelum menghapus arsip.',
            };
    }
    return {
        changed: false,
        message:
            'Hasil penghapusan belum dapat dipastikan. Periksa koneksi lalu siapkan konfirmasi baru sebelum mencoba lagi.',
    };
}

export function deletionDestination(
    result: ArchiveDeleteResult,
    location: 'index' | 'detail',
    context: ArchiveContext,
    search: string,
) {
    if (location === 'detail' && result.archive_exists)
        return {
            url: show(result.archive_uuid).url,
            query: buildListingQuery(search, { page: 1, context, target_keyword: null, target_page: null }),
            only: ['resource', 'parent_record', 'resources'],
        };
    return {
        url: context === 'reference' ? referenceArchiveIndex().url : index().url,
        query: location === 'index' ? buildListingQuery(search, { page: 1 }) : {},
        only: location === 'index' ? ['resources', 'filters'] : undefined,
    };
}
