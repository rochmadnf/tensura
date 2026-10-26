import { useForm } from '@inertiajs/react';
import { CheckmarkCircle02Icon, Download04Icon, PlayCircle02Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import type { SubmitEventHandler } from 'react';
import { FormCard } from '@/components/form-card';
import { DocxPreview } from '@/components/docx-preview';
import { OptionSelect } from '@/components/option-select';
import { DatePickerInput } from '@/components/date-picker-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { gooeyToast } from '@/components/ui/goey-toaster';
import { confirm, download, store } from '@/routes/lt/test';
import type { LetterTemplateTest } from '@/schemas/letter-template-test';

const states = {
    missing: 'Belum ada hasil uji',
    stale: 'Hasil uji perlu diperbarui',
    expired: 'Hasil uji kedaluwarsa',
    ready: 'Menunggu pemeriksaan visual',
    confirmed: 'Pemeriksaan visual dikonfirmasi',
};

export function preserveSelectionOrder(previous: string[], selected: string[]): string[] {
    return [...previous.filter((id) => selected.includes(id)), ...selected.filter((id) => !previous.includes(id))];
}

export function LetterTemplateTestForm({
    templateId,
    test,
    disabled,
    onProcessingChange,
    readOnly = false,
}: {
    readOnly?: boolean;
    templateId: string;
    test: LetterTemplateTest;
    disabled: boolean;
    onProcessingChange: (processing: boolean) => void;
}) {
    const form = useForm({
        validation_token: test.validation_token,
        values: Object.fromEntries(
            test.variables.map((variable) => [variable.id, variable.is_multiple ? [] : '']),
        ) as Record<string, string | string[]>,
        signers: Object.fromEntries(test.signer_slots.map((slot) => [slot.id, ''])) as Record<string, string>,
    });
    const confirmation = useForm({ test_token: test.test_token ?? '', confirmed: false });
    const errors: Record<string, string | undefined> = form.errors;
    const confirmErrors: Record<string, string | undefined> = confirmation.errors;
    const busy = disabled || form.processing || confirmation.processing;
    const args = { letterTemplate: templateId, version: test.id };
    const showError = () =>
        gooeyToast.error('Galat', { description: 'Periksa data contoh dan status DOCX hasil uji.' });
    const handleSubmit: SubmitEventHandler<HTMLFormElement> = (event) => {
        event.preventDefault();
        if (busy || !test.can_generate) return;
        form.post(store(args).url, {
            preserveScroll: true,
            onStart: () => onProcessingChange(true),
            onFinish: () => onProcessingChange(false),
            onError: showError,
            onSuccess: () => {
                confirmation.setData('confirmed', false);
                confirmation.clearErrors();
                gooeyToast.success('Berhasil', {
                    description: 'Hasil uji dibuat. Buka preview atau unduh DOCX untuk memeriksa tata letaknya.',
                });
            },
        });
    };
    function confirmResult() {
        if (busy || !test.can_generate || test.state !== 'ready' || !test.test_token || !confirmation.data.confirmed)
            return;
        confirmation.transform((data) => ({ ...data, test_token: test.test_token! }));
        confirmation.post(confirm(args).url, {
            preserveScroll: true,
            onStart: () => onProcessingChange(true),
            onFinish: () => onProcessingChange(false),
            onError: showError,
            onSuccess: () => gooeyToast.success('Berhasil', { description: 'Pemeriksaan visual hasil uji dicatat.' }),
        });
    }
    return (
        <FormCard
            title="Generate dan Pemeriksaan DOCX"
            step={5}
            description="Isi data contoh untuk token yang digunakan dalam DOCX tersimpan, lalu buka preview atau unduh hasilnya untuk diperiksa."
        >
            <FieldGroup>
                <Badge variant={test.state === 'confirmed' ? 'secondary' : 'outline'}>{states[test.state]}</Badge>
                {!test.can_generate && (
                    <p className="text-sm text-muted-foreground">
                        {readOnly
                            ? 'Versi ini hanya dapat dibaca. Buat atau buka draft untuk melakukan pengujian baru.'
                            : 'Selesaikan validasi DOCX pada draft sebelum menjalankan pengujian.'}
                    </p>
                )}
                <FieldError>{errors.test || errors.docx || confirmErrors.test}</FieldError>
                <FieldError>{errors.validation_token || errors.values || errors.signers}</FieldError>
                {test.can_generate && (
                    <form noValidate onSubmit={handleSubmit}>
                        <FieldGroup>
                            {test.variables.length === 0 && test.signer_slots.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    Tidak ada token isian yang perlu diisi. Generate akan menggunakan isi dokumen
                                    tersimpan.
                                </p>
                            )}
                            {test.variables.map((variable) => {
                                const path = `values.${variable.id}`;
                                const value = form.data.values[variable.id];
                                const setValue = (next: string | string[]) => {
                                    form.setData('values', { ...form.data.values, [variable.id]: next });
                                    form.clearErrors();
                                };
                                const messages = Object.entries(errors)
                                    .filter(([key]) => key === path || key.startsWith(path + '.'))
                                    .map(([, message]) => message);
                                return (
                                    <Field key={variable.id} data-invalid={messages.length > 0}>
                                        <FieldLabel htmlFor={`test-${path}`}>
                                            {variable.label}
                                            {variable.is_required ? ' *' : ' (opsional)'}
                                        </FieldLabel>
                                        {variable.type === 4 ? (
                                            variable.is_multiple ? (
                                                <>
                                                    <OptionSelect
                                                        multiple
                                                        forceCombobox
                                                        id={`test-${path}`}
                                                        name={path}
                                                        remote={{ source: 'variable-options', context: variable.id }}
                                                        items={variable.options.map((option) => ({
                                                            value: option.id,
                                                            label: option.label,
                                                        }))}
                                                        value={value as string[]}
                                                        onValueChange={(selected) =>
                                                            setValue(
                                                                preserveSelectionOrder(value as string[], selected),
                                                            )
                                                        }
                                                        disabled={busy}
                                                        placeholder="Pilih data contoh"
                                                    />
                                                    <FieldDescription>
                                                        Urutan baris mengikuti urutan pemilihan. Maksimal{' '}
                                                        {test.limits.max_rows} pilihan.
                                                    </FieldDescription>
                                                    <ol
                                                        aria-label={`Urutan baris ${variable.label}`}
                                                        className="list-inside list-decimal text-sm"
                                                    >
                                                        {(value as string[]).map((id) => (
                                                            <li key={id}>
                                                                {variable.options.find((option) => option.id === id)
                                                                    ?.label ?? 'Tidak tersedia'}
                                                            </li>
                                                        ))}
                                                    </ol>
                                                </>
                                            ) : (
                                                <OptionSelect
                                                    forceCombobox
                                                    id={`test-${path}`}
                                                    name={path}
                                                    remote={{ source: 'variable-options', context: variable.id }}
                                                    items={variable.options.map((option) => ({
                                                        value: option.id,
                                                        label: option.label,
                                                    }))}
                                                    value={value as string}
                                                    onValueChange={setValue}
                                                    disabled={busy}
                                                    placeholder="Pilih data contoh"
                                                />
                                            )
                                        ) : variable.type === 3 ? (
                                            <DatePickerInput
                                                id={`test-${path}`}
                                                name={path}
                                                value={value as string}
                                                onValueChange={setValue}
                                                disabled={busy}
                                            />
                                        ) : variable.type === 2 ? (
                                            <Textarea
                                                id={`test-${path}`}
                                                value={value as string}
                                                onChange={(event) => setValue(event.target.value)}
                                                maxLength={test.limits.long_text_max}
                                                disabled={busy}
                                            />
                                        ) : (
                                            <Input
                                                id={`test-${path}`}
                                                value={value as string}
                                                onChange={(event) => setValue(event.target.value)}
                                                maxLength={test.limits.short_text_max}
                                                disabled={busy}
                                            />
                                        )}
                                        {messages.map((message, index) => (
                                            <FieldError key={index}>{message}</FieldError>
                                        ))}
                                    </Field>
                                );
                            })}
                            {test.signer_slots.map((slot) => (
                                <Field key={slot.id}>
                                    <FieldLabel htmlFor={`test-signers-${slot.id}`}>{slot.label} *</FieldLabel>
                                    <OptionSelect
                                        forceCombobox
                                        id={`test-signers-${slot.id}`}
                                        name={`signers.${slot.id}`}
                                        remote={{ source: 'signer-candidates', context: slot.id }}
                                        items={slot.options.map((option) => ({
                                            value: option.id,
                                            label: option.label,
                                        }))}
                                        value={form.data.signers[slot.id]}
                                        onValueChange={(value) => {
                                            form.setData('signers', { ...form.data.signers, [slot.id]: value });
                                            form.clearErrors();
                                        }}
                                        disabled={busy}
                                        placeholder="Pilih kandidat aktif"
                                    />
                                    <FieldError>{errors[`signers.${slot.id}`]}</FieldError>
                                </Field>
                            ))}
                            <FieldDescription>
                                Gunakan data contoh seperlunya. Generate ulang mengganti hasil uji dan membatalkan
                                konfirmasi sebelumnya. Hasil berlaku {test.limits.expires_hours} jam.
                            </FieldDescription>
                            <Button type="submit" className="self-start" disabled={busy}>
                                <HugeiconsIcon icon={PlayCircle02Icon} data-icon="inline-start" aria-hidden="true" />
                                {form.processing ? 'Membuat hasil uji…' : 'Generate Hasil Uji'}
                            </Button>
                        </FieldGroup>
                    </form>
                )}
                {(test.state === 'ready' || test.state === 'confirmed') && test.test_token && (
                    <FieldGroup>
                        <div className="self-start">
                            <DocxPreview
                                url={download(args, { query: { test_token: test.test_token } }).url}
                                maxBytes={test.limits.max_output_bytes}
                                disabled={busy}
                            />
                        </div>
                        <Button
                            nativeButton={false}
                            variant="outline"
                            className="self-start"
                            disabled={busy}
                            render={<a href={download(args, { query: { test_token: test.test_token } }).url} />}
                        >
                            <HugeiconsIcon icon={Download04Icon} data-icon="inline-start" aria-hidden="true" />
                            Unduh Hasil Uji DOCX
                        </Button>
                        {test.expires_at && (
                            <p className="text-sm text-muted-foreground">
                                Berlaku sampai {new Date(test.expires_at).toLocaleString('id-ID')}.
                            </p>
                        )}
                        {test.state === 'confirmed' ? (
                            <p className="text-sm">Pemeriksaan visual dicatat atas nama {test.confirmed_by}.</p>
                        ) : test.can_generate ? (
                            <>
                                <Field orientation="horizontal">
                                    <Checkbox
                                        id="confirm-docx-visual"
                                        checked={confirmation.data.confirmed}
                                        onCheckedChange={(checked) => confirmation.setData('confirmed', checked)}
                                        disabled={busy}
                                    />
                                    <FieldLabel htmlFor="confirm-docx-visual">
                                        Saya sudah membuka hasil uji DOCX terbaru dan memeriksa isi serta tata letaknya.
                                    </FieldLabel>
                                </Field>
                                <FieldError>{confirmErrors.confirmed || confirmErrors.test_token}</FieldError>
                                <Button
                                    type="button"
                                    className="self-start"
                                    variant="outline"
                                    disabled={busy || !confirmation.data.confirmed}
                                    onClick={confirmResult}
                                >
                                    <HugeiconsIcon
                                        icon={CheckmarkCircle02Icon}
                                        data-icon="inline-start"
                                        aria-hidden="true"
                                    />
                                    Konfirmasi Pemeriksaan Visual
                                </Button>
                            </>
                        ) : null}
                    </FieldGroup>
                )}
            </FieldGroup>
        </FormCard>
    );
}
