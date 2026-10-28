import { lazy, Suspense, useState, type ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
const PdfViewer = lazy(() => import('@/components/pdf-viewer'));

export function PdfPreview({
    url,
    maxBytes,
    disabled = false,
    draft = false,
    onReady,
    onOpenChange,
    footer,
    title,
}: {
    url: string;
    maxBytes: number;
    disabled?: boolean;
    draft?: boolean;
    onReady?: () => Promise<void>;
    onOpenChange?: (open: boolean) => void;
    footer?: ReactNode;
    title?: string;
}) {
    const [open, setOpen] = useState(false);
    function changeOpen(value: boolean) {
        setOpen(value);
        onOpenChange?.(value);
    }
    return (
        <>
            <Button variant="outline" disabled={disabled} onClick={() => changeOpen(true)}>
                Lihat/Cetak PDF
            </Button>
            <Dialog open={open} onOpenChange={changeOpen}>
                <DialogContent className="flex h-[90dvh] max-w-[calc(100%-2rem)] flex-col sm:max-w-[calc(100%-4rem)]">
                    <DialogHeader>
                        <DialogTitle>{title ?? (draft ? 'Preview PDF Draft' : 'PDF Surat Resmi')}</DialogTitle>
                        <DialogDescription>
                            {draft
                                ? 'Hanya-baca. Nomor XX.XX dan penanda DRAFT belum merupakan dokumen resmi.'
                                : 'Hanya-baca. Cetak menggunakan isi PDF tersimpan.'}
                        </DialogDescription>
                    </DialogHeader>
                    {open && (
                        <Suspense fallback={<p role="status">Memuat viewer PDF…</p>}>
                            <PdfViewer key={url} url={url} maxBytes={maxBytes} onReady={onReady} />
                        </Suspense>
                    )}
                    {footer}
                </DialogContent>
            </Dialog>
        </>
    );
}
