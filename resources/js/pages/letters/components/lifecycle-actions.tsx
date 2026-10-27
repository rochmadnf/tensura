import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Textarea } from '@/components/ui/textarea';
import { revise, recover, cancel, discardDraft } from '@/routes/ltr';
import type { LetterLifecycle } from '@/schemas/letter';

export function LetterLifecycleActions({
    letterId,
    status,
    lifecycle,
    disabled,
    onOpenChange,
}: {
    letterId: string;
    status: number;
    lifecycle: LetterLifecycle;
    disabled: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [action, setAction] = useState<'revise' | 'recover' | 'cancel' | 'discard' | null>(null);
    const form = useForm({ state_token: '', base_version: '', reason: '', confirmed: true });
    const label =
        action === 'cancel'
            ? 'Batalkan surat'
            : action === 'discard'
              ? 'Buang draft'
              : action === 'recover'
                ? 'Mulai pemulihan'
                : 'Buat revisi';
    function close() {
        if (form.processing) return;
        setAction(null);
        onOpenChange(false);
    }
    function open(value: typeof action) {
        form.setData({
            state_token: lifecycle.state_token,
            base_version: lifecycle.base_version ?? '',
            reason: '',
            confirmed: true,
        });
        form.clearErrors();
        setAction(value);
        onOpenChange(true);
    }
    function submit() {
        if (!action || !form.data.base_version || form.processing) return;
        form.transform((data) => ({ ...data, confirmed: true }));
        const route =
            action === 'cancel'
                ? cancel
                : action === 'discard'
                  ? discardDraft
                  : action === 'recover'
                    ? recover
                    : revise;
        form.post(route(letterId).url, {
            preserveScroll: true,
            onSuccess: () => {
                setAction(null);
                onOpenChange(false);
            },
        });
    }
    return (
        <>
            <div className="flex flex-wrap gap-2">
                {status === 2 && !lifecycle.has_draft && (
                    <Button variant="outline" disabled={disabled} onClick={() => open('revise')}>
                        Buat revisi
                    </Button>
                )}
                {status === 3 && !lifecycle.has_draft && (
                    <Button variant="outline" disabled={disabled} onClick={() => open('recover')}>
                        Pulihkan surat
                    </Button>
                )}
                {status === 2 && (
                    <Button variant="outline" disabled={disabled} onClick={() => open('cancel')}>
                        Batalkan surat
                    </Button>
                )}
                {lifecycle.has_draft && lifecycle.draft_kind !== 1 && (
                    <Button variant="outline" disabled={disabled} onClick={() => open('discard')}>
                        Buang draft
                    </Button>
                )}
            </div>
            <Dialog
                open={action !== null}
                onOpenChange={(value) => {
                    if (!value) close();
                }}
            >
                <DialogContent showCloseButton={!form.processing}>
                    <DialogHeader>
                        <DialogTitle>{label}?</DialogTitle>
                        <DialogDescription>
                            {action === 'cancel'
                                ? 'Surat menjadi Batal dan nomor dapat digunakan kembali. Draft revisi yang ada akan dibuang; dokumen resmi serta riwayat tetap tersimpan.'
                                : action === 'discard'
                                  ? 'Isian draft lanjutan dibuang. Dokumen resmi, nomor, dan riwayat tetap tersimpan.'
                                  : 'Draft menggunakan versi templat aktif terbaru dengan isian kompatibel dari versi resmi terakhir. Periksa hasil pemetaan; tanggal dan periode asal dipertahankan.'}
                        </DialogDescription>
                    </DialogHeader>
                    {action === 'cancel' && (
                        <Field>
                            <FieldLabel htmlFor="cancel-letter-reason">Alasan pembatalan *</FieldLabel>
                            <Textarea
                                id="cancel-letter-reason"
                                value={form.data.reason}
                                maxLength={2000}
                                disabled={form.processing}
                                onChange={(event) => form.setData('reason', event.target.value)}
                            />
                        </Field>
                    )}
                    {Object.keys(form.errors).length > 0 && (
                        <FieldError>{Object.values(form.errors).flat().join(' ')}</FieldError>
                    )}
                    <DialogFooter>
                        <Button variant="outline" disabled={form.processing} onClick={close}>
                            Kembali
                        </Button>
                        <Button
                            disabled={form.processing || (action === 'cancel' && !form.data.reason.trim())}
                            onClick={submit}
                        >
                            {form.processing ? 'Memproses…' : `Ya, ${label.toLowerCase()}`}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
