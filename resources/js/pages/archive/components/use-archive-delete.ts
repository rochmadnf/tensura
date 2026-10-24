import { router, useHttp } from '@inertiajs/react';
import { useEffect, useReducer, useRef, type RefObject } from 'react';
import { gooeyToast } from '@/components/ui/goey-toaster';
import { destroy, preview } from '@/routes/archives';
import type {
    ArchiveContext,
    ArchiveDeleteMode,
    ArchiveDeleteResult,
    ArchiveDeleteSelection,
    ArchivePreview,
} from '@/types/archive';
import {
    canDeleteArchive,
    deleteReducer,
    deleteSelection,
    deletionDestination,
    deletionFailure,
    initialDeleteState,
    type DeleteSubject,
} from './delete-state';

export function useArchiveDelete({
    context,
    location,
    operationLock,
    onDeleted,
}: {
    context: ArchiveContext;
    location: 'index' | 'detail';
    operationLock: RefObject<boolean>;
    onDeleted?: (result: ArchiveDeleteResult) => void;
}) {
    const [state, dispatch] = useReducer(deleteReducer, initialDeleteState);
    const previewHttp = useHttp<ArchiveDeleteSelection, { data: ArchivePreview }>({
        action: 'delete_forever',
        mode: 'package',
    });
    const deleteHttp = useHttp<ArchiveDeleteSelection & { confirmation_token: string }, { data: ArchiveDeleteResult }>({
        action: 'delete_forever',
        mode: 'package',
        confirmation_token: '',
    });
    const generation = useRef(0);
    const ownsLock = useRef(false);
    const submitting = useRef(false);
    const consumedToken = useRef<string | null>(null);
    const mounted = useRef(true);
    const { cancel: cancelPreview } = previewHttp;
    const { cancel: cancelDelete } = deleteHttp;
    useEffect(() => {
        mounted.current = true;
        return () => {
            mounted.current = false;
            generation.current++;
            cancelPreview();
            cancelDelete();
            if (ownsLock.current) {
                operationLock.current = false;
                ownsLock.current = false;
            }
        };
    }, [cancelPreview, cancelDelete, operationLock]);

    function valid(requestGeneration: number) {
        return mounted.current && generation.current === requestGeneration;
    }
    function release() {
        if (ownsLock.current) {
            operationLock.current = false;
            ownsLock.current = false;
        }
    }
    function close() {
        if (submitting.current || state.phase === 'refresh') return;
        generation.current++;
        cancelPreview();
        dispatch({ type: 'close' });
        release();
    }
    async function loadPreview(subject: DeleteSubject, selection: ArchiveDeleteSelection, notice?: string) {
        const requestGeneration = ++generation.current;
        dispatch({ type: 'preview', subject, selection, generation: requestGeneration, notice });
        previewHttp.transform(() => selection);
        try {
            const response = await previewHttp.post(preview(subject.uuid).url, {
                onError: (errors) => {
                    if (valid(requestGeneration))
                        dispatch({
                            type: 'failed',
                            generation: requestGeneration,
                            message: [notice, ...Object.values(errors)].filter(Boolean).join(' '),
                        });
                },
            });
            if (response && valid(requestGeneration))
                dispatch({ type: 'review', preview: response.data, generation: requestGeneration });
        } catch (error) {
            if (valid(requestGeneration))
                dispatch({ type: 'failed', generation: requestGeneration, message: deletionFailure(error).message });
        }
    }
    function begin(subject: DeleteSubject, mode: ArchiveDeleteMode, uuids: string[] = []) {
        if (operationLock.current) return;
        const selection = deleteSelection(mode, uuids);
        if (!selection) return;
        operationLock.current = true;
        ownsLock.current = true;
        void loadPreview(subject, selection);
    }
    function retryPreview() {
        if (state.subject && state.selection && state.phase === 'review' && !state.preview && !submitting.current)
            void loadPreview(state.subject, state.selection);
    }
    async function confirm() {
        if (submitting.current || !ownsLock.current || !canDeleteArchive(state) || !state.subject || !state.selection)
            return;
        const token = state.preview!.confirmation_token!;
        if (consumedToken.current === token) return;
        consumedToken.current = token;
        submitting.current = true;
        dispatch({ type: 'delete' });
        const requestGeneration = generation.current;
        const subject = state.subject;
        const selection = state.selection;
        deleteHttp.transform(() => ({ ...selection, confirmation_token: token }));
        try {
            const response = await deleteHttp.delete(destroy(subject.uuid).url, {
                onError: (errors) => {
                    if (valid(requestGeneration))
                        dispatch({
                            type: 'failed',
                            generation: requestGeneration,
                            message: Object.values(errors).join(' ') || 'Siapkan kembali konfirmasi penghapusan.',
                        });
                },
            });
            if (!response || !valid(requestGeneration)) return;
            dispatch({ type: 'deleted' });
            onDeleted?.(response.data);
            gooeyToast.success('Penghapusan permanen selesai', {
                description: `${response.data.counts.deleted} data arsip dihapus; ${response.data.counts.remaining} data masih diarsipkan.`,
            });
            const destination = deletionDestination(response.data, location, context, window.location.search);
            router.get(destination.url, destination.query, {
                only: destination.only,
                preserveState: true,
                preserveScroll: true,
                onNetworkError: () => {
                    gooeyToast.warning('Daftar belum diperbarui', {
                        description: 'Penghapusan sudah selesai. Muat ulang halaman untuk melihat data terbaru.',
                    });
                    return false;
                },
                onFinish: () => {
                    release();
                    if (mounted.current) dispatch({ type: 'close' });
                },
            });
        } catch (error) {
            if (!valid(requestGeneration)) return;
            const failure = deletionFailure(error);
            dispatch({ type: 'failed', generation: requestGeneration, message: failure.message });
            if (failure.changed) {
                router.reload({
                    only: location === 'detail' ? ['resource', 'parent_record', 'resources'] : ['resources', 'filters'],
                });
                await loadPreview(subject, selection, failure.message);
            }
        } finally {
            submitting.current = false;
        }
    }
    return { state, begin, close, confirm, retryPreview, busy: state.phase !== 'closed' };
}
export type ArchiveDeleteFlow = ReturnType<typeof useArchiveDelete>;
