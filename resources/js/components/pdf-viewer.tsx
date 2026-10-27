import { useEffect, useMemo, useRef, useState } from 'react';
import { createPluginRegistration } from '@embedpdf/core';
import { EmbedPDF } from '@embedpdf/core/react';
import { usePdfiumEngine } from '@embedpdf/engines/react';
import { DocumentContent, DocumentManagerPluginPackage } from '@embedpdf/plugin-document-manager/react';
import { Viewport, ViewportPluginPackage } from '@embedpdf/plugin-viewport/react';
import { Scroller, ScrollPluginPackage, useScroll } from '@embedpdf/plugin-scroll/react';
import { RenderLayer, RenderPluginPackage } from '@embedpdf/plugin-render/react';
import { ZoomMode, ZoomPluginPackage, useZoom } from '@embedpdf/plugin-zoom/react';
import { PrintPluginPackage, usePrint } from '@embedpdf/plugin-print/react';
import wasmUrl from '@embedpdf/pdfium/pdfium.wasm?url';
import { HugeiconsIcon } from '@hugeicons/react';
import {
    ArrowLeft01Icon,
    ArrowRight01Icon,
    ZoomInAreaIcon,
    ZoomOutAreaIcon,
    PrinterIcon,
} from '@hugeicons/core-free-icons';
import { Button } from '@/components/ui/button';
import { FieldError } from '@/components/ui/field';
import { fetchPreviewPdf } from '@/lib/pdf-preview';

export default function PdfViewer({
    url,
    maxBytes,
    onReady,
}: {
    url: string;
    maxBytes: number;
    onReady?: () => Promise<void>;
}) {
    const { engine, isLoading, error } = usePdfiumEngine({
        wasmUrl: typeof window === 'undefined' ? wasmUrl : new URL(wasmUrl, window.location.href).href,
        worker: true,
        fontFallback: null,
    });
    const [buffer, setBuffer] = useState<ArrayBuffer | null>(null);
    const [failure, setFailure] = useState('');
    useEffect(() => {
        const abort = new AbortController();
        fetchPreviewPdf(url, maxBytes, abort.signal)
            .then((bytes) => {
                if (!abort.signal.aborted) setBuffer(bytes);
            })
            .catch((reason: unknown) => {
                if (!abort.signal.aborted) setFailure(reason instanceof Error ? reason.message : 'PDF gagal dimuat.');
            });
        return () => abort.abort();
    }, [url, maxBytes]);
    const plugins = useMemo(
        () =>
            buffer
                ? [
                      createPluginRegistration(DocumentManagerPluginPackage, {
                          initialDocuments: [{ buffer, name: 'surat.pdf', documentId: 'letter-pdf' }],
                      }),
                      createPluginRegistration(ViewportPluginPackage),
                      createPluginRegistration(ScrollPluginPackage),
                      createPluginRegistration(RenderPluginPackage),
                      createPluginRegistration(ZoomPluginPackage, {
                          defaultZoomLevel: ZoomMode.FitWidth,
                          minZoom: 0.25,
                          maxZoom: 3,
                      }),
                      createPluginRegistration(PrintPluginPackage),
                  ]
                : [],
        [buffer],
    );
    if (error || failure)
        return <FieldError>{failure || 'Mesin PDF gagal dimuat. Tutup dan buka kembali viewer.'}</FieldError>;
    if (isLoading || !engine || !buffer)
        return (
            <p role="status" className="text-sm text-muted-foreground">
                Menyiapkan viewer PDF…
            </p>
        );
    return (
        <EmbedPDF
            engine={engine}
            plugins={plugins}
            config={{
                permissions: {
                    overrides: {
                        modifyContents: false,
                        modifyAnnotations: false,
                        fillForms: false,
                        assembleDocument: false,
                    },
                },
            }}
        >
            {({ activeDocumentId }) =>
                activeDocumentId && (
                    <DocumentContent documentId={activeDocumentId}>
                        {({ isLoaded, isError }) =>
                            isError ? (
                                <FieldError>PDF tidak dapat dirender. Tutup dan buka kembali viewer.</FieldError>
                            ) : isLoaded ? (
                                <PdfPages documentId={activeDocumentId} onReady={onReady} />
                            ) : (
                                <p role="status">Membuka PDF…</p>
                            )
                        }
                    </DocumentContent>
                )
            }
        </EmbedPDF>
    );
}

function PdfPages({ documentId, onReady }: { documentId: string; onReady?: () => Promise<void> }) {
    const { provides: scroll, state: pages } = useScroll(documentId);
    const { provides: zoom, state: scale } = useZoom(documentId);
    const { provides: printing } = usePrint(documentId);
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);
    const acknowledged = useRef(false);
    async function ready() {
        if (acknowledged.current) return;
        acknowledged.current = true;
        try {
            await onReady?.();
        } catch {
            setError('Pemeriksaan belum tersambung. Tutup dan buka kembali PDF.');
        }
    }
    async function print() {
        if (!printing || busy) return;
        setBusy(true);
        setError('');
        try {
            await printing.print().toPromise();
        } catch {
            setError('Cetak PDF belum berhasil. Silakan coba lagi.');
        } finally {
            setBusy(false);
        }
    }
    return (
        <div className="flex min-h-0 flex-1 flex-col gap-3">
            <div role="group" aria-label="Kontrol preview PDF" className="flex flex-wrap items-center gap-2">
                <Button
                    variant="outline"
                    size="icon-sm"
                    aria-label="Halaman PDF sebelumnya"
                    disabled={!scroll || pages.currentPage <= 1}
                    onClick={() => scroll?.scrollToPreviousPage()}
                >
                    <HugeiconsIcon icon={ArrowLeft01Icon} aria-hidden="true" />
                </Button>
                <span aria-live="polite" className="text-sm tabular-nums">
                    {pages.currentPage} / {pages.totalPages}
                </span>
                <Button
                    variant="outline"
                    size="icon-sm"
                    aria-label="Halaman PDF berikutnya"
                    disabled={!scroll || pages.currentPage >= pages.totalPages}
                    onClick={() => scroll?.scrollToNextPage()}
                >
                    <HugeiconsIcon icon={ArrowRight01Icon} aria-hidden="true" />
                </Button>
                <Button
                    variant="outline"
                    size="icon-sm"
                    aria-label="Perkecil PDF"
                    disabled={!zoom || scale.currentZoomLevel <= 0.25}
                    onClick={() => zoom?.zoomOut()}
                >
                    <HugeiconsIcon icon={ZoomOutAreaIcon} aria-hidden="true" />
                </Button>
                <span className="text-sm tabular-nums">{Math.round(scale.currentZoomLevel * 100)}%</span>
                <Button
                    variant="outline"
                    size="icon-sm"
                    aria-label="Perbesar PDF"
                    disabled={!zoom || scale.currentZoomLevel >= 3}
                    onClick={() => zoom?.zoomIn()}
                >
                    <HugeiconsIcon icon={ZoomInAreaIcon} aria-hidden="true" />
                </Button>
                <Button variant="outline" disabled={!zoom} onClick={() => zoom?.requestZoom(ZoomMode.FitWidth)}>
                    Sesuaikan lebar
                </Button>
                <Button variant="outline" disabled={!printing || busy} onClick={() => void print()}>
                    <HugeiconsIcon icon={PrinterIcon} data-icon="inline-start" aria-hidden="true" />
                    {busy ? 'Menyiapkan cetak…' : 'Cetak PDF'}
                </Button>
            </div>
            {error && <FieldError>{error}</FieldError>}
            <Viewport
                documentId={documentId}
                className="min-h-0 flex-1 rounded-md border bg-muted"
                tabIndex={0}
                aria-label="Halaman PDF hanya-baca"
            >
                <Scroller
                    documentId={documentId}
                    renderPage={({ width, height, pageIndex }) => (
                        <div style={{ width, height }}>
                            <RenderLayer
                                documentId={documentId}
                                pageIndex={pageIndex}
                                onLoad={() => void ready()}
                                onError={() => setError('Halaman PDF gagal dirender. Buka kembali viewer.')}
                            />
                        </div>
                    )}
                />
            </Viewport>
        </div>
    );
}
