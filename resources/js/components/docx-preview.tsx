import { useEffect, useRef, useState } from 'react';
import {
    ArrowLeft01Icon,
    ArrowRight01Icon,
    FullScreenIcon,
    PrinterIcon,
    RefreshIcon,
    ViewIcon,
    ZoomInAreaIcon,
    ZoomOutAreaIcon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { FieldError } from '@/components/ui/field';
import { DOCX_PREVIEW_FRAME, fetchPreviewDocx, renderPreviewDocx } from '@/lib/docx-preview';

export function DocxPreview({
    url,
    maxBytes,
    disabled = false,
}: {
    url: string;
    maxBytes: number;
    disabled?: boolean;
}) {
    const [open, setOpen] = useState(false);
    return (
        <>
            <Button type="button" variant="outline" disabled={disabled} onClick={() => setOpen(true)}>
                <HugeiconsIcon icon={ViewIcon} data-icon="inline-start" aria-hidden="true" />
                Preview DOCX
            </Button>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="flex h-[90dvh] max-w-[calc(100%-2rem)] flex-col sm:max-w-[calc(100%-4rem)]">
                    <DialogHeader>
                        <DialogTitle>Preview Hasil Uji DOCX</DialogTitle>
                        <DialogDescription>
                            Hanya-baca. Pemenggalan halaman dan hasil cetak browser dapat berbeda dari Word. Periksa
                            hasil sebelum mengonfirmasi.
                        </DialogDescription>
                    </DialogHeader>
                    {open && <PreviewDocument key={url} url={url} maxBytes={maxBytes} />}
                </DialogContent>
            </Dialog>
        </>
    );
}

function PreviewDocument({ url, maxBytes }: { url: string; maxBytes: number }) {
    const frame = useRef<HTMLIFrameElement>(null);
    const pages = useRef<HTMLElement[]>([]);
    const [loaded, setLoaded] = useState(false);
    const [attempt, setAttempt] = useState(0);
    const [status, setStatus] = useState<{ ready: boolean; error: string | null }>({ ready: false, error: null });
    const [page, setPage] = useState(1);
    const [zoom, setZoom] = useState(100);

    useEffect(() => {
        if (!loaded || !frame.current?.contentDocument) return;
        const document = frame.current.contentDocument;
        const abort = new AbortController();
        setStatus({ ready: false, error: null });
        pages.current = [];
        async function load() {
            try {
                const bytes = await fetchPreviewDocx(url, maxBytes, abort.signal);
                if (abort.signal.aborted) return;
                const rendered = await renderPreviewDocx(bytes, document);
                if (abort.signal.aborted) return;
                if (rendered.length === 0)
                    throw new Error('Halaman dokumen tidak dapat ditampilkan. Unduh DOCX untuk memeriksanya.');
                pages.current = rendered;
                setPage(1);
                setStatus({ ready: true, error: null });
            } catch (error) {
                if (!abort.signal.aborted)
                    setStatus({
                        ready: false,
                        error:
                            error instanceof Error ? error.message : 'Preview gagal dibuka. Coba lagi atau unduh DOCX.',
                    });
            }
        }
        void load();
        return () => {
            abort.abort();
        };
    }, [loaded, attempt, url, maxBytes]);

    useEffect(() => {
        if (frame.current?.contentDocument) frame.current.contentDocument.body.style.zoom = String(zoom / 100);
    }, [zoom, status.ready]);

    function move(next: number) {
        const position = Math.max(1, Math.min(pages.current.length, next));
        pages.current[position - 1]?.scrollIntoView({ block: 'start' });
        setPage(position);
    }
    return (
        <div className="flex min-h-0 flex-1 flex-col gap-3">
            <div role="group" aria-label="Kontrol preview DOCX" className="flex flex-wrap items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="icon-sm"
                    aria-label="Halaman sebelumnya"
                    disabled={!status.ready || page <= 1}
                    onClick={() => move(page - 1)}
                >
                    <HugeiconsIcon icon={ArrowLeft01Icon} aria-hidden="true" />
                </Button>
                <span className="text-sm tabular-nums" aria-live="polite">
                    {status.ready ? `${page} / ${pages.current.length}` : '—'}
                </span>
                <Button
                    type="button"
                    variant="outline"
                    size="icon-sm"
                    aria-label="Halaman berikutnya"
                    disabled={!status.ready || page >= pages.current.length}
                    onClick={() => move(page + 1)}
                >
                    <HugeiconsIcon icon={ArrowRight01Icon} aria-hidden="true" />
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="icon-sm"
                    aria-label="Perkecil"
                    disabled={!status.ready || zoom <= 25}
                    onClick={() => setZoom(Math.max(25, zoom - 25))}
                >
                    <HugeiconsIcon icon={ZoomOutAreaIcon} aria-hidden="true" />
                </Button>
                <span className="text-sm tabular-nums">{zoom}%</span>
                <Button
                    type="button"
                    variant="outline"
                    size="icon-sm"
                    aria-label="Perbesar"
                    disabled={!status.ready || zoom >= 200}
                    onClick={() => setZoom(Math.min(200, zoom + 25))}
                >
                    <HugeiconsIcon icon={ZoomInAreaIcon} aria-hidden="true" />
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    disabled={!status.ready}
                    onClick={() => {
                        const width = Math.max(...pages.current.map((item) => item.offsetWidth));
                        if (width)
                            setZoom(
                                Math.max(
                                    25,
                                    Math.min(200, Math.floor(((frame.current!.clientWidth - 48) / width) * 100)),
                                ),
                            );
                    }}
                >
                    <HugeiconsIcon icon={FullScreenIcon} data-icon="inline-start" aria-hidden="true" />
                    Sesuaikan lebar
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    disabled={!status.ready}
                    onClick={() => {
                        frame.current?.contentWindow?.focus();
                        frame.current?.contentWindow?.print();
                    }}
                >
                    <HugeiconsIcon icon={PrinterIcon} data-icon="inline-start" aria-hidden="true" />
                    Cetak
                </Button>
            </div>
            {!status.ready && !status.error && (
                <p role="status" className="text-sm text-muted-foreground">
                    Menyiapkan preview DOCX…
                </p>
            )}
            {status.error && (
                <div className="flex flex-col items-start gap-2">
                    <FieldError>{status.error}</FieldError>
                    <Button type="button" variant="outline" onClick={() => setAttempt(attempt + 1)}>
                        <HugeiconsIcon icon={RefreshIcon} data-icon="inline-start" aria-hidden="true" />
                        Coba Lagi
                    </Button>
                </div>
            )}
            <iframe
                ref={frame}
                title="Isi hasil uji DOCX hanya-baca"
                sandbox="allow-same-origin allow-modals"
                srcDoc={DOCX_PREVIEW_FRAME}
                onLoad={() => setLoaded(true)}
                className="min-h-0 w-full flex-1 rounded-md border bg-muted"
                style={{ visibility: status.ready ? 'visible' : 'hidden' }}
            />
        </div>
    );
}
