import { useEffect, useRef, useState } from 'react';
import type { SubmitEventHandler } from 'react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { Separator } from '@/components/ui/separator';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { gooeyToast } from '@/components/ui/goey-toaster';
import { useValibotForm } from '@/hooks/use-valibot-form';
import { createDocumentFontSchema, type DocumentFontPage, type DocumentFontReport } from '@/schemas/document-font';
import { index, store } from '@/routes/document-fonts';

export function DocumentFonts({ disabled = false, uploadFor }: { disabled?: boolean; uploadFor?: string }) {
    const [open, setOpen] = useState(false);
    return (
        <>
            <Button type="button" size="sm" variant="outline" disabled={disabled} onClick={() => setOpen(true)}>
                {uploadFor ? 'Unggah font' : 'Font tersedia'}
            </Button>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="flex max-h-[90dvh] flex-col sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{uploadFor ? `Lengkapi font ${uploadFor}` : 'Font tersedia'}</DialogTitle>
                        <DialogDescription>
                            Font server digunakan untuk konversi PDF. Font tambahan berlaku untuk seluruh templat. DOCX
                            unduhan tetap memakai pengaturan font aslinya.
                        </DialogDescription>
                    </DialogHeader>
                    {open && <FontManager />}
                </DialogContent>
            </Dialog>
        </>
    );
}

function FontManager() {
    const [query, setQuery] = useState('');
    const [page, setPage] = useState(1);
    const [attempt, setAttempt] = useState(0);
    const [result, setResult] = useState<DocumentFontPage | null>(null);
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(true);
    useEffect(() => {
        const abort = new AbortController();
        setLoading(true);
        setError('');
        const timer = window.setTimeout(() => {
            fetch(index({ query: { q: query, page } }).url, {
                signal: abort.signal,
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            })
                .then(async (response) => {
                    if (!response.ok || response.redirected) throw new Error('Daftar font belum dapat dimuat.');
                    return response.json() as Promise<DocumentFontPage>;
                })
                .then((data) => {
                    if (!abort.signal.aborted) setResult(data);
                })
                .catch(() => {
                    if (!abort.signal.aborted)
                        setError('Daftar font belum dapat dimuat. Periksa koneksi dan sesi login.');
                })
                .finally(() => {
                    if (!abort.signal.aborted) setLoading(false);
                });
        }, 300);
        return () => {
            window.clearTimeout(timer);
            abort.abort();
        };
    }, [query, page, attempt]);
    return (
        <div className="flex min-h-0 flex-col gap-4 overflow-y-auto">
            <Field>
                <FieldLabel htmlFor="search-fonts">Cari keluarga atau varian font</FieldLabel>
                <Input
                    id="search-fonts"
                    maxLength={100}
                    value={query}
                    onChange={(event) => {
                        setQuery(event.target.value);
                        setPage(1);
                    }}
                />
            </Field>
            {loading ? (
                <Skeleton className="h-40 w-full" />
            ) : error ? (
                <FieldError>{error}</FieldError>
            ) : (
                result && (
                    <>
                        {result.warning && <FieldDescription role="status">{result.warning}</FieldDescription>}
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Keluarga</TableHead>
                                    <TableHead>Varian</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {result.data.map((font) => (
                                    <TableRow key={`${font.family}-${font.style}`}>
                                        <TableCell>{font.family}</TableCell>
                                        <TableCell>{font.style}</TableCell>
                                    </TableRow>
                                ))}
                                {result.data.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={2}>Tidak ada font yang sesuai.</TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                        <div className="flex flex-wrap items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={page <= 1}
                                onClick={() => setPage(page - 1)}
                            >
                                Sebelumnya
                            </Button>
                            <span className="text-sm">
                                {result.page} / {result.last_page} · {result.total} varian
                            </span>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={page >= result.last_page}
                                onClick={() => setPage(page + 1)}
                            >
                                Berikutnya
                            </Button>
                        </div>
                    </>
                )
            )}
            <Button
                type="button"
                variant="outline"
                size="sm"
                className="self-start"
                disabled={loading}
                onClick={() => setAttempt(attempt + 1)}
            >
                Perbarui daftar
            </Button>
            <Separator />
            <FontUpload
                maxKb={result?.max_upload_kb ?? 10240}
                onUploaded={() => {
                    setPage(1);
                    setAttempt((value) => value + 1);
                }}
            />
        </div>
    );
}

function FontUpload({ maxKb, onUploaded }: { maxKb: number; onUploaded: () => void }) {
    const input = useRef<HTMLInputElement>(null);
    const { form, submit } = useValibotForm(createDocumentFontSchema(maxKb), { font: null as File | null });
    const handleSubmit: SubmitEventHandler<HTMLFormElement> = (event) => {
        event.preventDefault();
        if (form.processing) return;
        const action = store();
        const onError = () =>
            gooeyToast.error('Galat', { description: 'Font belum dapat dipasang. Periksa pesan unggahan.' });
        if (
            !submit(action.method, action.url, {
                forceFormData: true,
                preserveScroll: true,
                onError,
                onSuccess: () => {
                    form.reset();
                    if (input.current) input.current.value = '';
                    onUploaded();
                    gooeyToast.success('Berhasil', {
                        description: 'Font telah diverifikasi dan tersedia untuk aplikasi.',
                    });
                },
            })
        )
            onError();
    };
    return (
        <form noValidate onSubmit={handleSubmit}>
            <FieldGroup>
                <Field data-invalid={!!form.errors.font}>
                    <FieldLabel htmlFor="document-font-upload">Unggah font</FieldLabel>
                    <Input
                        ref={input}
                        id="document-font-upload"
                        type="file"
                        accept=".ttf,.otf"
                        disabled={form.processing}
                        aria-invalid={!!form.errors.font}
                        onChange={(event) => {
                            form.setData('font', event.target.files?.[0] ?? null);
                            form.clearErrors();
                        }}
                    />
                    <FieldDescription>
                        TTF/OTF statis maksimal {maxKb / 1024} MB per berkas. Unggah tiap varian yang diperlukan. Berkas
                        berbeda dengan keluarga dan varian sama tidak mengganti font lama.
                    </FieldDescription>
                    <FieldError>{form.errors.font}</FieldError>
                </Field>
                <Button type="submit" className="self-start" disabled={form.processing}>
                    {form.processing ? 'Memasang dan memverifikasi…' : 'Unggah dan pasang font'}
                </Button>
            </FieldGroup>
        </form>
    );
}

export function DocumentFontWarnings({
    report,
    disabled = false,
}: {
    report: DocumentFontReport | null | undefined;
    disabled?: boolean;
}) {
    return (
        <section aria-label="Font Dokumen" className="flex flex-col gap-3">
            <p className="text-sm font-medium">Font Dokumen</p>
            {!report ? (
                <FieldDescription>Jalankan validasi DOCX untuk memeriksa font yang digunakan.</FieldDescription>
            ) : (
                <>
                    <FieldDescription>
                        Font yang kurang hanya menjadi peringatan. Generate, publikasi templat, dan unduh DOCX tetap
                        diperbolehkan.
                    </FieldDescription>
                    {report.warning && <FieldDescription role="status">{report.warning}</FieldDescription>}
                    <ul className="flex flex-col gap-3" aria-label="Font yang digunakan">
                        {report.entries.map((font) => (
                            <li key={`${font.family}-${font.style}`} className="flex flex-wrap items-center gap-2">
                                <span className="text-sm">
                                    {font.family} · {font.style}
                                </span>
                                <Badge variant={font.available ? 'secondary' : 'outline'}>
                                    {!report.available
                                        ? 'Belum diperiksa'
                                        : font.available
                                          ? 'Tersedia di server'
                                          : 'Belum tersedia'}
                                </Badge>
                                {!font.available && <DocumentFonts disabled={disabled} uploadFor={font.family} />}
                                {font.fallback && (
                                    <span className="text-sm text-muted-foreground">Fallback PDF: {font.fallback}</span>
                                )}
                            </li>
                        ))}
                    </ul>
                    {report.limitations?.map((message) => (
                        <FieldDescription key={message}>{message}</FieldDescription>
                    ))}
                </>
            )}
        </section>
    );
}
