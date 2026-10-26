import { useEffect, useState } from 'react';
import { useForm } from '@inertiajs/react';
import { RefreshIcon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { FieldDescription, FieldError } from '@/components/ui/field';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { gooeyToast } from '@/components/ui/goey-toaster';
import type { LetterChangePreview } from '@/schemas/letter';
import { preview as masterPreview, apply as applyMaster } from '@/routes/ltr/masters';
import { preview as templatePreview, apply as applyTemplate } from '@/routes/ltr/adjustment';

export function LetterChangeAction({
    letterId,
    revision,
    kind,
    disabled,
    onOpenChange,
}: {
    letterId: string;
    revision: number;
    kind: 'masters' | 'template';
    disabled: boolean;
    onOpenChange: (value: boolean) => void;
}) {
    const [open, setOpen] = useState(false);
    const [result, setResult] = useState<LetterChangePreview | null>(null);
    const [error, setError] = useState('');
    const [attempt, setAttempt] = useState(0);
    const form = useForm({ revision, change_token: '', confirmed: true });
    const label = kind === 'template' ? 'Sesuaikan ke versi terbaru' : 'Ambil data master terbaru';
    const changeOpen = (value: boolean) => {
        if (form.processing) return;
        setOpen(value);
        onOpenChange(value);
        form.clearErrors();
    };
    useEffect(() => {
        if (!open) return;
        const abort = new AbortController();
        setResult(null);
        setError('');
        const url = (kind === 'template' ? templatePreview : masterPreview)(letterId, { query: { revision } }).url;
        fetch(url, { signal: abort.signal, credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(async (response) => {
                if (response.redirected || !response.headers.get('Content-Type')?.includes('application/json'))
                    throw new Error('Ringkasan belum dapat dimuat. Muat ulang halaman dan periksa sesi login.');
                const data = await response.json();
                if (!response.ok || response.redirected)
                    throw new Error(
                        Object.values(data.errors ?? {})
                            .flat()
                            .join(' ') || 'Ringkasan belum dapat dimuat.',
                    );
                return data as LetterChangePreview;
            })
            .then((data) => {
                if (!abort.signal.aborted) setResult(data);
            })
            .catch((reason) => {
                if (!abort.signal.aborted)
                    setError(reason instanceof Error ? reason.message : 'Ringkasan belum dapat dimuat.');
            });
        return () => abort.abort();
    }, [open, letterId, revision, kind, attempt]);
    function apply() {
        if (!result || form.processing) return;
        form.transform(() => ({ revision: result.revision, change_token: result.change_token, confirmed: true }));
        form.post((kind === 'template' ? applyTemplate : applyMaster)(letterId).url, {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                onOpenChange(false);
                gooeyToast.success('Berhasil', {
                    description: 'Draft diperbarui. Periksa kembali isian dan snapshot.',
                });
            },
            onError: () =>
                gooeyToast.error('Galat', { description: 'Perubahan belum diterapkan. Periksa pesan pada ringkasan.' }),
        });
    }
    return (
        <>
            <Button type="button" variant="outline" disabled={disabled} onClick={() => changeOpen(true)}>
                <HugeiconsIcon icon={RefreshIcon} data-icon="inline-start" aria-hidden="true" />
                {label}
            </Button>
            <Dialog open={open} onOpenChange={changeOpen}>
                <DialogContent className="flex max-h-[90dvh] flex-col sm:max-w-3xl" showCloseButton={!form.processing}>
                    <DialogHeader>
                        <DialogTitle>{label}</DialogTitle>
                        <DialogDescription>
                            {kind === 'template'
                                ? 'Periksa pemetaan isian ke versi aktif. Nilai master yang kompatibel tetap memakai snapshot tersimpan.'
                                : 'Periksa nilai terbaru dari master sebelum mengganti snapshot draft.'}{' '}
                            Setelah diterapkan, pemeriksaan dokumen perlu diulang.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="min-h-0 overflow-auto">
                        {!result && !error && <Skeleton className="h-40 w-full" />}
                        <FieldError>
                            {error ||
                                form.errors.change_token ||
                                form.errors.revision ||
                                form.errors.confirmed ||
                                Object.values(form.errors).join(' ')}
                        </FieldError>
                        {(form.errors.change_token || form.errors.revision) && (
                            <Button
                                type="button"
                                variant="outline"
                                disabled={form.processing}
                                onClick={() => {
                                    form.clearErrors();
                                    setAttempt(attempt + 1);
                                }}
                            >
                                Perbarui ringkasan
                            </Button>
                        )}
                        {error && (
                            <Button type="button" variant="outline" onClick={() => setAttempt(attempt + 1)}>
                                Muat ringkasan lagi
                            </Button>
                        )}
                        {result && (
                            <>
                                <FieldDescription>
                                    Versi draft {result.revision} · templat versi {result.target_version}.
                                    {!result.complete && ' Ada isian yang harus dilengkapi setelah penyesuaian.'}
                                </FieldDescription>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Isian</TableHead>
                                            <TableHead>Sebelum</TableHead>
                                            <TableHead>Setelah</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {result.changes.map((change, index) => (
                                            <TableRow key={`${change.label}-${index}`}>
                                                <TableCell>{change.label}</TableCell>
                                                <TableCell className="whitespace-pre-wrap">
                                                    {change.before ?? '—'}
                                                </TableCell>
                                                <TableCell className="whitespace-pre-wrap">
                                                    {change.after ?? '—'}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                        {result.changes.length === 0 && (
                                            <TableRow>
                                                <TableCell colSpan={3}>
                                                    Tidak ada perubahan nilai dokumen. Snapshot tetap dapat diperbarui
                                                    secara eksplisit.
                                                </TableCell>
                                            </TableRow>
                                        )}
                                    </TableBody>
                                </Table>
                            </>
                        )}
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={form.processing}
                            onClick={() => changeOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button type="button" disabled={!result || form.processing || !!error} onClick={apply}>
                            {form.processing ? 'Menerapkan…' : 'Konfirmasi dan terapkan'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
