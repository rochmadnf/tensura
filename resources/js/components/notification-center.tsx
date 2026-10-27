import { Toggle } from '@base-ui/react/toggle';
import { ToggleGroup } from '@base-ui/react/toggle-group';
import { useEffect, useRef } from 'react';
import { useNotifications } from '@/components/letter-operation-notifications';
import { Alert, AlertDescription, AlertTitle } from '@/components/reui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty';
import { Notification01Icon, type Notification01IconHandle } from '@/components/ui/notification-01';
import { Separator } from '@/components/ui/separator';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import type { NotificationFilter, NotificationItem } from '@/types/notification';

const resultLabels = { succeeded: 'Berhasil', failed: 'Gagal', stale: 'Usang' } as const;
const resultVariants = { succeeded: 'success', failed: 'destructive', stale: 'warning' } as const;
const dateFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
const retryLabels = {
    read: 'Coba tandai dibaca lagi',
    readAll: 'Coba tandai semua dibaca lagi',
    open: 'Coba buka surat lagi',
} as const;

export function NotificationEntry({ item }: { item: NotificationItem }) {
    const { pending, controller, openLetter } = useNotifications();
    return (
        <li className="flex min-w-0 flex-col gap-3 p-4">
            <div className="flex flex-wrap items-center gap-2">
                <Badge variant={resultVariants[item.status]}>{resultLabels[item.status]}</Badge>
                {!item.read_at && <Badge variant="secondary">Belum dibaca</Badge>}
            </div>
            <div className="flex min-w-0 flex-col gap-1">
                <h3 className="text-sm font-medium wrap-anywhere">{item.title}</h3>
                <p className="text-sm wrap-anywhere">{item.subject}</p>
                {item.message && <p className="text-sm wrap-anywhere text-muted-foreground">{item.message}</p>}
                <time className="text-xs text-muted-foreground" dateTime={item.created_at}>
                    {dateFormat.format(new Date(item.created_at))}
                </time>
            </div>
            <div className="flex flex-wrap gap-2">
                <Button
                    variant="outline"
                    className="min-h-11"
                    disabled={pending !== null}
                    onClick={() => void openLetter(item)}
                >
                    {pending === item.id ? 'Menyimpan…' : 'Buka surat'}
                </Button>
                {!item.read_at && (
                    <Button
                        variant="ghost"
                        className="min-h-11"
                        disabled={pending !== null}
                        onClick={() => void controller.markRead(item.id)}
                    >
                        Tandai dibaca
                    </Button>
                )}
            </div>
        </li>
    );
}

export function NotificationPanel() {
    const state = useNotifications();
    const { controller } = state;
    return (
        <>
            <div className="flex flex-col gap-3 px-4">
                <ToggleGroup<NotificationFilter>
                    aria-label="Filter notifikasi"
                    className="flex gap-2"
                    value={[state.filter]}
                    onValueChange={(value) => {
                        if (value[0]) controller.setFilter(value[0]);
                    }}
                >
                    <Toggle
                        value="all"
                        render={
                            <Button variant={state.filter === 'all' ? 'secondary' : 'ghost'} className="min-h-11" />
                        }
                    >
                        Semua
                    </Toggle>
                    <Toggle
                        value="unread"
                        render={
                            <Button variant={state.filter === 'unread' ? 'secondary' : 'ghost'} className="min-h-11" />
                        }
                    >
                        Belum dibaca
                    </Toggle>
                </ToggleGroup>
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="text-sm text-muted-foreground">{state.unreadCount} belum dibaca</p>
                    <Button
                        variant="ghost"
                        className="min-h-11"
                        disabled={state.pending !== null || state.unreadCount === 0}
                        onClick={() => void controller.markRead(null)}
                    >
                        {state.pending === 'all' ? 'Menyimpan…' : 'Tandai semua dibaca'}
                    </Button>
                </div>
                {!state.connected && (
                    <p role="status" className="text-sm text-muted-foreground">
                        Menyambungkan ulang. Status akan diperbarui saat koneksi pulih.
                    </p>
                )}
                {state.soundSupported && (
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <p className="text-sm text-muted-foreground">
                            {state.soundReady ? 'Suara notifikasi aktif' : 'Aktifkan suara untuk notifikasi baru.'}
                        </p>
                        {!state.soundReady && (
                            <Button
                                variant="ghost"
                                className="min-h-11"
                                disabled={state.enablingSound}
                                onClick={() => void state.alerts.enableSound()}
                            >
                                {state.enablingSound ? 'Mengaktifkan…' : 'Aktifkan suara'}
                            </Button>
                        )}
                    </div>
                )}
                {state.mutationError && (
                    <Alert>
                        <AlertTitle>Status dibaca belum tersimpan</AlertTitle>
                        <AlertDescription>
                            <p>Periksa koneksi lalu ulangi tindakan yang gagal.</p>
                            <Button
                                variant="outline"
                                className="min-h-11"
                                disabled={state.pending !== null}
                                onClick={() => void controller.retryMutation()}
                            >
                                {retryLabels[state.failedAction ?? 'read']}
                            </Button>
                        </AlertDescription>
                    </Alert>
                )}
                {(state.summaryError || state.listError) && (
                    <Alert>
                        <AlertTitle>Notifikasi belum dapat diperbarui</AlertTitle>
                        <AlertDescription>
                            <p>Periksa koneksi lalu coba lagi.</p>
                            <Button
                                variant="outline"
                                className="min-h-11"
                                disabled={state.pending !== null}
                                onClick={controller.refresh}
                            >
                                Coba lagi
                            </Button>
                        </AlertDescription>
                    </Alert>
                )}
            </div>
            <Separator />
            <div
                className="min-h-0 flex-1 overflow-y-auto overscroll-contain pb-4"
                aria-busy={state.loading || state.loadingMore}
            >
                {state.loading && (
                    <p role="status" className="px-4 text-sm text-muted-foreground">
                        Memuat notifikasi…
                    </p>
                )}
                {state.loading && state.items.length === 0 && (
                    <div className="flex flex-col gap-6 p-4" aria-hidden="true">
                        {[0, 1, 2].map((row) => (
                            <div key={row} className="flex flex-col gap-3">
                                <Skeleton className="h-5 w-28" />
                                <Skeleton className="h-4 w-3/4" />
                                <Skeleton className="h-4 w-full" />
                                <Skeleton className="h-9 w-24" />
                            </div>
                        ))}
                    </div>
                )}
                {!state.loading && !state.listError && state.items.length === 0 && (
                    <Empty>
                        <EmptyHeader>
                            <EmptyTitle>
                                {state.filter === 'unread' ? 'Semua notifikasi sudah dibaca' : 'Belum ada notifikasi'}
                            </EmptyTitle>
                            <EmptyDescription>
                                Hasil penerbitan dan PDF surat, serta proses yang gagal atau usang, akan muncul di sini.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                )}
                <ul aria-label="Daftar notifikasi" className="divide-y divide-border">
                    {state.items.map((item) => (
                        <NotificationEntry key={item.id} item={item} />
                    ))}
                </ul>
                {state.pageError && (
                    <p role="status" className="px-4 text-sm text-muted-foreground">
                        Notifikasi berikutnya gagal dimuat. Silakan coba lagi.
                    </p>
                )}
                {state.nextCursor && (
                    <div className="flex justify-center p-4">
                        <Button
                            variant="outline"
                            className="min-h-11"
                            disabled={state.loading || state.loadingMore || state.pending !== null}
                            onClick={controller.loadMore}
                        >
                            {state.loadingMore ? 'Memuat…' : state.pageError ? 'Coba lagi' : 'Muat lebih banyak'}
                        </Button>
                    </div>
                )}
            </div>
        </>
    );
}

export function NotificationCenter() {
    const { enabled, open, unreadCount, controller, arrivalRevision } = useNotifications();
    const icon = useRef<Notification01IconHandle>(null);
    useEffect(() => {
        if (arrivalRevision > 0) icon.current?.startAnimation();
    }, [arrivalRevision]);
    if (!enabled) return null;
    return (
        <Sheet open={open} onOpenChange={controller.setOpen}>
            <SheetTrigger
                render={<Button variant="ghost" className="relative min-h-11 min-w-11" />}
                aria-label={unreadCount ? `Notifikasi, ${unreadCount} belum dibaca` : 'Notifikasi'}
            >
                <Notification01Icon ref={icon} size={20} className="inline-flex" aria-hidden="true" />
                {unreadCount > 0 && (
                    <Badge className="absolute -top-1 -right-1" aria-hidden="true">
                        {unreadCount > 99 ? '99+' : unreadCount}
                    </Badge>
                )}
            </SheetTrigger>
            <SheetContent className="data-[side=right]:w-full data-[side=right]:sm:max-w-md">
                <SheetHeader className="pr-12">
                    <SheetTitle>Notifikasi</SheetTitle>
                    <SheetDescription>Hasil proses surat untuk akun Anda.</SheetDescription>
                </SheetHeader>
                <NotificationPanel />
            </SheetContent>
        </Sheet>
    );
}
