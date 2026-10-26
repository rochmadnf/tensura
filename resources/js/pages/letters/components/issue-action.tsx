import { useCallback, useEffect, useRef, useState } from 'react';
import { router, useHttp } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { LetterOperation, LetterIssuance, LetterPreview } from '@/schemas/letter-preview';
import { issue } from '@/routes/ltr';

export function LetterIssueAction({
    letterId,
    revision,
    preview,
    issuance,
    disabled,
    onOpenChange,
    onPendingChange,
}: {
    letterId: string;
    revision: number;
    preview: LetterPreview;
    issuance: LetterIssuance;
    disabled: boolean;
    onOpenChange: (open: boolean) => void;
    onPendingChange: (pending: boolean) => void;
}) {
    const [open, setOpen] = useState(false);
    const [keep, setKeep] = useState(false);
    const [operation, setOperation] = useState(issuance.operation);
    const [error, setError] = useState('');
    const pending = !!operation && ['queued', 'running'].includes(operation.status);
    const form = useHttp({
        revision,
        artifact: '',
        fingerprint: '',
        idempotency_key: '',
        publication_month: '',
        keep_period: false,
        confirmed: true,
    });
    const key = useRef<{ signature: string; value: string } | null>(null);
    const latest = useRef(issuance.operation);
    const acceptOperation = useCallback(
        (result: LetterOperation) => {
            const previous = latest.current;
            if (previous?.id === result.id && previous.status_revision > result.status_revision) return previous;
            latest.current = result;
            setOperation(result);
            const active = ['queued', 'running'].includes(result.status);
            onPendingChange(active);
            if (!active) key.current = null;
            return result;
        },
        [onPendingChange],
    );
    const artifact = preview.artifact;
    useEffect(() => {
        function changed(event: Event) {
            const result = (event as CustomEvent<LetterOperation>).detail;
            if (result.kind !== 'issue' || result.letter_id !== letterId) return;
            const accepted = acceptOperation(result);
            const active = ['queued', 'running'].includes(accepted.status);
            if (!active) router.reload({ only: ['resource', 'issuance', 'preview', 'audits'], showProgress: false });
        }
        window.addEventListener('letter-operation', changed);
        return () => window.removeEventListener('letter-operation', changed);
    }, [letterId, acceptOperation]);
    useEffect(() => {
        if (issuance.operation) acceptOperation(issuance.operation);
    }, [issuance.operation, acceptOperation]);
    function changeOpen(value: boolean) {
        if (form.processing) return;
        setOpen(value);
        onOpenChange(value);
        if (value) {
            setKeep(!issuance.crossed_month);
            form.clearErrors();
            setError('');
        }
    }
    async function publish() {
        if (!artifact?.confirmed || !keep || form.processing || pending) return;
        const signature = `${revision}:${artifact.id}:${artifact.fingerprint}:${issuance.publication_month}`;
        if (key.current?.signature !== signature) key.current = { signature, value: crypto.randomUUID() };
        const idempotencyKey = key.current.value;
        form.transform(() => ({
            revision,
            artifact: artifact.id,
            fingerprint: artifact.fingerprint,
            idempotency_key: idempotencyKey,
            publication_month: issuance.publication_month,
            keep_period: keep,
            confirmed: true,
        }));
        try {
            const result = (await form.post(issue(letterId).url)) as { operation: LetterOperation };
            acceptOperation(result.operation);
            setOpen(false);
            onOpenChange(false);
            router.reload({ only: ['resource', 'issuance', 'preview', 'audits'], showProgress: false });
        } catch {
            setError('Penerbitan belum diterima. Periksa pesan atau muat status terbaru sebelum mencoba lagi.');
        }
    }
    return (
        <div className="flex flex-col items-start gap-3">
            {operation && (
                <p role="status" className="text-sm">
                    Penerbitan: {operation.status_label}. {operation.message}
                </p>
            )}
            <Button
                disabled={
                    disabled || form.processing || pending || !artifact?.confirmed || preview.revision !== revision
                }
                onClick={() => changeOpen(true)}
            >
                {pending ? 'Menerbitkan surat…' : 'Terbitkan surat'}
            </Button>
            {!artifact?.confirmed && (
                <FieldDescription>
                    Konfirmasikan pemeriksaan preview terbaru dengan akun Anda sebelum menerbitkan.
                </FieldDescription>
            )}
            <Dialog open={open} onOpenChange={changeOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Terbitkan Surat?</DialogTitle>
                        <DialogDescription>
                            Tanggal surat {issuance.date}, periode nomor {issuance.period}. Nomor diperiksa kembali saat
                            penerbitan. Isian memakai hasil yang sudah Anda periksa.
                        </DialogDescription>
                    </DialogHeader>
                    {issuance.crossed_month && (
                        <Field orientation="horizontal">
                            <Checkbox
                                id="keep-letter-period"
                                checked={keep}
                                onCheckedChange={(checked) => setKeep(checked)}
                                disabled={form.processing}
                            />
                            <FieldLabel htmlFor="keep-letter-period">
                                Draft melewati pergantian bulan. Saya tetap menggunakan tanggal dan periode tersebut.
                            </FieldLabel>
                        </Field>
                    )}
                    {Object.keys(form.errors).length > 0 && (
                        <FieldError>{Object.values(form.errors).flat().join(' ')}</FieldError>
                    )}
                    {error && <FieldError>{error}</FieldError>}
                    <DialogFooter>
                        <Button variant="outline" disabled={form.processing} onClick={() => changeOpen(false)}>
                            Batal
                        </Button>
                        <Button disabled={!keep || form.processing || pending} onClick={() => void publish()}>
                            {form.processing ? 'Mengirim…' : 'Ya, terbitkan'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
