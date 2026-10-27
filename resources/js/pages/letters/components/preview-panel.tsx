import { useEffect, useState } from 'react';
import { router, useHttp } from '@inertiajs/react';
import { FormCard } from '@/components/form-card';
import { DocxPreview } from '@/components/docx-preview';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { FieldDescription, FieldError } from '@/components/ui/field';
import { gooeyToast } from '@/components/ui/goey-toaster';
import { store, file, rendered, confirm } from '@/routes/ltr/previews';
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
    const checking = useHttp({ revision, fingerprint: '', preview_kind: 'docx', confirmed: true });
    const artifact = preview.artifact;
    const [ready, setReady] = useState(false);
    const [confirmed, setConfirmed] = useState(artifact?.confirmed ?? false);
    const busy = generation.processing || checking.processing;
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
    useEffect(() => {
        setReady(false);
        setConfirmed(artifact?.confirmed ?? false);
    }, [artifact?.id, artifact?.confirmed]);
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
    async function acknowledge() {
        if (!artifact) return;
        setReady(false);
        checking.transform(() => ({
            revision,
            fingerprint: artifact.fingerprint,
            preview_kind: 'docx',
            ...(preview.draft_id ? { draft_id: preview.draft_id, base_version: preview.base_version } : {}),
            confirmed: true,
        }));
        await checking.post(rendered({ letter: letterId, artifact: artifact.id }).url);
        setReady(true);
    }
    async function confirmPreview() {
        if (!artifact || !ready || busy) return;
        setError('');
        checking.transform(() => ({
            revision,
            fingerprint: artifact.fingerprint,
            preview_kind: 'docx',
            ...(preview.draft_id ? { draft_id: preview.draft_id, base_version: preview.base_version } : {}),
            confirmed: true,
        }));
        try {
            await checking.post(confirm({ letter: letterId, artifact: artifact.id }).url);
            setConfirmed(true);
            gooeyToast.success('Pemeriksaan preview tersimpan.');
            router.reload({ only: ['preview', 'audits'], showProgress: false });
        } catch {
            setError('Konfirmasi belum tersimpan. Buka kembali preview atau generate hasil terbaru.');
        }
    }
    const errors = [...Object.values(generation.errors), ...Object.values(checking.errors), ...(error ? [error] : [])];
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
                        <DocxPreview
                            key={artifact.id}
                            url={file({ letter: letterId, artifact: artifact.id }).url}
                            maxBytes={preview.max_bytes}
                            fontReport={artifact.font_report}
                            disabled={disabled || busy}
                            draft
                            title="Preview Draft Surat"
                            onOpenChange={(open) => {
                                setReady(false);
                                onOpenChange(open);
                            }}
                            onReady={acknowledge}
                            footer={
                                <Button disabled={!ready || busy || confirmed} onClick={() => void confirmPreview()}>
                                    {confirmed ? 'Sudah diperiksa' : 'Saya sudah memeriksa dokumen'}
                                </Button>
                            }
                        />
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
                {confirmed && (
                    <FieldDescription>
                        Pemeriksaan Anda tersimpan untuk hasil ini. Perubahan draft, templat, atau font terkait
                        memerlukan pemeriksaan ulang.
                    </FieldDescription>
                )}
                <FieldDescription>
                    Periksa layout dan cetak sebelum menerbitkan. PDF dapat diminta bila tampilan DOCX kurang sesuai.
                </FieldDescription>
            </div>
        </FormCard>
    );
}
