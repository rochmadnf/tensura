import { useEffect, useState } from 'react';
import type { SubmitEventHandler } from 'react';
import { router } from '@inertiajs/react';
import { Edit04Icon } from '@hugeicons/core-free-icons';
import { FormCard } from '@/components/form-card';
import { FormActions } from '@/components/form-actions';
import { TableActionButton } from '@/components/table-action-button';
import { OptionSelect } from '@/components/option-select';
import { DatePickerInput } from '@/components/date-picker-input';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Button } from '@/components/ui/button';
import { CardAction } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { gooeyToast } from '@/components/ui/goey-toaster';
import { useValibotForm } from '@/hooks/use-valibot-form';
import {
    createLetterSchema,
    letterInitialValues,
    preserveLetterSelectionOrder,
    type LetterConfiguration,
    type LetterResource,
} from '@/schemas/letter';
import { create, index, show, store, update } from '@/routes/ltr';

export function LetterForm({
    configuration: incomingConfiguration,
    templateId,
    templateName,
    today,
    resource,
    editing = true,
    disabled = false,
    onEdit,
    onClose,
    onProcessingChange,
}: {
    configuration: LetterConfiguration;
    templateId: string;
    templateName?: string;
    today: string;
    resource?: LetterResource;
    editing?: boolean;
    disabled?: boolean;
    onEdit?: () => void;
    onClose?: () => void;
    onProcessingChange?: (value: boolean) => void;
}) {
    const [openedConfiguration] = useState(incomingConfiguration);
    const configuration = editing ? openedConfiguration : incomingConfiguration;
    const { form, submit } = useValibotForm(
        createLetterSchema(configuration),
        letterInitialValues(configuration, templateId, today, resource),
    );
    const busy = disabled || form.processing;
    const errors: Record<string, string | undefined> = form.errors;
    useEffect(() => {
        onProcessingChange?.(form.processing);
    }, [form.processing, onProcessingChange]);
    const handleSubmit: SubmitEventHandler<HTMLFormElement> = (event) => {
        event.preventDefault();
        if (busy) return;
        const action = resource ? update(resource.id) : store();
        const onError = () =>
            gooeyToast.error('Galat', {
                description: 'Draft belum disimpan. Periksa pesan dan isian; input tetap tersedia.',
            });
        if (
            !submit(action.method, action.url, {
                preserveScroll: true,
                onError,
                onSuccess: () => {
                    onClose?.();
                    gooeyToast.success('Berhasil', { description: 'Draft surat disimpan.' });
                },
            })
        )
            onError();
    };
    const selectedLabel = (
        value: string | string[] | null | undefined,
        options: { value: string; label: string }[],
    ) => {
        const ids = Array.isArray(value) ? value : value ? [value] : [];
        return (
            ids
                .map((id) => options.find((option) => option.value === id)?.label ?? 'Pilihan tersimpan tidak tersedia')
                .join('; ') || '—'
        );
    };
    if (resource && !editing)
        return (
            <FormCard
                sectioned
                title="Draft Surat"
                description={`Templat ${resource.template.name} · versi ${configuration.number}. Nomor resmi diberikan saat penerbitan.`}
                action={
                    resource.editable && (
                        <CardAction>
                            <TableActionButton
                                icon={Edit04Icon}
                                variant="warning"
                                label="Ubah draft surat"
                                tooltip="Ubah draft"
                                disabled={disabled}
                                onClick={onEdit}
                            />
                        </CardAction>
                    )
                }
            >
                <div className="flex flex-col gap-4">
                    <dl className="grid gap-3 sm:grid-cols-2">
                        <div>
                            <dt className="text-sm text-muted-foreground">Perihal</dt>
                            <dd>{resource.subject}</dd>
                        </div>
                        <div>
                            <dt className="text-sm text-muted-foreground">Tanggal Surat</dt>
                            <dd>{resource.letter_date}</dd>
                        </div>
                        <div>
                            <dt className="text-sm text-muted-foreground">Penomoran</dt>
                            <dd>
                                {resource.draft?.numbering_mode === 2
                                    ? `Manual: ${resource.draft.page_number}.${resource.draft.sequence_number}`
                                    : 'Otomatis saat terbit'}{' '}
                                · periode {resource.draft?.number_month}/{resource.draft?.number_year}
                            </dd>
                        </div>
                    </dl>
                    {configuration.variables.map((variable) => (
                        <div key={variable.id}>
                            <p className="text-sm text-muted-foreground">{variable.label}</p>
                            <p className="break-words whitespace-pre-wrap">
                                {variable.type === 4
                                    ? selectedLabel(resource.draft?.values[variable.id], variable.options)
                                    : String(resource.draft?.values[variable.id] ?? '—')}
                            </p>
                        </div>
                    ))}
                    {configuration.signers.map((slot) => (
                        <div key={slot.id}>
                            <p className="text-sm text-muted-foreground">{slot.label}</p>
                            <p>{selectedLabel(resource.draft?.signers[slot.id], slot.options)}</p>
                        </div>
                    ))}
                    <p className="text-sm text-muted-foreground">
                        Terakhir disimpan oleh {resource.draft?.updated_by} · versi draft {resource.revision}.
                    </p>
                    <details>
                        <summary className="cursor-pointer text-sm font-medium">Nilai dokumen yang tersimpan</summary>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Variabel</TableHead>
                                    <TableHead>Nilai snapshot</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {Object.entries(resource.draft?.resolved ?? {}).map(([token, value]) => (
                                    <TableRow key={token}>
                                        <TableCell>
                                            <code>{'${' + token + '}'}</code>
                                        </TableCell>
                                        <TableCell className="break-words whitespace-pre-wrap">
                                            {value || '—'}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </details>
                </div>
            </FormCard>
        );
    return (
        <FormCard
            sectioned
            title={resource ? 'Ubah Draft Surat' : 'Surat Baru'}
            description={`Templat ${templateName ?? resource?.template.name ?? ''} · versi ${configuration.number} · klasifikasi ${configuration.classification}. Isian mengikuti variabel yang digunakan DOCX. Menyimpan draft tidak memesan nomor.`}
        >
            <form
                noValidate
                onSubmit={handleSubmit}
                onKeyDown={(event) => {
                    if (event.key === 'Escape' && !event.defaultPrevented && !busy && resource) {
                        event.preventDefault();
                        onClose?.();
                    }
                }}
            >
                <FieldGroup>
                    <FieldError>
                        {errors.revision || errors.draft || errors.template || errors.template_version || errors.test}
                    </FieldError>
                    {(errors.revision || errors.template_version || errors.template || errors.draft) && (
                        <>
                            <FieldDescription>
                                Salin input yang belum tersimpan sebelum memuat versi terbaru.
                            </FieldDescription>
                            <Button
                                type="button"
                                variant="outline"
                                className="self-start"
                                onClick={() =>
                                    router.get(
                                        resource
                                            ? show(resource.id).url
                                            : create({ query: errors.template ? {} : { template: templateId } }).url,
                                        {},
                                        { preserveState: false, preserveScroll: true },
                                    )
                                }
                            >
                                Muat versi terbaru
                            </Button>
                        </>
                    )}
                    <Field data-invalid={!!errors.subject}>
                        <FieldLabel htmlFor="letter-subject">Perihal *</FieldLabel>
                        <Input
                            id="letter-subject"
                            value={form.data.subject}
                            maxLength={255}
                            disabled={busy}
                            aria-invalid={!!errors.subject}
                            onChange={(event) => form.setData('subject', event.target.value)}
                        />
                        <FieldError>{errors.subject}</FieldError>
                    </Field>
                    <Field data-invalid={!!errors.letter_date}>
                        <FieldLabel htmlFor="letter-date">Tanggal Surat *</FieldLabel>
                        <DatePickerInput
                            id="letter-date"
                            name="letter_date"
                            value={form.data.letter_date}
                            disabled={busy}
                            onValueChange={(value) => {
                                form.setData('letter_date', value);
                                if (value) {
                                    form.setData('number_year', value.slice(0, 4));
                                    form.setData('number_month', String(Number(value.slice(5, 7))));
                                }
                            }}
                        />
                        <FieldError>{errors.letter_date}</FieldError>
                    </Field>
                    <Field>
                        <FieldLabel htmlFor="letter-number-mode">Mode Nomor</FieldLabel>
                        <OptionSelect
                            id="letter-number-mode"
                            name="numbering_mode"
                            items={[
                                { value: '1', label: 'Otomatis saat diterbitkan' },
                                { value: '2', label: 'Pilih nomor manual' },
                            ]}
                            value={form.data.numbering_mode}
                            onValueChange={(value) => {
                                form.setData('numbering_mode', value);
                                form.setData('page_number', null);
                                form.setData('sequence_number', null);
                            }}
                            placeholder="Pilih mode"
                            disabled={busy}
                        />
                        <FieldError>{errors.numbering_mode}</FieldError>
                        <FieldDescription>
                            Nomor manual belum dipesan dan akan diperiksa ketersediaannya saat penerbitan.
                        </FieldDescription>
                    </Field>
                    <FieldGroup className="grid gap-4 sm:grid-cols-2">
                        {(
                            [
                                'number_month',
                                'number_year',
                                ...(form.data.numbering_mode === '2'
                                    ? (['page_number', 'sequence_number'] as const)
                                    : []),
                            ] as const
                        ).map((name) => (
                            <Field key={name} data-invalid={!!errors[name]}>
                                <FieldLabel htmlFor={`letter-${name}`}>
                                    {
                                        {
                                            number_month: 'Bulan Nomor',
                                            number_year: 'Tahun Nomor',
                                            page_number: 'Halaman',
                                            sequence_number: 'Nomor Urut',
                                        }[name]
                                    }
                                </FieldLabel>
                                <Input
                                    id={`letter-${name}`}
                                    type="number"
                                    min={1}
                                    max={
                                        name === 'number_month'
                                            ? 12
                                            : name === 'number_year'
                                              ? 9999
                                              : name === 'sequence_number'
                                                ? 99
                                                : 2147483647
                                    }
                                    value={form.data[name] ?? ''}
                                    disabled={busy}
                                    aria-invalid={!!errors[name]}
                                    onChange={(event) =>
                                        form.setData(
                                            name,
                                            name === 'page_number' || name === 'sequence_number'
                                                ? event.target.value || null
                                                : event.target.value,
                                        )
                                    }
                                />
                                <FieldError>{errors[name]}</FieldError>
                            </Field>
                        ))}
                    </FieldGroup>
                    {configuration.variables.map((variable) => {
                        const path = `values.${variable.id}`;
                        const value = form.data.values[variable.id];
                        const setValue = (next: string | string[]) =>
                            form.setData('values', { ...form.data.values, [variable.id]: next });
                        return (
                            <Field key={variable.id} data-invalid={!!errors[path]}>
                                <FieldLabel htmlFor={`letter-${variable.id}`}>
                                    {variable.label}
                                    {variable.required ? ' *' : ''}
                                </FieldLabel>
                                {variable.type === 4 ? (
                                    variable.multiple ? (
                                        <>
                                            <OptionSelect
                                                id={`letter-${variable.id}`}
                                                name={path}
                                                forceCombobox
                                                multiple
                                                items={variable.options}
                                                remote={{
                                                    source: 'variable-options',
                                                    context: variable.id,
                                                    preserveSelectedLabels: true,
                                                }}
                                                value={Array.isArray(value) ? value : []}
                                                onValueChange={(selected) =>
                                                    setValue(
                                                        preserveLetterSelectionOrder(
                                                            Array.isArray(value) ? value : [],
                                                            selected,
                                                        ),
                                                    )
                                                }
                                                disabled={busy}
                                                placeholder="Pilih data"
                                            />
                                            <FieldDescription>
                                                Urutan baris mengikuti urutan pemilihan.
                                            </FieldDescription>
                                        </>
                                    ) : (
                                        <OptionSelect
                                            id={`letter-${variable.id}`}
                                            name={path}
                                            forceCombobox
                                            items={variable.options}
                                            remote={{
                                                source: 'variable-options',
                                                context: variable.id,
                                                preserveSelectedLabels: true,
                                            }}
                                            value={typeof value === 'string' ? value : ''}
                                            onValueChange={setValue}
                                            disabled={busy}
                                            placeholder="Pilih data"
                                        />
                                    )
                                ) : variable.type === 3 ? (
                                    <DatePickerInput
                                        id={`letter-${variable.id}`}
                                        name={path}
                                        value={typeof value === 'string' ? value : ''}
                                        onValueChange={setValue}
                                        disabled={busy}
                                    />
                                ) : variable.type === 2 ? (
                                    <Textarea
                                        id={`letter-${variable.id}`}
                                        value={typeof value === 'string' ? value : ''}
                                        onChange={(event) => setValue(event.target.value)}
                                        disabled={busy}
                                        maxLength={10000}
                                        aria-invalid={!!errors[path]}
                                    />
                                ) : (
                                    <Input
                                        id={`letter-${variable.id}`}
                                        value={typeof value === 'string' ? value : ''}
                                        onChange={(event) => setValue(event.target.value)}
                                        disabled={busy}
                                        maxLength={255}
                                        aria-invalid={!!errors[path]}
                                    />
                                )}
                                <FieldError>
                                    {errors[path] ||
                                        Object.entries(errors).find(([key]) => key.startsWith(path + '.'))?.[1]}
                                </FieldError>
                            </Field>
                        );
                    })}
                    {configuration.signers.map((slot) => (
                        <Field key={slot.id} data-invalid={!!errors[`signers.${slot.id}`]}>
                            <FieldLabel htmlFor={`letter-${slot.id}`}>{slot.label} *</FieldLabel>
                            <OptionSelect
                                id={`letter-${slot.id}`}
                                name={`signers.${slot.id}`}
                                forceCombobox
                                items={slot.options}
                                remote={{ source: 'signer-candidates', context: slot.id, preserveSelectedLabels: true }}
                                value={form.data.signers[slot.id] ?? ''}
                                onValueChange={(value) =>
                                    form.setData('signers', { ...form.data.signers, [slot.id]: value })
                                }
                                disabled={busy}
                                placeholder="Pilih penandatangan"
                            />
                            <FieldError>{errors[`signers.${slot.id}`]}</FieldError>
                        </Field>
                    ))}
                    <FieldError>{errors.values || errors.signers}</FieldError>
                    <FormActions
                        cancelUrl={index().url}
                        processing={form.processing}
                        disabled={disabled}
                        onCancel={
                            resource
                                ? () => {
                                      form.reset();
                                      form.clearErrors();
                                      onClose?.();
                                  }
                                : undefined
                        }
                    />
                </FieldGroup>
            </form>
        </FormCard>
    );
}
