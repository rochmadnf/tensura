import { describe, expect, it } from 'vite-plus/test';
import {
    canDeleteArchive,
    deleteReducer,
    deleteSelection,
    deletionDestination,
    deletionFailure,
    initialDeleteState,
    type DeleteState,
} from '@/pages/archive/components/delete-state';
import { initialRestoreState, restoreReducer, restoreSelection } from '@/pages/archive/components/restore-state';
import type { ArchiveDeleteResult, ArchivePreview } from '@/types/archive';

const subject = { uuid: 'archive-uuid', label: 'Grup arsip' };
const preview: ArchivePreview = {
    archive_uuid: subject.uuid,
    action: 'delete_forever',
    mode: 'package',
    parent: null,
    target: null,
    matching_groups: [],
    candidates: [],
    conflicts: [],
    counts: { total: 3, ready: 3, conflicted: 0 },
    can_confirm: true,
    confirmation_token: 'protected-token',
};
function ready(): DeleteState {
    let state = deleteReducer(initialDeleteState, {
        type: 'preview',
        subject,
        selection: { action: 'delete_forever', mode: 'package' },
        generation: 1,
    });
    state = deleteReducer(state, { type: 'review', preview, generation: 1 });
    return state;
}
const result: ArchiveDeleteResult = {
    archive_uuid: subject.uuid,
    mode: 'child',
    counts: { requested: 1, deleted: 1, remaining: 1 },
    archive_exists: true,
};

describe('Delete Forever confirmation', () => {
    it('builds package scope without inheriting previously checked rows', () => {
        expect(deleteSelection('package', ['old-choice'])).toEqual({ action: 'delete_forever', mode: 'package' });
    });
    it('builds a single record scope using the archive record UUID', () => {
        expect(deleteSelection('child', ['archive-record-uuid'])).toEqual({
            action: 'delete_forever',
            mode: 'child',
            record_uuids: ['archive-record-uuid'],
        });
        expect(deleteSelection('child')).toBeNull();
        expect(deleteSelection('child', ['one', 'two'])).toBeNull();
    });
    it('keeps only the selected UUIDs across pages and does not accept an empty selection', () => {
        expect(deleteSelection('children_selected', ['page-two', 'page-one', 'page-two'])).toEqual({
            action: 'delete_forever',
            mode: 'children_selected',
            record_uuids: ['page-one', 'page-two'],
        });
        expect(deleteSelection('children_selected', [])).toBeNull();
    });
    it('requires a loaded preview and explicit confirmation before deleting', () => {
        const state = deleteReducer(initialDeleteState, {
            type: 'preview',
            subject,
            selection: { action: 'delete_forever', mode: 'package' },
            generation: 1,
        });
        expect(canDeleteArchive(state)).toBe(false);
        expect(deleteReducer(state, { type: 'delete' }).phase).toBe('preview');
        const reviewed = deleteReducer(state, { type: 'review', preview, generation: 1 });
        expect(reviewed.phase).toBe('review');
        expect(canDeleteArchive(reviewed)).toBe(true);
        const deleting = deleteReducer(reviewed, { type: 'delete' });
        expect(deleting.phase).toBe('deleting');
        expect(canDeleteArchive(deleting)).toBe(false);
    });
    it.each([
        { confirmation_token: null },
        { can_confirm: false },
        { archive_uuid: 'another-archive' },
        { action: 'restore' as const },
        { mode: 'children_selected' as const },
    ])('blocks confirmation for an invalid proof %j', (changes) => {
        expect(canDeleteArchive({ ...ready(), preview: { ...preview, ...changes } })).toBe(false);
    });
    it('discards a cancelled preview and ignores late responses', () => {
        const state = deleteReducer(ready(), { type: 'close' });
        expect(state.phase).toBe('closed');
        expect(state.preview).toBeNull();
        expect(deleteReducer(state, { type: 'review', preview, generation: 1 })).toEqual(state);
        expect(deleteReducer(state, { type: 'failed', message: 'Old error', generation: 1 })).toEqual(state);
    });
    it('invalidates the previous token when preparing a different selection', () => {
        const state = deleteReducer(ready(), {
            type: 'preview',
            subject,
            selection: { action: 'delete_forever', mode: 'child', record_uuids: ['chosen'] },
            generation: 2,
        });
        expect(state.preview).toBeNull();
        expect(deleteReducer(state, { type: 'review', preview, generation: 1 }).preview).toBeNull();
        expect(canDeleteArchive(state)).toBe(false);
    });
    it('keeps exact scope after archive_changed and requires confirming the new preview', () => {
        const selection = {
            action: 'delete_forever' as const,
            mode: 'children_selected' as const,
            record_uuids: ['off-page', 'current-page'],
        };
        let state: DeleteState = { ...ready(), selection, preview: { ...preview, mode: 'children_selected' as const } };
        const failure = deletionFailure({
            response: { status: 409, data: JSON.stringify({ code: 'archive_changed' }) },
        });
        expect(failure.changed).toBe(true);
        state = deleteReducer(state, { type: 'failed', generation: 1, message: failure.message });
        expect(state.preview).toBeNull();
        expect(state.selection).toEqual(selection);
        state = deleteReducer(state, { type: 'preview', subject, selection, generation: 2, notice: failure.message });
        state = deleteReducer(state, {
            type: 'review',
            preview: { ...preview, mode: 'children_selected', confirmation_token: 'new-proof' },
            generation: 2,
        });
        expect(state.phase).toBe('review');
        expect(state.notice).toContain('konfirmasi ulang');
        expect(state.preview?.confirmation_token).toBe('new-proof');
    });
    it('does not fall back to package deletion when a selected record was already processed', () => {
        const selection = deleteSelection('child', ['already-processed'])!;
        const state = deleteReducer(
            { ...ready(), selection },
            { type: 'failed', generation: 1, message: 'Item sudah diproses.' },
        );
        expect(canDeleteArchive(state)).toBe(false);
        expect(state.selection).toEqual(selection);
        expect(state.preview).toBeNull();
    });
    it('cannot confirm again while refreshing a successful deletion', () => {
        const state = deleteReducer(ready(), { type: 'deleted' });
        expect(state.phase).toBe('refresh');
        expect(state.preview).toBeNull();
        expect(canDeleteArchive(state)).toBe(false);
    });
    it.each([401, 419, 404, 500])('uses deletion-specific feedback for status %i', (status) => {
        const failure = deletionFailure({ response: { status, data: '{}' } });
        expect(failure.changed).toBe(false);
        expect(failure.message).toMatch(/arsip|penghapusan/);
    });
    it('does not automatically retry deletion after network or unrelated conflict errors', () => {
        expect(deletionFailure(new Error('network')).changed).toBe(false);
        expect(deletionFailure({ response: { status: 409, data: '{broken' } }).changed).toBe(false);
        expect(deletionFailure({ response: { status: 409, data: '{}' } }).changed).toBe(false);
        expect(deletionFailure(new Error('network')).message).toContain('belum dapat dipastikan');
    });
    it('clears restore selection, token and outdated result after permanent deletion', () => {
        const original = {
            ...initialRestoreState(false, false),
            rowSelection: { chosen: true as const },
            preview,
            previewSelection: restoreSelection(initialRestoreState(false, false)),
            childUuid: 'chosen',
        };
        const state = restoreReducer(original, { type: 'deleted' });
        expect(state.rowSelection).toEqual({});
        expect(state.preview).toBeNull();
        expect(state.result).toBeNull();
        expect(state.childUuid).toBeNull();
    });
});

describe('Navigation after permanent deletion', () => {
    it('retains the parent-only archive and reloads the first detail page when the final child is deleted', () => {
        const destination = deletionDestination(
            result,
            'detail',
            'reference',
            '?page=5&per_page=5&context=reference&target_keyword=old&target_page=2',
        );
        expect(destination.url).toBe('/archives/archive-uuid');
        expect(destination.query).toEqual({ page: '1', per_page: '5', context: 'reference' });
        expect(destination.only).toEqual(['resource', 'parent_record', 'resources']);
    });
    it.each([null, 'reference'] as const)(
        'returns to the correct listing after an empty package with context %s',
        (context) => {
            const destination = deletionDestination(
                { ...result, archive_exists: false, counts: { requested: 1, deleted: 1, remaining: 0 } },
                'detail',
                context,
                '?page=2',
            );
            expect(destination.url).toBe(context === 'reference' ? '/reference/archive' : '/archives');
            expect(destination.query).toEqual({});
            expect(destination.only).toBeUndefined();
        },
    );
    it('preserves list filters and page size while avoiding an out-of-range page', () => {
        const destination = deletionDestination(
            result,
            'index',
            null,
            '?page=4&keyword=Nama&module=reference&kind=package&per_page=5',
        );
        expect(destination.url).toBe('/archives');
        expect(destination.query).toEqual({
            page: '1',
            keyword: 'Nama',
            module: 'reference',
            kind: 'package',
            per_page: '5',
        });
        expect(destination.only).toEqual(['resources', 'filters']);
    });
});
