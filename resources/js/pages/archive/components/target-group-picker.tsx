import { router } from '@inertiajs/react';
import { useState, type SubmitEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { buildListingQuery } from '@/lib/listing-query';
import { show } from '@/routes/archives';
import type { ArchivePage, ArchiveTarget, ArchiveTargetGroup } from '@/types/archive';
import { Alert, AlertDescription } from '@/components/reui/alert';

export function TargetGroupPicker({
    archiveUuid,
    groups,
    disabled,
    onChoose,
}: {
    archiveUuid: string;
    groups?: ArchivePage<ArchiveTargetGroup>;
    disabled: boolean;
    onChoose: (target: ArchiveTarget) => void;
}) {
    const [open, setOpen] = useState(false);
    const [keyword, setKeyword] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    function load(page = 1) {
        setLoading(true);
        setError(null);
        router.get(
            show(archiveUuid).url,
            buildListingQuery(window.location.search, { target_keyword: keyword.trim() || null, target_page: page }),
            {
                only: ['target_groups'],
                preserveState: true,
                preserveScroll: true,
                showProgress: false,
                replace: true,
                onError: () => setError('Pencarian grup belum berhasil. Periksa kata kunci dan coba kembali.'),
                onNetworkError: () => {
                    setError('Grup belum dapat dimuat. Periksa koneksi lalu coba kembali.');
                    return false;
                },
                onFinish: () => setLoading(false),
            },
        );
    }
    function search(event: SubmitEvent<HTMLFormElement>) {
        event.preventDefault();
        load();
    }
    return (
        <>
            <Button
                variant="outline"
                disabled={disabled}
                onClick={() => {
                    setOpen(true);
                    load();
                }}
            >
                Pilih grup tujuan
            </Button>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-h-[85svh] overflow-y-auto sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>Pilih grup aktif</DialogTitle>
                        <DialogDescription>
                            Pilih tujuan, lalu tinjau pratinjau penggabungan sebelum mengonfirmasi.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={search}>
                        <FieldGroup className="flex-row items-end gap-3">
                            <Field>
                                <FieldLabel htmlFor="target-search">Cari nama atau deskripsi grup</FieldLabel>
                                <Input
                                    id="target-search"
                                    value={keyword}
                                    onChange={(event) => setKeyword(event.target.value)}
                                    maxLength={255}
                                    disabled={loading}
                                />
                            </Field>
                            <Button type="submit" disabled={loading}>
                                Cari
                            </Button>
                        </FieldGroup>
                    </form>
                    {error && (
                        <Alert variant="destructive">
                            <AlertDescription>{error}</AlertDescription>
                        </Alert>
                    )}
                    <div className="flex flex-col gap-3" aria-busy={loading}>
                        {loading ? (
                            <Skeleton className="h-24 w-full" />
                        ) : groups?.data.length ? (
                            groups.data.map((group) => (
                                <div
                                    key={group.id}
                                    className="flex items-center justify-between gap-4 rounded-lg border p-3"
                                >
                                    <div className="flex min-w-0 flex-col gap-1">
                                        <p className="font-medium break-words">{group.name}</p>
                                        <p className="text-xs whitespace-pre-wrap text-muted-foreground">
                                            {group.description ?? 'Tidak ada deskripsi.'}
                                        </p>
                                        <p className="text-xs text-muted-foreground">{group.items_count} item aktif</p>
                                    </div>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => {
                                            onChoose({ uuid: group.id, name: group.name });
                                            setOpen(false);
                                        }}
                                    >
                                        Pilih<span className="sr-only"> {group.name}</span>
                                    </Button>
                                </div>
                            ))
                        ) : (
                            <p className="text-sm text-muted-foreground">Tidak ada grup aktif yang sesuai.</p>
                        )}
                    </div>
                    {groups && (
                        <nav aria-label="Halaman grup tujuan" className="flex items-center justify-between gap-3">
                            <Button
                                variant="outline"
                                disabled={loading || groups.meta.current_page <= 1}
                                onClick={() => load(groups.meta.current_page - 1)}
                            >
                                Sebelumnya
                            </Button>
                            <span className="text-xs text-muted-foreground tabular-nums">
                                {groups.meta.current_page} / {groups.meta.last_page}
                            </span>
                            <Button
                                variant="outline"
                                disabled={loading || groups.meta.current_page >= groups.meta.last_page}
                                onClick={() => load(groups.meta.current_page + 1)}
                            >
                                Berikutnya
                            </Button>
                        </nav>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
