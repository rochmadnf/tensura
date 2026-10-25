import { useValibotForm } from '@/hooks/use-valibot-form';
import { FormActions } from '@/components/form-actions';
import { OptionSelect } from '@/components/option-select';
import { Button } from '@/components/ui/button';
import { Field, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { TableCell, TableRow } from '@/components/ui/table';
import { gooeyToast } from '@/components/ui/goey-toaster';
import { Delete02Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import {
    letterTemplateSlotItemSchema,
    type ConfigurationOptions,
    type ConfigurationSlot,
    type LetterTemplateConfiguration,
} from '@/schemas/letter-template-configuration';
import slots from '@/routes/lt/configuration/slots';

export function ConfigurationSlotRow({
    slot,
    index,
    templateId,
    configuration,
    options,
    disabled,
    onClose,
    onProcessingChange,
}: {
    slot: ConfigurationSlot;
    index: number;
    templateId: string;
    configuration: LetterTemplateConfiguration;
    options: ConfigurationOptions;
    disabled: boolean;
    onClose: () => void;
    onProcessingChange: (value: boolean) => void;
}) {
    const { form, submit } = useValibotForm(letterTemplateSlotItemSchema, { item: slot });
    const errors: Record<string, string | undefined> = form.errors;
    const busy = disabled || form.processing;
    const id = `slot-form-${slot.id ?? 'new'}`;
    const inactive = configuration.warnings.filter(
        (warning) => warning.slot === index + 1 && form.data.item.candidates.includes(warning.employee),
    );
    const error = () => gooeyToast.error('Galat', { description: 'Periksa kandidat penandatangan.' });
    return (
        <TableRow>
            <TableCell>Slot {index + 1}</TableCell>
            <TableCell colSpan={2} className="min-w-72 whitespace-normal">
                <FieldGroup>
                    <Field data-invalid={Object.keys(errors).some((key) => key.startsWith('item.candidates'))}>
                        <FieldLabel htmlFor={`${id}-candidates`}>Kandidat Penandatangan</FieldLabel>
                        <OptionSelect
                            id={`${id}-candidates`}
                            name="candidates"
                            remote={{ source: 'employees' }}
                            multiple
                            forceCombobox
                            disabled={busy}
                            items={options.employees.map((employee) => ({ value: employee.id, label: employee.label }))}
                            value={form.data.item.candidates}
                            onValueChange={(candidates) => {
                                form.setData('item', { ...form.data.item, candidates });
                                form.clearErrors();
                            }}
                            placeholder="Pilih pegawai aktif"
                        />
                        {Object.entries(errors)
                            .filter(([key]) => key.startsWith('item.candidates'))
                            .map(([key, message]) => (
                                <FieldError key={key}>{message}</FieldError>
                            ))}
                    </Field>
                    {inactive.map((warning) => (
                        <div key={warning.employee} className="flex flex-wrap items-center gap-2 text-sm">
                            <span>{warning.name} — Tidak aktif</span>
                            <Button
                                type="button"
                                variant="destructive"
                                size="sm"
                                disabled={busy}
                                onClick={() =>
                                    form.setData('item', {
                                        ...form.data.item,
                                        candidates: form.data.item.candidates.filter((id) => id !== warning.employee),
                                    })
                                }
                            >
                                <HugeiconsIcon icon={Delete02Icon} data-icon="inline-start" aria-hidden="true" />
                                Hapus kandidat
                            </Button>
                        </div>
                    ))}
                    <p className="text-sm text-muted-foreground">
                        Simpan slot terlebih dahulu untuk mengatur teks alternatif dan format atribut Pegawai.
                    </p>
                </FieldGroup>
            </TableCell>
            <TableCell className="w-24">
                <form
                    id={id}
                    noValidate
                    onSubmit={(event) => {
                        event.preventDefault();
                        if (busy) return;
                        const args = { letterTemplate: templateId, version: configuration.id };
                        const action = slot.id ? slots.update({ ...args, item: slot.id }) : slots.store(args);
                        if (
                            !submit(action.method, action.url, {
                                preserveScroll: true,
                                showProgress: false,
                                onStart: () => onProcessingChange(true),
                                onFinish: () => onProcessingChange(false),
                                onError: error,
                                onSuccess: () => {
                                    onClose();
                                    gooeyToast.success('Berhasil', {
                                        description: 'Penandatangan disimpan. Periksa kembali variabel DOCX.',
                                    });
                                },
                            })
                        )
                            error();
                    }}
                >
                    <FormActions
                        cancelUrl=""
                        onCancel={onClose}
                        processing={form.processing}
                        disabled={disabled}
                        fullWidth={false}
                    />
                    <FieldError>{errors.configuration || errors.item || errors['item.id']}</FieldError>
                </form>
            </TableCell>
        </TableRow>
    );
}
