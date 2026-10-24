import { describe, expect, it } from 'vite-plus/test';
import {
    archiveFailure,
    canConfirmRestore,
    canPreviewRestore,
    initialRestoreState,
    restoreReducer,
    restoreSelection,
    selectedRecordUuids,
    type RestoreState,
    type RestoreAction,
} from '@/pages/archive/components/restore-state';
import type { ArchivePreview, ArchiveRestoreMode, ArchiveRestoreResult } from '@/types/archive';

const preview: ArchivePreview = {
    archive_uuid: 'archive',
    action: 'restore',
    mode: 'parent_with_children',
    parent: null,
    target: null,
    matching_groups: [],
    candidates: [],
    conflicts: [],
    counts: { total: 2, ready: 1, conflicted: 1 },
    can_confirm: true,
    confirmation_token: 'protected-token',
};

function previewed(state = initialRestoreState(true, false)): RestoreState {
    state = restoreReducer(state, { type: 'preview-start' });
    return restoreReducer(state, {
        type: 'preview-loaded',
        generation: state.generation,
        selection: restoreSelection(state),
        preview,
    });
}

describe('Archive restore selection and confirmation', () => {
    it.each<ArchiveRestoreMode>(['parent_only', 'parent_with_children', 'children_all'])(
        'sends no hidden selected records for %s',
        (mode) => {
            const state: RestoreState = {
                ...initialRestoreState(true, false),
                mode,
                rowSelection: { 'unrelated-choice': true },
            };
            expect(restoreSelection(state)).toEqual({ action: 'restore', mode });
        },
    );

    it('keeps choices from previous pages and omits deselected UUIDs', () => {
        let state = initialRestoreState(false, false);
        state = restoreReducer(state, { type: 'mode', mode: 'children_selected' });
        state = restoreReducer(state, { type: 'selection', update: { 'record-a': true } });
        state = restoreReducer(state, {
            type: 'selection',
            update: (previous) => ({ ...previous, 'record-c': true, 'record-b': true }),
        });
        state = restoreReducer(state, {
            type: 'selection',
            update: (previous) => {
                const next = { ...previous };
                delete next['record-b'];
                return next;
            },
        });
        expect(restoreSelection(state)).toEqual({
            action: 'restore',
            mode: 'children_selected',
            record_uuids: ['record-a', 'record-c'],
        });
        expect(selectedRecordUuids(state.rowSelection)).toHaveLength(2);
        expect(canPreviewRestore(state)).toBe(true);
    });

    it('cannot preview an empty selection or an unchosen single child', () => {
        let state = initialRestoreState(false, true);
        expect(canPreviewRestore(state)).toBe(false);
        state = restoreReducer(state, { type: 'mode', mode: 'children_selected' });
        expect(canPreviewRestore(state)).toBe(false);
        state = restoreReducer(state, { type: 'child', uuid: 'archive-record-uuid' });
        expect(restoreSelection(state)).toEqual({
            action: 'restore',
            mode: 'child',
            record_uuids: ['archive-record-uuid'],
        });
        expect(canPreviewRestore(state)).toBe(true);
    });

    it.each(['mode', 'target', 'selection', 'child'] as const)(
        'discards the token and closes confirmation when %s changes',
        (change) => {
            let state = previewed();
            state = restoreReducer(state, { type: 'confirmation', open: true });
            expect(state.confirmationOpen).toBe(true);
            const action: RestoreAction =
                change === 'mode'
                    ? { type: 'mode' as const, mode: 'parent_only' as const }
                    : change === 'target'
                      ? { type: 'target' as const, target: { uuid: 'target-uuid', name: 'Tujuan' } }
                      : change === 'selection'
                        ? { type: 'selection' as const, update: { 'record-uuid': true } }
                        : { type: 'child' as const, uuid: 'record-uuid' };
            state = restoreReducer(state, action);
            expect(state.preview).toBeNull();
            expect(state.confirmationOpen).toBe(false);
            expect(canConfirmRestore(state)).toBe(false);
        },
    );

    it('requires a fresh preview even after a target is changed back to the previous choice', () => {
        const original = previewed();
        let state = restoreReducer(original, { type: 'target', target: { uuid: 'another', name: 'Lain' } });
        state = restoreReducer(state, { type: 'target', target: null });
        state = restoreReducer(state, {
            type: 'preview-loaded',
            generation: original.generation,
            selection: restoreSelection(original),
            preview,
        });
        expect(state.preview).toBeNull();
        expect(canConfirmRestore(state)).toBe(false);
    });

    it('does not accept a response for a different record selection even at the same generation', () => {
        const state = { ...initialRestoreState(false, true), childUuid: 'current-record' };
        const result = restoreReducer(state, {
            type: 'preview-loaded',
            generation: state.generation,
            selection: { action: 'restore', mode: 'child', record_uuids: ['old-record'] },
            preview,
        });
        expect(result.preview).toBeNull();
    });

    it('cannot open confirmation when every candidate conflicts or there is no token', () => {
        for (const changes of [{ can_confirm: false }, { confirmation_token: null }]) {
            const state = { ...previewed(), preview: { ...preview, ...changes } };
            expect(restoreReducer(state, { type: 'confirmation', open: true }).confirmationOpen).toBe(false);
        }
    });

    it('needs an explicit confirmation after a preview and blocks repeated submission while restoring', () => {
        let state = previewed();
        expect(restoreReducer(state, { type: 'restore-start' }).activity).toBe('idle');
        state = restoreReducer(state, { type: 'confirmation', open: true });
        state = restoreReducer(state, { type: 'restore-start' });
        expect(state.activity).toBe('restore');
        expect(canConfirmRestore(state)).toBe(false);
        expect(canPreviewRestore(state)).toBe(false);
    });

    it('clears selection and retains partial results and conflict reasons after success', () => {
        let state = previewed({
            ...initialRestoreState(true, false),
            rowSelection: { 'record-a': true, 'record-b': true },
        });
        const result: ArchiveRestoreResult = {
            archive_uuid: 'archive',
            mode: 'parent_with_children',
            target: { uuid: 'target', name: 'Grup aktif' },
            counts: { requested: 2, restored: 1, conflicted: 1, remaining: 1 },
            archive_exists: true,
            conflicts: [
                {
                    record_uuid: 'record-b',
                    entity: 'reference_item',
                    label: 'Bentrok',
                    operation: 'restore',
                    status: 'conflict',
                    conflicts: [{ code: 'content_conflict', message: 'Konten sudah tersedia.' }],
                },
            ],
        };
        state = restoreReducer(state, { type: 'restored', result });
        expect(state.rowSelection).toEqual({});
        expect(state.preview).toBeNull();
        expect(state.result).toEqual(result);
        expect(state.mode).toBe('children_all');
        expect(state.activity).toBe('refresh');
        state = restoreReducer(state, { type: 'refreshed' });
        expect(state.result?.conflicts[0].label).toBe('Bentrok');
        expect(state.activity).toBe('idle');
    });

    it('blocks preview while a rename is unsaved and invalidates the previous token after saving', () => {
        let state = restoreReducer(previewed(), { type: 'rename-draft', dirty: true });
        expect(canPreviewRestore(state)).toBe(false);
        expect(canConfirmRestore(state)).toBe(false);
        state = restoreReducer(state, { type: 'rename-start' });
        state = restoreReducer(state, { type: 'renamed' });
        state = restoreReducer(state, { type: 'refreshed' });
        expect(state.preview).toBeNull();
        expect(canPreviewRestore(state)).toBe(true);
        expect(state.notice).toContain('pratinjau baru');
    });

    it('returns to idle after cancelling a rename draft without restoring the old preview', () => {
        let state = restoreReducer(previewed(), { type: 'rename-draft', dirty: true });
        state = restoreReducer(state, { type: 'rename-draft', dirty: false });
        expect(state.preview).toBeNull();
        expect(canPreviewRestore(state)).toBe(true);
    });

    it('recognizes archive_changed and requires reviewing and confirming the refreshed preview', () => {
        const failure = archiveFailure({
            response: { status: 409, data: JSON.stringify({ code: 'archive_changed' }) },
        });
        expect(failure.changed).toBe(true);
        let state = restoreReducer(previewed(), { type: 'failed', message: failure.message });
        expect(state.preview).toBeNull();
        state = restoreReducer(state, { type: 'preview-start', notice: failure.message });
        state = restoreReducer(state, {
            type: 'preview-loaded',
            generation: state.generation,
            selection: restoreSelection(state),
            preview: { ...preview, confirmation_token: 'new-token' },
        });
        expect(state.notice).toContain('Data berubah');
        expect(state.confirmationOpen).toBe(false);
        expect(state.preview?.confirmation_token).toBe('new-token');
        expect(restoreReducer(state, { type: 'restore-start' }).activity).toBe('idle');
    });

    it.each([404, 401, 419, 500])(
        'discards a failed confirmation and retains the selection for status %i',
        (status) => {
            const failure = archiveFailure({ response: { status, data: '<html>error</html>' } });
            const state = restoreReducer(
                previewed({ ...initialRestoreState(false, false), rowSelection: { chosen: true } }),
                { type: 'failed', message: failure.message },
            );
            expect(state.preview).toBeNull();
            expect(state.rowSelection).toEqual({ chosen: true });
            expect(state.notice).toBeTruthy();
            expect(failure.changed).toBe(false);
        },
    );

    it('does not treat unrelated or malformed 409 responses as archive_changed', () => {
        expect(archiveFailure({ response: { status: 409, data: '{}' } }).changed).toBe(false);
        expect(archiveFailure({ response: { status: 409, data: 'broken-json' } }).changed).toBe(false);
        expect(archiveFailure(new Error('Network error')).message).toContain('koneksi');
    });
});
