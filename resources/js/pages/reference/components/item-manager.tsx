import { Accessibility, Feedback, PointerActivationConstraints, PointerSensor } from '@dnd-kit/dom';
import { DragDropProvider, type DragStartEvent, type DragOverEvent, type DragEndEvent } from '@dnd-kit/react';
import { isSortable, useSortable } from '@dnd-kit/react/sortable';
import {
    Add01Icon,
    ArrowDown01Icon,
    ArrowUp01Icon,
    Delete02Icon,
    DragDropVerticalIcon,
    Edit04Icon,
    LoaderIcon,
    SaveIcon,
    XIcon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { router } from '@inertiajs/react';
import { useEffect, useEffectEvent, useRef, useState, type ReactNode, type SubmitEventHandler } from 'react';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { gooeyToast } from '@/components/ui/goey-toaster';
import { Input } from '@/components/ui/input';
import { useValibotForm } from '@/hooks/use-valibot-form';
import ref from '@/routes/ref';
import { referenceItemSchema, type ReferenceGroupDetail, type ReferenceItemResource } from '@/schemas/reference';
import { moveItem } from './move-item';

interface ItemManagerProps {
    group: ReferenceGroupDetail;
    disabled: boolean;
    onProcessingChange: (processing: boolean) => void;
}

function SortableItem({
    item,
    index,
    disabled,
    children,
}: {
    item: ReferenceItemResource;
    index: number;
    disabled: boolean;
    children: ReactNode;
}) {
    const { ref: rowRef, handleRef } = useSortable({ id: item.id, index, disabled, data: { content: item.content } });
    return (
        <li ref={rowRef} className="flex flex-wrap items-center gap-2 rounded-lg border bg-card p-3">
            <Button
                ref={handleRef}
                type="button"
                variant="ghost"
                size="icon-sm"
                disabled={disabled}
                className="cursor-grab touch-none"
                data-focus={`handle-${item.id}`}
                aria-label={`Geser untuk mengurutkan ${item.content}`}
                aria-roledescription="Item yang dapat diurutkan"
            >
                <HugeiconsIcon icon={DragDropVerticalIcon} aria-hidden="true" />
            </Button>
            {children}
        </li>
    );
}

export function ItemManager({ group, disabled, onProcessingChange }: ItemManagerProps) {
    const add = useValibotForm(referenceItemSchema, { content: '' });
    const edit = useValibotForm(referenceItemSchema, { content: '' });
    const [editingId, setEditingId] = useState<string | null>(null);
    const [deleting, setDeleting] = useState<ReferenceItemResource | null>(null);
    const [processing, setProcessing] = useState(false);
    const [dragging, setDragging] = useState(false);
    const [dragRevision, setDragRevision] = useState(0);
    const [focusRevision, setFocusRevision] = useState(0);
    const running = useRef(false);
    const container = useRef<HTMLDivElement>(null);
    const focusTarget = useRef('add-item');
    const focusRequested = useRef(false);
    const busy = disabled || processing;
    const sortingDisabled = busy || editingId !== null || group.items.length < 2;

    const focusAfterRender = useEffectEvent(() => {
        const target = container.current?.querySelector<HTMLButtonElement | HTMLInputElement>(
            `[data-focus="${focusTarget.current}"]:not(:disabled)`,
        );
        const uuid = focusTarget.current.slice(focusTarget.current.indexOf('-') + 1);
        const fallback = container.current?.querySelector<HTMLButtonElement>(
            `[data-focus="handle-${uuid}"]:not(:disabled), [data-focus="edit-${uuid}"]:not(:disabled)`,
        );
        (target ?? fallback ?? container.current?.querySelector<HTMLInputElement>('[data-focus="add-item"]'))?.focus({
            preventScroll: true,
        });
    });

    useEffect(() => {
        if (focusRevision > 0 && focusRequested.current && !busy) {
            focusRequested.current = false;
            focusAfterRender();
        }
    }, [focusRevision, busy]);

    function restoreFocus() {
        focusRequested.current = true;
        setFocusRevision((revision) => revision + 1);
    }

    function begin() {
        running.current = true;
        setProcessing(true);
        onProcessingChange(true);
    }

    function finish() {
        running.current = false;
        setProcessing(false);
        onProcessingChange(false);
        restoreFocus();
    }

    function showError(message = 'Terdapat data yang tidak valid atau item gagal disimpan.') {
        gooeyToast.error('Galat', { description: message });
    }

    function cancelEdit() {
        if (busy) return;
        focusTarget.current = `edit-${editingId}`;
        setEditingId(null);
        edit.form.clearErrors();
        restoreFocus();
    }

    const addItem: SubmitEventHandler<HTMLFormElement> = (event) => {
        event.preventDefault();
        if (busy || running.current || editingId !== null || dragging) return;
        focusTarget.current = 'add-item';
        const submitted = add.submit('post', ref.item.store(group.id).url, {
            preserveScroll: true,
            only: ['resource'],
            showProgress: false,
            errorBag: 'addReferenceItem',
            onStart: begin,
            onFinish: finish,
            onError: () => showError(),
            onNetworkError: () => {
                showError();
                return false;
            },
            onHttpException: () => {
                showError();
                return false;
            },
            onSuccess: () => {
                add.form.reset();
                focusTarget.current = 'add-item';
                gooeyToast.success('Berhasil', { description: 'Item berhasil ditambahkan.' });
            },
        });
        if (!submitted) showError();
    };

    const updateItem: SubmitEventHandler<HTMLFormElement> = (event) => {
        event.preventDefault();
        if (!editingId || busy || running.current) return;
        focusTarget.current = 'edit-item';
        const submitted = edit.submit('patch', ref.item.update({ referenceGroup: group.id, item: editingId }).url, {
            preserveScroll: true,
            only: ['resource'],
            showProgress: false,
            errorBag: 'updateReferenceItem',
            onStart: begin,
            onFinish: finish,
            onError: () => showError(),
            onNetworkError: () => {
                showError();
                return false;
            },
            onHttpException: () => {
                showError();
                return false;
            },
            onSuccess: () => {
                focusTarget.current = `edit-${editingId}`;
                setEditingId(null);
                gooeyToast.success('Berhasil', { description: 'Item berhasil diubah.' });
            },
        });
        if (!submitted) showError();
    };

    function deleteItem() {
        if (!deleting || busy || running.current) return;
        router.delete(ref.item.destroy({ referenceGroup: group.id, item: deleting.id }).url, {
            preserveScroll: true,
            only: ['resource'],
            showProgress: false,
            onStart: begin,
            onFinish: finish,
            onError: () => showError('Item gagal dihapus. Silakan coba lagi.'),
            onNetworkError: () => {
                showError('Item gagal dihapus. Silakan coba lagi.');
                return false;
            },
            onHttpException: () => {
                showError('Item gagal dihapus. Silakan coba lagi.');
                return false;
            },
            onSuccess: () => {
                const position = group.items.findIndex((item) => item.id === deleting.id);
                const next = group.items[position + 1] ?? group.items[position - 1];
                focusTarget.current = next ? `edit-${next.id}` : 'add-item';
                setDeleting(null);
                gooeyToast.success('Berhasil', { description: 'Item berhasil dihapus.' });
            },
        });
    }

    function reorder(from: number, to: number) {
        if (sortingDisabled || running.current || from === to) return;
        const ordered = moveItem(group.items, from, to).map((item, index) => ({ ...item, sort_order: index + 1 }));
        let recover = false;
        const onFailure = (message?: string) => {
            recover = true;
            showError(message ?? 'Urutan gagal disimpan. Data akan dimuat ulang.');
        };
        router
            .optimistic<{ resource: { data: ReferenceGroupDetail } }>((props) => ({
                resource: { ...props.resource, data: { ...props.resource.data, items: ordered } },
            }))
            .patch(
                ref.item.reorder(group.id).url,
                { ids: ordered.map((item) => item.id) },
                {
                    preserveScroll: true,
                    only: ['resource'],
                    showProgress: false,
                    onStart: begin,
                    onError: (errors) => onFailure(errors.ids),
                    onNetworkError: () => {
                        onFailure();
                        return false;
                    },
                    onHttpException: () => {
                        onFailure();
                        return false;
                    },
                    onFinish: () => {
                        if (recover) {
                            router.reload({
                                only: ['resource'],
                                showProgress: false,
                                onFinish: finish,
                                onNetworkError: () => false,
                                onHttpException: () => false,
                            });
                        } else finish();
                    },
                },
            );
    }

    return (
        <Card ref={container} aria-busy={processing}>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    Item Referensi{' '}
                    <span className="rounded-md bg-secondary px-2 py-0.5 text-sm text-secondary-foreground tabular-nums">
                        {group.items_count}
                    </span>
                </CardTitle>
                <CardDescription>Gunakan tombol panah atau geser handle untuk mengatur urutan item.</CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                <form noValidate onSubmit={addItem}>
                    <FieldGroup>
                        <Field
                            data-invalid={!!add.form.errors.content}
                            data-disabled={busy || editingId !== null || dragging}
                        >
                            <FieldLabel htmlFor="add-item">Tambah Item</FieldLabel>
                            <div className="flex items-start gap-2">
                                <Input
                                    {...add.bind('content')}
                                    id="add-item"
                                    data-focus="add-item"
                                    placeholder="Tambah item…"
                                    maxLength={255}
                                    disabled={busy || editingId !== null || dragging}
                                />
                                <Button type="submit" disabled={busy || editingId !== null || dragging}>
                                    <HugeiconsIcon
                                        icon={add.form.processing ? LoaderIcon : Add01Icon}
                                        data-icon="inline-start"
                                    />
                                    Tambah
                                </Button>
                            </div>
                            {add.form.errors.content && <FieldError>{add.form.errors.content}</FieldError>}
                        </Field>
                    </FieldGroup>
                </form>
                {group.items.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Belum ada item referensi. Tambahkan item pertama melalui kolom di atas.
                    </p>
                ) : (
                    <DragDropProvider
                        sensors={(defaults) => [
                            ...defaults.filter((sensor) => sensor !== PointerSensor),
                            PointerSensor.configure({
                                activationConstraints: [new PointerActivationConstraints.Distance({ value: 8 })],
                            }),
                        ]}
                        plugins={(defaults) => [
                            ...defaults.filter((plugin) => plugin !== Accessibility && plugin !== Feedback),
                            Feedback.configure({ feedback: 'clone', dropAnimation: null }),
                            Accessibility.configure({
                                screenReaderInstructions: {
                                    draggable:
                                        'Tekan spasi untuk mulai mengurutkan. Gunakan panah atas atau bawah, spasi untuk menyimpan, dan Escape untuk membatalkan.',
                                },
                                announcements: {
                                    dragstart: ({ operation }: DragStartEvent) =>
                                        `Mulai mengurutkan ${operation.source?.data.content ?? 'item'}.`,
                                    dragover: ({ operation }: DragOverEvent) =>
                                        isSortable(operation.source)
                                            ? `Item berada pada posisi ${operation.source.index + 1}.`
                                            : undefined,
                                    dragend: ({ canceled, operation }: DragEndEvent) =>
                                        canceled
                                            ? 'Pengurutan dibatalkan.'
                                            : isSortable(operation.source)
                                              ? `Item dipindah ke posisi ${operation.source.index + 1}.`
                                              : 'Pengurutan selesai.',
                                },
                            }),
                        ]}
                        onBeforeDragStart={(event) => {
                            if (sortingDisabled) event.preventDefault();
                        }}
                        onDragStart={({ operation }) => {
                            focusTarget.current = `handle-${operation.source?.id}`;
                            setDragging(true);
                        }}
                        onDragEnd={({ canceled, operation }) => {
                            const sortableSource = !canceled && isSortable(operation.source) ? operation.source : null;
                            const from = sortableSource?.initialIndex ?? null;
                            const to = sortableSource?.index ?? null;

                            // Let dnd-kit finish its placeholder ResizeObserver cycle before React replaces the list.
                            window.setTimeout(() => {
                                setDragging(false);
                                setDragRevision((revision) => revision + 1);
                                restoreFocus();
                                if (from !== null && to !== null) reorder(from, to);
                            }, 0);
                        }}
                    >
                        {/* DnD moves DOM nodes directly; rebuild rows when a drag ends or the canonical order changes. */}
                        <ul
                            key={`${dragRevision}:${group.items.map((item) => item.id).join(',')}`}
                            aria-label="Daftar item referensi"
                            className="flex flex-col gap-2"
                        >
                            {group.items.map((item, index) => (
                                <SortableItem key={item.id} item={item} index={index} disabled={sortingDisabled}>
                                    {editingId === item.id ? (
                                        <form
                                            className="min-w-0 flex-1"
                                            noValidate
                                            onSubmit={updateItem}
                                            onKeyDown={(event) => {
                                                if (event.key === 'Escape') {
                                                    event.preventDefault();
                                                    cancelEdit();
                                                }
                                            }}
                                        >
                                            <FieldGroup>
                                                <Field data-invalid={!!edit.form.errors.content} data-disabled={busy}>
                                                    <FieldLabel className="sr-only" htmlFor="edit-item">
                                                        Konten Item
                                                    </FieldLabel>
                                                    <Input
                                                        {...edit.bind('content')}
                                                        id="edit-item"
                                                        data-focus="edit-item"
                                                        maxLength={255}
                                                        disabled={busy}
                                                        autoFocus
                                                    />
                                                    {edit.form.errors.content && (
                                                        <FieldError>{edit.form.errors.content}</FieldError>
                                                    )}
                                                    <div className="flex gap-2">
                                                        <Button type="submit" size="sm" disabled={busy}>
                                                            <HugeiconsIcon icon={SaveIcon} data-icon="inline-start" />
                                                            Simpan
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            disabled={busy}
                                                            onClick={cancelEdit}
                                                        >
                                                            <HugeiconsIcon icon={XIcon} data-icon="inline-start" />
                                                            Batal
                                                        </Button>
                                                    </div>
                                                </Field>
                                            </FieldGroup>
                                        </form>
                                    ) : (
                                        <>
                                            <span className="min-w-0 flex-1 wrap-anywhere">{item.content}</span>
                                            <div className="flex flex-wrap gap-1">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="icon-sm"
                                                    disabled={sortingDisabled || dragging || index === 0}
                                                    data-focus={`up-${item.id}`}
                                                    aria-label={`Pindah ke atas ${item.content}`}
                                                    onClick={() => {
                                                        focusTarget.current = `up-${item.id}`;
                                                        reorder(index, index - 1);
                                                    }}
                                                >
                                                    <HugeiconsIcon icon={ArrowUp01Icon} aria-hidden="true" />
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="icon-sm"
                                                    disabled={
                                                        sortingDisabled || dragging || index === group.items.length - 1
                                                    }
                                                    data-focus={`down-${item.id}`}
                                                    aria-label={`Pindah ke bawah ${item.content}`}
                                                    onClick={() => {
                                                        focusTarget.current = `down-${item.id}`;
                                                        reorder(index, index + 1);
                                                    }}
                                                >
                                                    <HugeiconsIcon icon={ArrowDown01Icon} aria-hidden="true" />
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="warning"
                                                    size="icon-sm"
                                                    disabled={busy || editingId !== null || dragging}
                                                    data-focus={`edit-${item.id}`}
                                                    aria-label={`Ubah ${item.content}`}
                                                    onClick={() => {
                                                        edit.form.setData('content', item.content);
                                                        edit.form.clearErrors();
                                                        setEditingId(item.id);
                                                    }}
                                                >
                                                    <HugeiconsIcon icon={Edit04Icon} aria-hidden="true" />
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="destructive"
                                                    size="icon-sm"
                                                    disabled={busy || editingId !== null || dragging}
                                                    data-focus={`delete-${item.id}`}
                                                    aria-label={`Hapus ${item.content}`}
                                                    onClick={() => {
                                                        focusTarget.current = `delete-${item.id}`;
                                                        setDeleting(item);
                                                    }}
                                                >
                                                    <HugeiconsIcon icon={Delete02Icon} aria-hidden="true" />
                                                </Button>
                                            </div>
                                        </>
                                    )}
                                </SortableItem>
                            ))}
                        </ul>
                    </DragDropProvider>
                )}
                <AlertDialog
                    open={deleting !== null}
                    onOpenChange={(open) => {
                        if (!open && !busy) {
                            setDeleting(null);
                            restoreFocus();
                        }
                    }}
                >
                    <AlertDialogContent>
                        <AlertDialogTitle>Hapus Item Referensi?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Hapus <strong>{deleting?.content}</strong>? Item yang dihapus tidak dapat dikembalikan.
                        </AlertDialogDescription>
                        <AlertDialogFooter>
                            <AlertDialogCancel render={<Button variant="outline" disabled={busy} />}>
                                Batal
                            </AlertDialogCancel>
                            <Button type="button" variant="destructive" disabled={busy} onClick={deleteItem}>
                                <HugeiconsIcon icon={processing ? LoaderIcon : Delete02Icon} data-icon="inline-start" />
                                Hapus
                            </Button>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </CardContent>
        </Card>
    );
}
