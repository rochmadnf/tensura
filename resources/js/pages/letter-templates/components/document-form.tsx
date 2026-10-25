import { Download04Icon, CheckmarkCircle02Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { TemplateEditAction } from './edit-action';
import { useRef, type SubmitEventHandler } from 'react';
import { useForm } from '@inertiajs/react';
import { FormCard } from '@/components/form-card';
import { FormActions } from '@/components/form-actions';
import { Button } from '@/components/ui/button';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { CardAction } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { gooeyToast } from '@/components/ui/goey-toaster';
import { useValibotForm } from '@/hooks/use-valibot-form';
import lt from '@/routes/lt';
import { store as upload, download as sourceDownload } from '@/routes/lt/source';
import { store as validate } from '@/routes/lt/validation';
import { download as exampleDownload } from '@/routes/lt/example';
import {
    createLetterTemplateDocxSchema,
    type LetterTemplateDocument,
    type LetterTemplateDocumentVersion,
} from '@/schemas/letter-template-document';

const states = {
    pending: 'Belum divalidasi',
    valid: 'Valid',
    invalid: 'Perlu perbaikan',
    stale: 'Perlu validasi ulang',
};

export function LetterTemplateDocumentForm({
    templateId,
    document,
    editable,
    maxKb,
    versions,
    disabled = false,
    onProcessingChange,
    editing,
    onEdit,
    onClose,
}: {
    templateId: string;
    document: LetterTemplateDocument;
    editable: boolean;
    maxKb: number;
    versions: LetterTemplateDocumentVersion[];
    disabled?: boolean;
    onProcessingChange: (processing: boolean) => void;
    editing: boolean;
    onEdit: () => void;
    onClose: () => void;
}) {
    const input = useRef<HTMLInputElement>(null);
    const { form, submit } = useValibotForm(createLetterTemplateDocxSchema(maxKb), { docx: null });
    const validation = useForm<{ docx?: string }>({});
    const busy = disabled || form.processing || validation.processing;
    const args = { letterTemplate: templateId, version: document.id };
    const showError = () => gooeyToast.error('Galat', { description: 'Periksa berkas dan pesan validasi DOCX.' });
    const handleSubmit: SubmitEventHandler<HTMLFormElement> = (event) => {
        event.preventDefault();
        if (busy || !editable) return;
        const action = upload(args);
        if (
            !submit(action.method, action.url, {
                forceFormData: true,
                preserveScroll: true,
                onStart: () => onProcessingChange(true),
                onFinish: () => onProcessingChange(false),
                onError: showError,
                onSuccess: () => {
                    onClose();
                    form.reset();
                    if (input.current) input.current.value = '';
                    gooeyToast.success('Berhasil', { description: 'DOCX disimpan. Jalankan validasi dokumen.' });
                },
            })
        )
            showError();
    };
    function validateSource() {
        if (busy || !editable || !document.source) return;
        validation.post(validate(args).url, {
            preserveScroll: true,
            onStart: () => onProcessingChange(true),
            onFinish: () => onProcessingChange(false),
            onError: showError,
            onSuccess: () => gooeyToast.success('Berhasil', { description: 'Struktur dan placeholder DOCX valid.' }),
        });
    }
    return (
        <FormCard
            title="DOCX Sumber"
            description="Unduh contoh, sesuaikan dokumen, lalu unggah dan validasi. Panduan memakai konfigurasi yang sudah disimpan."
            action={
                <CardAction className="col-span-full col-start-1 row-span-1 row-start-3 flex flex-wrap gap-2 justify-self-start sm:col-span-1 sm:col-start-2 sm:row-span-2 sm:row-start-1 sm:justify-self-end">
                    <Button
                        nativeButton={false}
                        variant="outline"
                        size="sm"
                        disabled={busy}
                        render={<a href={exampleDownload(args).url} />}
                    >
                        <HugeiconsIcon icon={Download04Icon} data-icon="inline-start" aria-hidden="true" />
                        Unduh DOCX Contoh
                    </Button>
                    {!editing && editable && (
                        <TemplateEditAction label="DOCX sumber" disabled={busy} onClick={onEdit} inline />
                    )}
                </CardAction>
            }
            disabled={busy}
        >
            <FieldGroup>
                <div className="flex flex-wrap items-center gap-3">
                    {document.source && (
                        <Button
                            nativeButton={false}
                            variant="outline"
                            disabled={busy}
                            render={<a href={sourceDownload(args).url} />}
                        >
                            <HugeiconsIcon icon={Download04Icon} data-icon="inline-start" aria-hidden="true" />
                            Unduh DOCX Sumber
                        </Button>
                    )}
                    <Badge variant={document.state === 'invalid' ? 'destructive' : 'secondary'}>
                        {states[document.state]}
                    </Badge>
                </div>
                <p className="text-sm text-muted-foreground">
                    {document.source
                        ? `${document.source.name} · ${Math.ceil(document.source.size / 1024)} KB`
                        : 'Belum ada DOCX sumber.'}
                </p>
                {document.errors.length > 0 && (
                    <FieldError>
                        <ul>
                            {document.errors.map((error) => (
                                <li key={error}>{error}</li>
                            ))}
                        </ul>
                    </FieldError>
                )}
                {document.state === 'stale' && (
                    <p className="text-sm text-muted-foreground">
                        Konfigurasi berubah. Jalankan validasi ulang sebelum menguji dokumen.
                    </p>
                )}
                {document.placeholders.length > 0 && (
                    <p className="text-sm text-muted-foreground">
                        Placeholder ditemukan: {document.placeholders.join(', ')}.
                    </p>
                )}
                {editable && editing && (
                    <form noValidate onSubmit={handleSubmit}>
                        <FieldGroup>
                            <Field data-invalid={!!form.errors.docx} data-disabled={busy}>
                                <FieldLabel htmlFor="docx-source">
                                    {document.source ? 'Ganti DOCX Sumber' : 'Upload DOCX Sumber'}
                                </FieldLabel>
                                <Input
                                    ref={input}
                                    id="docx-source"
                                    name="docx"
                                    type="file"
                                    accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                                    disabled={busy}
                                    aria-invalid={!!form.errors.docx}
                                    aria-describedby="docx-help"
                                    onChange={(event) => {
                                        form.setData('docx', event.target.files?.[0] ?? null);
                                        form.clearErrors('docx');
                                    }}
                                />
                                <FieldDescription id="docx-help">
                                    DOCX maksimal {maxKb / 1024} MB. Dokumen dengan macro, objek tertanam, atau konten
                                    eksternal tidak didukung.
                                </FieldDescription>
                                <FieldError>{form.errors.docx}</FieldError>
                            </Field>
                            <FormActions
                                cancelUrl={lt.index().url}
                                onCancel={onClose}
                                processing={form.processing}
                                disabled={disabled || validation.processing}
                                fullWidth={false}
                            />
                        </FieldGroup>
                    </form>
                )}
                {editable && (
                    <Field>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={busy || editing || !document.source}
                            onClick={validateSource}
                        >
                            <HugeiconsIcon icon={CheckmarkCircle02Icon} data-icon="inline-start" aria-hidden="true" />
                            {validation.processing ? 'Memvalidasi...' : 'Validasi DOCX'}
                        </Button>
                        <FieldError>{validation.errors.docx}</FieldError>
                        <FieldDescription>
                            Validasi ini memeriksa struktur dan placeholder. Lanjutkan generate dan pemeriksaan visual
                            pada kartu di bawah. Publikasi tersedia pada tahap berikutnya.
                        </FieldDescription>
                    </Field>
                )}
                {versions.filter((version) => version.id !== document.id).length > 0 && (
                    <FieldGroup>
                        <p className="text-sm font-medium">Dokumen Versi Lain</p>
                        {versions
                            .filter((version) => version.id !== document.id)
                            .map((version) => (
                                <div key={version.id} className="flex flex-wrap items-center gap-3 text-sm">
                                    <span>
                                        Versi {version.number} · {version.status}
                                    </span>
                                    <Button
                                        nativeButton={false}
                                        size="sm"
                                        variant="outline"
                                        disabled={busy}
                                        render={
                                            <a
                                                href={
                                                    exampleDownload({ letterTemplate: templateId, version: version.id })
                                                        .url
                                                }
                                            />
                                        }
                                    >
                                        <HugeiconsIcon
                                            icon={Download04Icon}
                                            data-icon="inline-start"
                                            aria-hidden="true"
                                        />
                                        Unduh Contoh
                                    </Button>
                                    {version.has_source && (
                                        <Button
                                            nativeButton={false}
                                            size="sm"
                                            variant="outline"
                                            disabled={busy}
                                            render={
                                                <a
                                                    href={
                                                        sourceDownload({
                                                            letterTemplate: templateId,
                                                            version: version.id,
                                                        }).url
                                                    }
                                                />
                                            }
                                        >
                                            <HugeiconsIcon
                                                icon={Download04Icon}
                                                data-icon="inline-start"
                                                aria-hidden="true"
                                            />
                                            Unduh Sumber
                                        </Button>
                                    )}
                                </div>
                            ))}
                    </FieldGroup>
                )}
            </FieldGroup>
        </FormCard>
    );
}
