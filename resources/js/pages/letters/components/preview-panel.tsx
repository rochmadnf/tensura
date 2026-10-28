import { useEffect, useState } from 'react';
import { router, useHttp } from '@inertiajs/react';
import { FormCard } from '@/components/form-card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { FieldDescription, FieldError } from '@/components/ui/field';
import { store, file } from '@/routes/ltr/previews';
import { LetterPdfReview } from './pdf-review';
import type { LetterOperation, LetterPreview, LetterPdf } from '@/schemas/letter-preview';

export function LetterPreviewPanel({
    letterId,
    revision,
    preview,
    pdf,
    disabled,
    onOpenChange,
}: {
    letterId: string;
    revision: number;
    preview: LetterPreview;
    pdf: LetterPdf;
    disabled: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [operation, setOperation] = useState(preview.operation);
    const [error, setError] = useState('');
    const generation = useHttp({ revision, idempotency_key: '' });
    const artifact = preview.artifact;
    const busy = generation.processing;
    const working = operation?.revision === revision && ['queued', 'running'].includes(operation.status);
    const stale = preview.revision !== revision;
    useEffect(() => {
        function changed(event: Event) {
            const result = (event as CustomEvent<LetterOperation>).detail;
            if (result.kind !== 'preview' || result.letter_id !== letterId) return;
            setOperation((previous) =>
                previous?.id === result.id && previous.status_revision >= result.status_revision ? previous : result,
            );
            if (['succeeded', 'failed', 'stale'].includes(result.status))
                router.reload({ only: ['preview', 'pdf'], showProgress: false });
        }
        window.addEventListener('letter-operation', changed);
        return () => window.removeEventListener('letter-operation', changed);
    }, [letterId]);
    useEffect(() => {
        setOperation(preview.operation);
    }, [preview.operation]);
    async function generate() {
        if (busy || working || disabled) return;
        setError('');
        generation.transform(() => ({
            revision,
            idempotency_key: crypto.randomUUID(),
            ...(preview.draft_id ? { draft_id: preview.draft_id, base_version: preview.base_version } : {}),
        }));
        try {
            const response = (await generation.post(store(letterId).url)) as { operation: LetterOperation };
            setOperation(response.operation);
            router.reload({ only: ['preview', 'pdf'], showProgress: false });
        } catch {
            setError('Preview belum dapat diminta. Periksa pesan dan coba lagi.');
        }
    }
    const errors = [...Object.values(generation.errors), ...(error ? [error] : [])];
    return (
        <FormCard
            sectioned
            title="Pemeriksaan Dokumen"
            description="Preview draft belum memakai nomor resmi. Hasil tersedia selama 24 jam."
        >
            <div className="flex flex-col items-start gap-3">
                <FieldDescription>
                    Simpan perubahan sebelum meminta preview. Anda dapat berpindah halaman selama dokumen diproses.
                </FieldDescription>
                {operation && (
                    <p role="status" className="text-sm">
                        <Badge variant="secondary">{operation.status_label}</Badge> {operation.message}
                    </p>
                )}
                {preview.reason && <FieldDescription>{preview.reason}</FieldDescription>}
                {stale && (
                    <FieldError>Draft berubah. Salin input yang belum disimpan lalu muat halaman terbaru.</FieldError>
                )}
                {errors.length > 0 && <FieldError>{errors.flat().join(' ')}</FieldError>}
                <div className="flex flex-wrap gap-2">
                    <Button
                        variant="outline"
                        disabled={disabled || busy || working || stale || !preview.can_generate}
                        onClick={() => void generate()}
                    >
                        {working ? 'Menyiapkan preview…' : artifact ? 'Perbarui preview' : 'Generate preview'}
                    </Button>
                    {artifact && !stale && (
                        <Button
                            nativeButton={false}
                            variant="outline"
                            disabled={disabled || busy}
                            render={<a href={file({ letter: letterId, artifact: artifact.id }).url} />}
                        >
                            Unduh DOCX
                        </Button>
                    )}
                </div>
                <LetterPdfReview
                    key={pdf.source_id ?? 'no-pdf'}
                    letterId={letterId}
                    revision={revision}
                    pdf={pdf}
                    disabled={disabled || busy || stale}
                    onOpenChange={onOpenChange}
                />

                <FieldDescription>
                    Unduh DOCX untuk membukanya di Word. Periksa dan konfirmasikan PDF terbaru sebelum menerbitkan.
                </FieldDescription>
            </div>
        </FormCard>
    );
}
