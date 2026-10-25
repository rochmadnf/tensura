import { Add01Icon, Delete02Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { EmployeeAttributeFields } from './employee-attribute-fields';
import { TemplateEditAction } from './edit-action';
import { type SubmitEventHandler } from 'react';
import { FormCard } from '@/components/form-card';
import { FormActions } from '@/components/form-actions';
import { OptionSelect } from '@/components/option-select';
import { Button } from '@/components/ui/button';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
    FieldLegend,
    FieldSet,
} from '@/components/ui/field';
import { gooeyToast } from '@/components/ui/goey-toaster';
import { useValibotForm } from '@/hooks/use-valibot-form';
import lt from '@/routes/lt';
import { update } from '@/routes/lt/configuration';
import {
    letterTemplateConfigurationSchema,
    type ConfigurationOptions,
    type ConfigurationVariable,
    type LetterTemplateConfiguration,
} from '@/schemas/letter-template-configuration';
import { ConfigurationVariableFields } from './configuration-variable';
import { CopyPlaceholder } from './copy-placeholder';

export function LetterTemplateConfigurationForm({
    templateId,
    configuration,
    options,
    disabled: externalDisabled = false,
    onProcessingChange,
    editing = false,
    onEdit,
    onClose,
}: {
    templateId: string;
    configuration: LetterTemplateConfiguration;
    options: ConfigurationOptions;
    disabled?: boolean;
    onProcessingChange?: (processing: boolean) => void;
    editing?: boolean;
    onEdit: () => void;
    onClose: () => void;
}) {
    const { form, submit } = useValibotForm(letterTemplateConfigurationSchema, {
        variables: configuration.variables,
        signer_slots:
            configuration.signer_slots.length === 0 && !configuration.has_configuration && configuration.editable
                ? [{ id: null, candidates: [], employee_attributes: [] }]
                : configuration.signer_slots.map(({ id, candidates, employee_attributes }) => ({
                      id,
                      candidates,
                      employee_attributes,
                  })),
    });
    const disabled = externalDisabled || form.processing || !configuration.editable;
    const errors: Record<string, string | undefined> = form.errors;
    const showError = () =>
        gooeyToast.error('Galat', { description: 'Periksa isian konfigurasi dan kandidat penandatangan.' });
    const handleSubmit: SubmitEventHandler<HTMLFormElement> = (event) => {
        event.preventDefault();
        if (disabled) return;
        const action = update({ letterTemplate: templateId, version: configuration.id });
        const submitted = submit(action.method, action.url, {
            preserveScroll: true,
            onStart: () => onProcessingChange?.(true),
            onFinish: () => onProcessingChange?.(false),
            onError: showError,
            onSuccess: (page) => {
                onClose();
                const saved = page.props.configuration as { data: LetterTemplateConfiguration };
                form.setData({
                    variables: saved.data.variables,
                    signer_slots: saved.data.signer_slots.map(({ id, candidates, employee_attributes }) => ({
                        id,
                        candidates,
                        employee_attributes,
                    })),
                });
                gooeyToast.success('Berhasil', {
                    description: 'Konfigurasi disimpan. Periksa kembali DOCX sebelum publikasi.',
                });
            },
        });
        if (!submitted) showError();
    };
    function updateVariable(index: number, variable: ConfigurationVariable) {
        form.setData(
            'variables',
            form.data.variables.map((value, position) => (position === index ? variable : value)),
        );
        form.clearErrors();
    }
    function moveVariable(index: number, direction: number) {
        const variables = [...form.data.variables];
        [variables[index + direction], variables[index]] = [variables[index], variables[index + direction]];
        form.setData('variables', variables);
        form.clearErrors();
    }
    if (!editing) {
        return (
            <FormCard
                title="Konfigurasi Variabel dan Penandatangan"
                description="Konfigurasi tersimpan. Salin token yang dibutuhkan ke DOCX."
                action={
                    configuration.editable ? (
                        <TemplateEditAction label="konfigurasi" disabled={externalDisabled} onClick={onEdit} />
                    ) : undefined
                }
            >
                <FieldGroup>
                    {configuration.warnings.map((warning) => (
                        <FieldError key={`${warning.slot}-${warning.employee}`}>
                            {warning.name} pada slot {warning.slot} sudah tidak aktif. Ganti atau hapus kandidat sebelum
                            publikasi.
                        </FieldError>
                    ))}
                    {configuration.variables.length === 0 && (
                        <p className="text-sm text-muted-foreground">Belum ada variabel.</p>
                    )}
                    {configuration.variables.map((variable) => (
                        <FieldGroup key={variable.id}>
                            <p className="text-sm font-medium">
                                {variable.label} · {options.types.find((type) => type.id === variable.type)?.label} ·{' '}
                                {variable.is_required ? 'Wajib' : 'Opsional'}
                            </p>
                            {variable.is_multiple ? (
                                variable.columns.map((column) => (
                                    <div key={column.id}>
                                        <CopyPlaceholder name={column.placeholder} />
                                        <p className="text-sm text-muted-foreground">
                                            {
                                                options.columns[variable.option_source ?? 0]?.find(
                                                    (item) => item.id === column.source_attribute,
                                                )?.label
                                            }
                                            {column.date_format ? ` · ${column.date_format}` : ''}
                                        </p>
                                    </div>
                                ))
                            ) : (
                                <CopyPlaceholder name={variable.name} />
                            )}
                            {variable.default_text && <p className="text-sm">Fallback: {variable.default_text}</p>}
                            {variable.option_source === 2 && !variable.is_multiple && (
                                <EmployeeAttributeFields
                                    prefix={variable.name}
                                    path={`saved-variable-${variable.id}`}
                                    attributes={options.employeeAttributes}
                                    settings={variable.employee_attributes}
                                    disabled
                                    errors={{}}
                                    readOnly
                                />
                            )}
                        </FieldGroup>
                    ))}
                    {configuration.signer_slots.length === 0 && (
                        <p className="text-sm text-muted-foreground">Tidak ada slot penandatangan aktif.</p>
                    )}
                    {configuration.signer_slots.map((slot, index) => (
                        <FieldGroup key={slot.id}>
                            <p className="text-sm font-medium">
                                Kandidat Slot {index + 1}:{' '}
                                {slot.candidates
                                    .map(
                                        (id) =>
                                            options.employees.find((employee) => employee.id === id)?.label ??
                                            configuration.warnings.find((warning) => warning.employee === id)?.name ??
                                            'Tidak tersedia',
                                    )
                                    .join(', ')}
                            </p>
                            <CopyPlaceholder name={slot.placeholder} />
                            <EmployeeAttributeFields
                                prefix={slot.placeholder}
                                path={`saved-slot-${slot.id}`}
                                attributes={options.employeeAttributes}
                                settings={slot.employee_attributes}
                                disabled
                                errors={{}}
                                readOnly
                            />
                        </FieldGroup>
                    ))}
                </FieldGroup>
            </FormCard>
        );
    }
    return (
        <FormCard
            title="Konfigurasi Variabel dan Penandatangan"
            description="Atur isian surat dan kandidat untuk setiap slot, lalu simpan konfigurasi. Gunakan placeholder pada DOCX."
            disabled={externalDisabled || form.processing}
        >
            <form noValidate onSubmit={handleSubmit}>
                <FieldGroup>
                    {!configuration.editable && (
                        <p className="text-sm text-muted-foreground">Konfigurasi versi ini hanya dapat dibaca.</p>
                    )}
                    {configuration.warnings.length > 0 && (
                        <FieldError>
                            <ul>
                                {configuration.warnings.map((warning) => (
                                    <li key={`${warning.slot}-${warning.employee}`}>
                                        {warning.name} pada slot {warning.slot} sudah tidak aktif. Ganti atau hapus
                                        kandidat dari konfigurasi sebelum publikasi.
                                    </li>
                                ))}
                            </ul>
                        </FieldError>
                    )}
                    <FieldError>{errors.configuration}</FieldError>
                    <FieldSet>
                        <FieldLegend>Variabel</FieldLegend>
                        <FieldDescription>
                            Urutan variabel di bawah menjadi urutan isian formulir surat.
                        </FieldDescription>
                        <FieldError>{errors.variables}</FieldError>
                        <FieldGroup>
                            {form.data.variables.length === 0 && (
                                <p className="text-sm text-muted-foreground">Belum ada variabel.</p>
                            )}
                            {form.data.variables.map((variable, index) => (
                                <ConfigurationVariableFields
                                    key={variable.id ?? `variable-${index}`}
                                    variable={variable}
                                    index={index}
                                    count={form.data.variables.length}
                                    options={options}
                                    disabled={disabled}
                                    errors={errors}
                                    onChange={(value) => updateVariable(index, value)}
                                    onMove={(direction) => moveVariable(index, direction)}
                                    onRemove={() => {
                                        form.setData(
                                            'variables',
                                            form.data.variables.filter((_, position) => position !== index),
                                        );
                                        form.clearErrors();
                                    }}
                                />
                            ))}
                            {configuration.editable && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={disabled}
                                    onClick={() =>
                                        form.setData('variables', [
                                            ...form.data.variables,
                                            {
                                                id: null,
                                                name: '',
                                                label: '',
                                                type: 1,
                                                is_required: false,
                                                option_source: null,
                                                reference_group: null,
                                                is_multiple: false,
                                                columns: [],
                                                default_text: null,
                                                employee_attributes: [],
                                            },
                                        ])
                                    }
                                >
                                    <HugeiconsIcon icon={Add01Icon} data-icon="inline-start" aria-hidden="true" />
                                    Tambah Variabel
                                </Button>
                            )}
                        </FieldGroup>
                    </FieldSet>
                    <FieldSet>
                        <FieldLegend>Penandatangan</FieldLegend>
                        <FieldDescription>
                            Setiap slot aktif wajib memiliki kandidat pegawai aktif. Hapus slot jika penandatangan tidak
                            diperlukan. Token penandatangan bersifat opsional. Jika jumlah slot berubah, sesuaikan
                            placeholder di DOCX.
                        </FieldDescription>
                        <FieldError>{errors.signer_slots}</FieldError>
                        <FieldGroup>
                            {form.data.signer_slots.length === 0 && (
                                <p className="text-sm text-muted-foreground">Tidak ada slot penandatangan aktif.</p>
                            )}
                            {form.data.signer_slots.map((slot, index) => {
                                const path = `signer_slots.${index}.candidates`;
                                const candidates = slot.candidates;
                                const changeCandidates = (selected: string[]) => {
                                    form.setData(
                                        'signer_slots',
                                        form.data.signer_slots.map((value, position) =>
                                            position === index ? { ...value, candidates: selected } : value,
                                        ),
                                    );
                                    form.clearErrors();
                                };
                                const inactive = configuration.warnings.filter(
                                    (warning) =>
                                        warning.slot ===
                                            configuration.signer_slots.findIndex(
                                                (savedSlot) => savedSlot.id === slot.id,
                                            ) +
                                                1 && candidates.includes(warning.employee),
                                );
                                const candidateErrors = Object.entries(errors)
                                    .filter(([key]) => key === path || key.startsWith(path + '.'))
                                    .map(([, message]) => message);
                                return (
                                    <Field key={slot.id ?? `slot-${index}`} data-invalid={candidateErrors.length > 0}>
                                        <FieldError>{errors[`signer_slots.${index}.id`]}</FieldError>
                                        <FieldLabel htmlFor={path}>Kandidat Slot {index + 1}</FieldLabel>
                                        <OptionSelect
                                            multiple
                                            forceCombobox
                                            id={path}
                                            name={path}
                                            items={options.employees.map((employee) => ({
                                                value: employee.id,
                                                label: employee.label,
                                            }))}
                                            value={candidates}
                                            onValueChange={changeCandidates}
                                            disabled={disabled}
                                            aria-invalid={candidateErrors.length > 0}
                                            placeholder="Pilih pegawai aktif"
                                            resetLabel="Reset kandidat slot"
                                        />
                                        {inactive.map((warning) => (
                                            <div
                                                key={warning.employee}
                                                className="flex flex-wrap items-center gap-2 text-sm"
                                            >
                                                <span>{warning.name} — Tidak aktif</span>
                                                <Button
                                                    type="button"
                                                    variant="destructive"
                                                    size="sm"
                                                    disabled={disabled}
                                                    onClick={() =>
                                                        changeCandidates(
                                                            candidates.filter((id) => id !== warning.employee),
                                                        )
                                                    }
                                                >
                                                    <HugeiconsIcon
                                                        icon={Delete02Icon}
                                                        data-icon="inline-start"
                                                        aria-hidden="true"
                                                    />
                                                    Hapus Kandidat
                                                </Button>
                                            </div>
                                        ))}
                                        {candidateErrors.map((message, position) => (
                                            <FieldError key={position}>{message}</FieldError>
                                        ))}
                                        <CopyPlaceholder
                                            name={
                                                form.data.signer_slots.length === 1
                                                    ? 'penandatangan'
                                                    : `penandatangan_${index + 1}`
                                            }
                                        />
                                        <EmployeeAttributeFields
                                            prefix={
                                                form.data.signer_slots.length === 1
                                                    ? 'penandatangan'
                                                    : `penandatangan_${index + 1}`
                                            }
                                            path={`signer_slots.${index}.employee_attributes`}
                                            attributes={options.employeeAttributes}
                                            settings={slot.employee_attributes}
                                            disabled={disabled}
                                            errors={errors}
                                            onChange={(employee_attributes) =>
                                                form.setData(
                                                    'signer_slots',
                                                    form.data.signer_slots.map((value, position) =>
                                                        position === index ? { ...value, employee_attributes } : value,
                                                    ),
                                                )
                                            }
                                        />
                                        {configuration.editable && (
                                            <Button
                                                type="button"
                                                variant="destructive"
                                                disabled={disabled}
                                                onClick={() => {
                                                    form.setData(
                                                        'signer_slots',
                                                        form.data.signer_slots.filter(
                                                            (_, position) => position !== index,
                                                        ),
                                                    );
                                                    form.clearErrors();
                                                }}
                                            >
                                                <HugeiconsIcon
                                                    icon={Delete02Icon}
                                                    data-icon="inline-start"
                                                    aria-hidden="true"
                                                />
                                                Hapus Slot {index + 1}
                                            </Button>
                                        )}
                                    </Field>
                                );
                            })}
                            {configuration.editable && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={disabled}
                                    onClick={() =>
                                        form.setData('signer_slots', [
                                            ...form.data.signer_slots,
                                            { id: null, candidates: [], employee_attributes: [] },
                                        ])
                                    }
                                >
                                    <HugeiconsIcon icon={Add01Icon} data-icon="inline-start" aria-hidden="true" />
                                    Tambah Slot
                                </Button>
                            )}
                        </FieldGroup>
                    </FieldSet>
                    {configuration.editable && (
                        <FormActions
                            cancelUrl={lt.index().url}
                            onCancel={onClose}
                            processing={form.processing}
                            fullWidth={false}
                        />
                    )}
                </FieldGroup>
            </form>
        </FormCard>
    );
}
