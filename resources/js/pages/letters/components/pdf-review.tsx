import { useState } from 'react';
import { router, useHttp } from '@inertiajs/react';
import { PdfPreview } from '@/components/pdf-preview';
import { Button } from '@/components/ui/button';
import { FieldDescription, FieldError } from '@/components/ui/field';
import { useLetterPdf } from '@/hooks/use-letter-pdf';
import { file } from '@/routes/ltr/pdfs';
import { rendered, confirm } from '@/routes/ltr/previews';
import type { LetterPdf } from '@/schemas/letter-preview';
import { LetterPdfStatus } from './pdf-status';

export function LetterPdfReview({
    letterId,
    revision,
    pdf,
    disabled,
    onOpenChange,
}: {
    letterId: string;
    revision: number;
    pdf: LetterPdf;
    disabled: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const conversion = useLetterPdf(letterId, pdf);
    const [ready, setReady] = useState(false);
    const [error, setError] = useState('');
    const checking = useHttp({ revision, fingerprint: '', preview_kind: 'pdf', confirmed: true });
    const artifact = pdf.artifact;
    async function check(confirmed: boolean) {
        if (!artifact) return;
        checking.transform(() => ({
            revision,
            fingerprint: artifact.fingerprint,
            preview_kind: 'pdf',
            confirmed: true,
        }));
        const target = confirmed ? confirm : rendered;
        await checking.post(target({ letter: letterId, artifact: artifact.id }).url);
        if (confirmed) router.reload({ only: ['pdf', 'audits'], showProgress: false });
    }
    return (
        <div className="flex flex-col items-start gap-3">
            {artifact ? (
                <PdfPreview
                    key={artifact.id}
                    url={file({ letter: letterId, artifact: artifact.id }).url}
                    maxBytes={pdf.max_bytes}
                    draft
                    disabled={disabled || checking.processing}
                    onOpenChange={(open) => {
                        setReady(false);
                        setError('');
                        onOpenChange(open);
                    }}
                    onReady={async () => {
                        await check(false);
                        setReady(true);
                    }}
                    footer={
                        <>
                            <Button
                                disabled={!ready || checking.processing || artifact.confirmed}
                                onClick={() =>
                                    void check(true).catch(() =>
                                        setError('Pemeriksaan PDF belum tersimpan. Buka hasil terbaru dan coba lagi.'),
                                    )
                                }
                            >
                                {artifact.confirmed ? 'Sudah diperiksa' : 'Saya sudah memeriksa PDF'}
                            </Button>
                            {error && <FieldError>{error}</FieldError>}
                        </>
                    }
                />
            ) : (
                <Button
                    variant="outline"
                    disabled={disabled || conversion.working || !pdf.source_id}
                    onClick={() => void conversion.generate()}
                >
                    {conversion.working
                        ? 'Menyiapkan PDF…'
                        : conversion.operation?.status === 'failed' || conversion.operation?.status === 'stale'
                          ? 'Coba PDF Lagi'
                          : 'Lihat/Cetak PDF'}
                </Button>
            )}
            <LetterPdfStatus
                pdf={pdf}
                operation={conversion.operation}
                error={conversion.error || Object.values(checking.errors).flat().join(' ')}
            />
            {artifact?.confirmed && (
                <FieldDescription>Pemeriksaan PDF Anda tersimpan untuk draft ini.</FieldDescription>
            )}
        </div>
    );
}
