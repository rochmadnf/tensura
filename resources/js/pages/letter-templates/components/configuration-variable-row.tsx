import { Fragment, useRef } from 'react';
import { useValibotForm } from '@/hooks/use-valibot-form';
import {
    letterTemplateVariableItemSchema,
    type ConfigurationOptions,
    type ConfigurationVariable,
} from '@/schemas/letter-template-configuration';
import { TableCell, TableRow } from '@/components/ui/table';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { OptionSelect } from '@/components/option-select';
import { FormActions } from '@/components/form-actions';
import { gooeyToast } from '@/components/ui/goey-toaster';
import variables from '@/routes/lt/configuration/variables';
import { ConfigurationVariableDetails } from './configuration-variable';
import { slugifyVariable } from '@/lib/variable-slug';

export function ConfigurationVariableRow({
    variable,
    index,
    options,
    templateId,
    versionId,
    disabled,
    onClose,
    onProcessingChange,
}: {
    variable: ConfigurationVariable;
    index: number;
    options: ConfigurationOptions;
    templateId: string;
    versionId: string;
    disabled: boolean;
    onClose: () => void;
    onProcessingChange: (value: boolean) => void;
}) {
    const { form, submit } = useValibotForm(letterTemplateVariableItemSchema, { item: variable });
    const value = form.data.item;
    const errors: Record<string, string | undefined> = form.errors;
    const busy = disabled || form.processing;
    const id = `variable-form-${variable.id ?? 'new'}`;
    const employee = value.type === 4 && value.option_source === 2 && !value.is_multiple;
    const manualNameRef = useRef(Boolean(variable.id && variable.name));

    function patch(changes: Partial<ConfigurationVariable>) {
        form.setData('item', { ...value, ...changes });
        form.clearErrors();
    }
    const error = () => gooeyToast.error('Galat', { description: 'Periksa isian pada formulir.' });
    return (
        <Fragment>
            <TableRow>
                <TableCell>{index + 1}</TableCell>
                <TableCell className="min-w-48">
                    <Field data-invalid={!!errors['item.name']}>
                        <FieldLabel className="sr-only" htmlFor={`${id}-name`}>
                            Nama Variabel
                        </FieldLabel>
                        <Input
                            id={`${id}-name`}
                            form={id}
                            autoFocus
                            value={value.name}
                            maxLength={100}
                            disabled={busy}
                            aria-invalid={!!errors['item.name']}
                            onChange={(event) => {
                                manualNameRef.current = true;
                                patch({ name: event.target.value });
                            }}
                            placeholder="Cth: nama_variabel"
                        />
                        <FieldError>{errors['item.name']}</FieldError>
                    </Field>
                </TableCell>
                <TableCell className="min-w-48">
                    <Field data-invalid={!!errors['item.label']}>
                        <FieldLabel className="sr-only" htmlFor={`${id}-label`}>
                            Pertanyaan
                        </FieldLabel>
                        <Input
                            id={`${id}-label`}
                            form={id}
                            value={value.label}
                            maxLength={100}
                            disabled={busy}
                            aria-invalid={!!errors['item.label']}
                            onChange={(event) => {
                                const newLabel = event.target.value;
                                const isNameAuto =
                                    !manualNameRef.current ||
                                    !value.name ||
                                    value.name === slugifyVariable(value.label);
                                patch({
                                    label: newLabel,
                                    ...(isNameAuto && !variable.id ? { name: slugifyVariable(newLabel) } : {}),
                                });
                            }}
                            placeholder="Cth: Kamu sehat hari ini?"
                        />
                        <FieldError>{errors['item.label']}</FieldError>
                    </Field>
                </TableCell>
                <TableCell className="min-w-72">
                    <div className="flex items-center gap-3">
                        <Field data-invalid={!!errors['item.type']}>
                            <FieldLabel className="sr-only" htmlFor={`${id}-type`}>
                                Jenis Pertanyaan
                            </FieldLabel>
                            <OptionSelect
                                id={`${id}-type`}
                                name="type"
                                placeholder="Pilih jenis pertanyaan"
                                items={options.types.map((type) => ({ value: String(type.id), label: type.label }))}
                                value={String(value.type)}
                                disabled={busy}
                                onValueChange={(type) => {
                                    const numType = Number(type);
                                    patch({
                                        type: numType,
                                        option_source: null,
                                        reference_group: null,
                                        is_multiple: false,
                                        columns: [],
                                        default_text: null,
                                        employee_attributes: [],
                                        radio:
                                            numType === 5
                                                ? {
                                                      selected_text: '✔',
                                                      options: [
                                                          { id: null, code: '', label: '' },
                                                          { id: null, code: '', label: '' },
                                                      ],
                                                  }
                                                : null,
                                        numeric:
                                            numType === 6
                                                ? { mode: 'integer', scale: 0, allow_negative: false }
                                                : numType === 7
                                                  ? { mode: 'decimal', scale: 2, allow_negative: false }
                                                  : null,
                                        date_range:
                                            numType === 8 ? { basis: 'working_days', date_format: 'd-m-Y' } : null,
                                    });
                                }}
                            />
                        </Field>
                        <Field orientation="horizontal" className="w-auto">
                            <FieldLabel htmlFor={`${id}-required`}>Wajib diisi</FieldLabel>
                            <Switch
                                id={`${id}-required`}
                                checked={value.is_required}
                                disabled={busy}
                                onCheckedChange={(is_required) => patch({ is_required })}
                            />
                        </Field>
                    </div>
                    <FieldError>{errors['item.type'] || errors['item.is_required']}</FieldError>
                </TableCell>
                <TableCell className="min-w-44">
                    {value.is_multiple || [5, 6, 7, 8].includes(value.type) ? (
                        'Tidak berlaku'
                    ) : employee ? (
                        'Per atribut'
                    ) : (
                        <Field data-invalid={!!errors['item.default_text']}>
                            <FieldLabel className="sr-only" htmlFor={`${id}-fallback`}>
                                Teks Alternatif
                            </FieldLabel>
                            <Input
                                id={`${id}-fallback`}
                                form={id}
                                value={value.default_text ?? ''}
                                disabled={busy}
                                maxLength={10000}
                                onChange={(event) => patch({ default_text: event.target.value || null })}
                                placeholder="Kosong"
                                aria-invalid={!!errors['item.default_text']}
                            />
                            <FieldError>{errors['item.default_text']}</FieldError>
                        </Field>
                    )}
                </TableCell>
                <TableCell className="w-24">
                    <form
                        id={id}
                        noValidate
                        onSubmit={(event) => {
                            event.preventDefault();
                            if (busy) return;
                            const args = { letterTemplate: templateId, version: versionId };
                            const action = variable.id
                                ? variables.update({ ...args, item: variable.id })
                                : variables.store(args);
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
                                            description: 'Pertanyaan disimpan. Validasi ulang DOCX.',
                                        });
                                    },
                                })
                            )
                                error();
                        }}
                    >
                        <FormActions
                            cancelUrl=""
                            compact
                            onCancel={onClose}
                            processing={form.processing}
                            disabled={disabled}
                            fullWidth={false}
                        />
                        <FieldError>
                            {errors.configuration ||
                                errors.item ||
                                errors['item.id'] ||
                                errors['item.employee_attributes']}
                        </FieldError>
                    </form>
                </TableCell>
            </TableRow>
            {[4, 5, 6, 7, 8].includes(value.type) && (
                <TableRow>
                    <TableCell colSpan={6} className="bg-muted/30 p-4 whitespace-normal">
                        <ConfigurationVariableDetails
                            variable={value}
                            options={options}
                            disabled={busy}
                            errors={errors}
                            onChange={(item) => {
                                form.setData('item', item);
                                form.clearErrors();
                            }}
                        />
                        {employee && (
                            <p className="mt-3 text-sm text-muted-foreground">
                                Simpan variabel terlebih dahulu untuk mengatur teks alternatif dan format setiap atribut
                                Pegawai.
                            </p>
                        )}
                    </TableCell>
                </TableRow>
            )}
        </Fragment>
    );
}
