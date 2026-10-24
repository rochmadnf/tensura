import type { RowSelectionState, Updater } from '@tanstack/react-table';
import type {
    ArchivePreview,
    ArchiveRestoreMode,
    ArchiveRestoreResult,
    ArchiveRestoreSelection,
    ArchiveTarget,
} from '@/types/archive';

export interface RestoreState {
    mode: ArchiveRestoreMode;
    childUuid: string | null;
    target: ArchiveTarget | null;
    rowSelection: RowSelectionState;
    preview: ArchivePreview | null;
    previewSelection: ArchiveRestoreSelection | null;
    confirmationOpen: boolean;
    activity: 'idle' | 'preview' | 'restore' | 'rename' | 'refresh';
    generation: number;
    notice: string | null;
    result: ArchiveRestoreResult | null;
    renameDirty: boolean;
}

export function initialRestoreState(hasParent: boolean, standalone: boolean): RestoreState {
    return {
        mode: hasParent ? 'parent_with_children' : standalone ? 'child' : 'children_all',
        childUuid: null,
        target: null,
        rowSelection: {},
        preview: null,
        previewSelection: null,
        confirmationOpen: false,
        activity: 'idle',
        generation: 0,
        notice: null,
        result: null,
        renameDirty: false,
    };
}

export function selectedRecordUuids(selection: RowSelectionState): string[] {
    return Object.keys(selection)
        .filter((uuid) => selection[uuid])
        .sort();
}

export function restoreSelection(state: RestoreState): ArchiveRestoreSelection {
    return {
        action: 'restore',
        mode: state.mode,
        ...(state.mode === 'child' ? { record_uuids: state.childUuid ? [state.childUuid] : [] } : {}),
        ...(state.mode === 'children_selected' ? { record_uuids: selectedRecordUuids(state.rowSelection) } : {}),
        ...(state.target ? { target_group_uuid: state.target.uuid } : {}),
    };
}

export function canPreviewRestore(state: RestoreState): boolean {
    return (
        state.activity === 'idle' &&
        !state.renameDirty &&
        (state.mode !== 'child' || state.childUuid !== null) &&
        (state.mode !== 'children_selected' || selectedRecordUuids(state.rowSelection).length > 0)
    );
}

export function canConfirmRestore(state: RestoreState): boolean {
    return (
        state.activity === 'idle' &&
        !state.renameDirty &&
        !!state.preview?.can_confirm &&
        !!state.preview.confirmation_token &&
        JSON.stringify(state.previewSelection) === JSON.stringify(restoreSelection(state))
    );
}

function invalidate(state: RestoreState): RestoreState {
    return {
        ...state,
        preview: null,
        previewSelection: null,
        confirmationOpen: false,
        activity: 'idle',
        generation: state.generation + 1,
        notice: null,
    };
}

export type RestoreAction =
    | { type: 'mode'; mode: ArchiveRestoreMode }
    | { type: 'target'; target: ArchiveTarget | null }
    | { type: 'child'; uuid: string }
    | { type: 'selection'; update: Updater<RowSelectionState> }
    | { type: 'invalidate' }
    | { type: 'preview-start'; notice?: string }
    | { type: 'preview-loaded'; generation: number; selection: ArchiveRestoreSelection; preview: ArchivePreview }
    | { type: 'confirmation'; open: boolean }
    | { type: 'restore-start' }
    | { type: 'restored'; result: ArchiveRestoreResult }
    | { type: 'rename-start' }
    | { type: 'rename-draft'; dirty: boolean }
    | { type: 'renamed' }
    | { type: 'refreshed' }
    | { type: 'deleted' }
    | { type: 'failed'; message: string };

export function restoreReducer(state: RestoreState, action: RestoreAction): RestoreState {
    switch (action.type) {
        case 'mode':
            return { ...invalidate(state), mode: action.mode, childUuid: null };
        case 'target':
            return { ...invalidate(state), target: action.target };
        case 'child':
            return { ...invalidate(state), mode: 'child', childUuid: action.uuid };
        case 'selection':
            return {
                ...invalidate(state),
                rowSelection: typeof action.update === 'function' ? action.update(state.rowSelection) : action.update,
            };
        case 'invalidate':
            return invalidate(state);
        case 'preview-start':
            return {
                ...state,
                preview: null,
                previewSelection: null,
                confirmationOpen: false,
                activity: 'preview',
                notice: action.notice ?? null,
                result: null,
            };
        case 'preview-loaded':
            if (
                action.generation !== state.generation ||
                JSON.stringify(action.selection) !== JSON.stringify(restoreSelection(state))
            )
                return state;
            return { ...state, preview: action.preview, previewSelection: action.selection, activity: 'idle' };
        case 'confirmation':
            return { ...state, confirmationOpen: action.open && canConfirmRestore(state) };
        case 'restore-start':
            return canConfirmRestore(state) && state.confirmationOpen ? { ...state, activity: 'restore' } : state;
        case 'restored':
            return {
                ...invalidate(state),
                result: action.result,
                rowSelection: {},
                childUuid: null,
                mode:
                    state.mode === 'parent_only' || state.mode === 'parent_with_children' ? 'children_all' : state.mode,
                activity: 'refresh',
            };
        case 'rename-draft':
            return { ...invalidate(state), renameDirty: action.dirty };
        case 'rename-start':
            return { ...invalidate(state), activity: 'rename' };
        case 'renamed':
            return {
                ...state,
                renameDirty: false,
                activity: 'refresh',
                notice: 'Nama grup arsip diperbarui. Buat pratinjau baru sebelum memulihkan.',
            };
        case 'refreshed':
            return { ...state, activity: 'idle' };
        case 'deleted':
            return { ...invalidate(state), rowSelection: {}, childUuid: null, result: null };
        case 'failed':
            return { ...invalidate(state), activity: 'idle', notice: action.message };
    }
}

export function archiveFailure(error: unknown): { changed: boolean; message: string } {
    const response = typeof error === 'object' && error !== null && 'response' in error ? error.response : null;
    if (typeof response === 'object' && response !== null && 'status' in response) {
        if (response.status === 409 && 'data' in response) {
            try {
                const data = typeof response.data === 'string' ? JSON.parse(response.data) : response.data;
                if (data?.code === 'archive_changed')
                    return {
                        changed: true,
                        message:
                            'Data berubah sejak pratinjau terakhir. Tinjau kondisi terbaru lalu konfirmasi kembali.',
                    };
            } catch {
                /* A non-JSON error still needs a fresh preview. */
            }
        }
        if (response.status === 404)
            return {
                changed: false,
                message:
                    'Arsip atau grup tujuan tidak tersedia. Kembali ke daftar arsip untuk melihat kondisi terbaru.',
            };
        if (response.status === 401 || response.status === 419)
            return {
                changed: false,
                message: 'Sesi berakhir. Muat ulang halaman dan masuk kembali sebelum melanjutkan.',
            };
    }
    return {
        changed: false,
        message: 'Operasi belum dapat dipastikan berhasil. Periksa koneksi lalu buat pratinjau baru.',
    };
}
