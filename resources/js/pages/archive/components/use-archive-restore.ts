import { router, useHttp } from '@inertiajs/react';
import { useEffect, useReducer, useRef, type RefObject } from 'react';
import type { RowSelectionState, Updater } from '@tanstack/react-table';
import { gooeyToast } from '@/components/ui/goey-toaster';
import { index, preview, restore, show } from '@/routes/archives';
import { update } from '@/routes/archives/reference-group';
import { index as referenceArchiveIndex } from '@/routes/ref/archive';
import type {
    ArchivedGroupRenameResult,
    ArchiveContext,
    ArchivePreview,
    ArchiveRestoreResult,
    ArchiveRestoreSelection,
    ArchiveResource,
} from '@/types/archive';
import {
    archiveFailure,
    canConfirmRestore,
    canPreviewRestore,
    initialRestoreState,
    restoreReducer,
    restoreSelection,
} from './restore-state';

export function useArchiveRestore(
    archive: ArchiveResource,
    hasParent: boolean,
    context: ArchiveContext,
    operationLock: RefObject<boolean>,
) {
    const [state, dispatch] = useReducer(restoreReducer, undefined, () =>
        initialRestoreState(hasParent, archive.kind === 'record'),
    );
    const previewHttp = useHttp<ArchiveRestoreSelection, { data: ArchivePreview }>({
        action: 'restore',
        mode: 'parent_only',
    });
    const restoreHttp = useHttp<
        ArchiveRestoreSelection & { confirmation_token: string },
        { data: ArchiveRestoreResult }
    >({ action: 'restore', mode: 'parent_only', confirmation_token: '' });
    const renameHttp = useHttp<{ name: string }, { data: ArchivedGroupRenameResult }>({ name: '' });
    const locked = operationLock;
    const consumedToken = useRef<string | null>(null);
    const mounted = useRef(true);
    const { cancel: cancelPreview } = previewHttp;
    const { cancel: cancelRestore } = restoreHttp;
    const { cancel: cancelRename } = renameHttp;
    useEffect(() => {
        mounted.current = true;
        return () => {
            mounted.current = false;
            cancelPreview();
            cancelRestore();
            cancelRename();
        };
    }, [cancelPreview, cancelRestore, cancelRename]);

    function validationFailure(errors: Record<string, string>) {
        dispatch({ type: 'failed', message: Object.values(errors).join(' ') || 'Periksa kembali pilihan pemulihan.' });
    }

    async function loadPreview(selection: ArchiveRestoreSelection, generation: number, notice?: string) {
        dispatch({ type: 'preview-start', notice });
        previewHttp.transform(() => selection);
        const response = await previewHttp.post(preview(archive.uuid).url, { onError: validationFailure });
        if (response && mounted.current)
            dispatch({ type: 'preview-loaded', selection, generation, preview: response.data });
    }

    async function requestPreview() {
        if (locked.current || !canPreviewRestore(state)) return;
        locked.current = true;
        try {
            await loadPreview(restoreSelection(state), state.generation);
        } catch (error) {
            if (mounted.current) dispatch({ type: 'failed', message: archiveFailure(error).message });
        } finally {
            locked.current = false;
        }
    }

    function refreshDetail() {
        router.get(
            show(archive.uuid).url,
            { context, per_page: new URLSearchParams(window.location.search).get('per_page') ?? 10 },
            {
                preserveState: true,
                preserveScroll: true,
                only: ['resource', 'parent_record', 'resources'],
                onFinish: () => {
                    locked.current = false;
                    if (mounted.current) dispatch({ type: 'refreshed' });
                },
            },
        );
    }

    async function confirm() {
        if (locked.current || !state.confirmationOpen || !canConfirmRestore(state)) return;
        const token = state.preview!.confirmation_token!;
        if (consumedToken.current === token) return;
        consumedToken.current = token;
        locked.current = true;
        dispatch({ type: 'restore-start' });
        const selection = state.previewSelection!;
        restoreHttp.transform(() => ({ ...selection, confirmation_token: token }));
        let refreshing = false;
        try {
            const response = await restoreHttp.post(restore(archive.uuid).url, { onError: validationFailure });
            if (!response || !mounted.current) return;
            dispatch({ type: 'restored', result: response.data });
            const { counts } = response.data;
            gooeyToast.success('Pemulihan selesai', {
                description: `${counts.restored} data dipulihkan; ${counts.remaining} data masih diarsipkan.`,
            });
            refreshing = true;
            if (response.data.archive_exists) refreshDetail();
            else
                router.visit(context === 'reference' ? referenceArchiveIndex() : index(), {
                    onFinish: () => {
                        locked.current = false;
                        if (mounted.current) dispatch({ type: 'refreshed' });
                    },
                });
        } catch (error) {
            if (!mounted.current) return;
            const failure = archiveFailure(error);
            dispatch({ type: 'failed', message: failure.message });
            if (failure.changed) {
                router.reload({ only: ['resource', 'parent_record', 'resources'] });
                // Only read a new preview. A second restore always requires another explicit confirmation.
                try {
                    await loadPreview(selection, state.generation + 1, failure.message);
                } catch (previewError) {
                    if (mounted.current) dispatch({ type: 'failed', message: archiveFailure(previewError).message });
                }
            }
        } finally {
            if (!refreshing) locked.current = false;
        }
    }

    async function renameGroup(name: string) {
        if (locked.current) return;
        locked.current = true;
        dispatch({ type: 'rename-start' });
        renameHttp.transform(() => ({ name }));
        let refreshing = false;
        try {
            const response = await renameHttp.patch(update(archive.uuid).url, { onError: validationFailure });
            if (!response || !mounted.current) return;
            dispatch({ type: 'renamed' });
            refreshing = true;
            refreshDetail();
        } catch (error) {
            if (mounted.current) dispatch({ type: 'failed', message: archiveFailure(error).message });
        } finally {
            if (!refreshing) locked.current = false;
        }
    }

    function setRowSelection(update: Updater<RowSelectionState>) {
        if (!locked.current) dispatch({ type: 'selection', update });
    }
    function chooseChild(uuid: string) {
        if (locked.current) return;
        dispatch({ type: 'child', uuid });
        document.getElementById('restore')?.focus();
        document.getElementById('restore')?.scrollIntoView({ behavior: 'instant', block: 'start' });
    }
    return {
        state,
        dispatch,
        busy: state.activity !== 'idle',
        requestPreview,
        confirm,
        renameGroup,
        setRowSelection,
        chooseChild,
        renameErrors: renameHttp.errors,
    };
}

export type ArchiveRestoreFlow = ReturnType<typeof useArchiveRestore>;
