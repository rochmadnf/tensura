import { FormCard } from '@/components/form-card';
import { FormActions } from '@/components/form-actions';
import { DatePickerInput } from '@/components/date-picker-input';
import { OptionSelect } from '@/components/option-select';
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
import { Input } from '@/components/ui/input';
import { useValibotForm } from '@/hooks/use-valibot-form';
import empl from '@/routes/empl';
import jt from '@/routes/jt';
import {
    createEmployeeSchema,
    employeeFieldLabels,
    type EmployeeFormOptions,
    type EmployeeResource,
} from '@/schemas/employee';
import { Link } from '@inertiajs/react';
import type { ComponentProps, SubmitEventHandler } from 'react';

interface EmployeeFormProps extends EmployeeFormOptions {
    employee?: EmployeeResource;
}
type EmployeeField = keyof typeof employeeFieldLabels;
const requiredFields: EmployeeField[] = [
    'nik',
    'name',
    'gender',
    'job_title_id',
    'join_date',
    'contract_status',
    'status',
];
const dateFields: EmployeeField[] = ['join_date', 'str_published_date', 'sip_published_date', 'sip_expired_date'];
const inputProps: Partial<Record<EmployeeField, ComponentProps<typeof Input>>> = {
    nik: { maxLength: 16, inputMode: 'numeric', autoFocus: true },
    nip: { maxLength: 18, inputMode: 'numeric' },
    name: { maxLength: 100 },
    front_title: { maxLength: 50 },
    back_title: { maxLength: 50 },
    rank: { maxLength: 50 },
    grade: { maxLength: 50 },
    email: { maxLength: 100, type: 'email' },
    str_number: { maxLength: 16 },
    sip_number: { maxLength: 30 },
};
const sections: { title: string; description?: string; fields: EmployeeField[] }[] = [
    { title: 'Data Diri', fields: ['nik', 'nip', 'name', 'gender', 'front_title', 'back_title', 'email'] },
    { title: 'Data Kepegawaian', fields: ['job_title_id', 'rank', 'grade', 'join_date', 'contract_status', 'status'] },
    {
        title: 'STR',
        description: 'Opsional. Isi seluruh data STR atau kosongkan semuanya.',
        fields: ['str_number', 'str_status', 'str_published_date'],
    },
    {
        title: 'SIP',
        description: 'Opsional. Isi seluruh data SIP atau kosongkan semuanya.',
        fields: ['sip_number', 'sip_published_date', 'sip_expired_date'],
    },
];

export function EmployeeForm({ employee, ...options }: EmployeeFormProps) {
    const pageName = employee ? 'Ubah Pegawai' : 'Tambah Pegawai';
    const { form, submit, bind } = useValibotForm(createEmployeeSchema(options), {
        nik: employee?.nik ?? '',
        nip: employee?.nip ?? '',
        name: employee?.name.raw ?? '',
        gender: employee?.gender.id == null ? '' : String(employee.gender.id),
        front_title: employee?.name.first ?? '',
        back_title: employee?.name.last ?? '',
        job_title_id: employee?.job_title.id ?? '',
        rank: employee?.rank ?? '',
        grade: employee?.grade ?? '',
        join_date: employee?.join_date_input ?? '',
        email: employee?.email ?? '',
        contract_status: employee?.contract_status.id == null ? '' : String(employee.contract_status.id),
        status: employee?.status.id == null ? '' : String(employee.status.id),
        str_number: employee?.str?.number ?? '',
        str_status: employee?.str?.status.id == null ? '' : String(employee.str.status.id),
        str_published_date: employee?.str?.published_date_input ?? '',
        sip_number: employee?.sip?.number ?? '',
        sip_published_date: employee?.sip?.published_date_input ?? '',
        sip_expired_date: employee?.sip?.expired_date_input ?? '',
    });
    const selections: Partial<Record<EmployeeField, { id: number | string; label: string }[]>> = {
        gender: options.genders,
        job_title_id: options.jobTitles,
        contract_status: options.contractStatuses,
        status: options.statuses,
        str_status: options.strStatuses,
    };
    function changeValue(name: EmployeeField, value: string) {
        form.setData(name, value);
        form.clearErrors(name);
    }
    const handleSubmit: SubmitEventHandler<HTMLFormElement> = (event) => {
        event.preventDefault();
        const action = employee ? empl.update(employee.id) : empl.store();
        const submitted = submit(action.method, action.url, {
            onError: () => gooeyToast.error('Galat', { description: 'Terdapat data yang tidak valid.' }),
            onSuccess: () =>
                gooeyToast.success('Berhasil', {
                    description: `${form.data.name} berhasil ${employee ? 'diubah' : 'ditambahkan'}.`,
                }),
        });
        if (!submitted) {
            gooeyToast.error('Galat', { description: 'Terdapat data yang tidak valid.' });
        }
    };
    return (
        <FormCard
            title={pageName}
            description="Isi data identitas dan kepegawaian. Kolom bertanda * wajib diisi."
            cancelUrl={empl.index().url}
            disabled={form.processing}
        >
            <form noValidate onSubmit={handleSubmit}>
                <FieldGroup>
                    {sections.map((section) => (
                        <FieldSet key={section.title}>
                            <FieldLegend>{section.title}</FieldLegend>
                            {section.description && <FieldDescription>{section.description}</FieldDescription>}
                            <FieldGroup className="grid gap-6 md:grid-cols-2">
                                {section.fields.map((name) => {
                                    const items = selections[name];
                                    const errorId = `${name}-error`;
                                    const ariaProps = {
                                        'aria-invalid': !!form.errors[name],
                                        'aria-describedby': form.errors[name] ? errorId : undefined,
                                    };
                                    return (
                                        <Field
                                            key={name}
                                            data-invalid={!!form.errors[name]}
                                            data-disabled={form.processing}
                                        >
                                            <FieldLabel htmlFor={name}>
                                                {employeeFieldLabels[name]}
                                                {requiredFields.includes(name) && (
                                                    <span className="text-destructive">*</span>
                                                )}
                                            </FieldLabel>
                                            {items ? (
                                                <OptionSelect
                                                    forceCombobox={name === 'str_status'}
                                                    id={name}
                                                    name={name}
                                                    remote={
                                                        name === 'job_title_id' ? { source: 'job-titles' } : undefined
                                                    }
                                                    items={items.map((item) => ({
                                                        value: String(item.id),
                                                        label: item.label,
                                                    }))}
                                                    value={form.data[name]}
                                                    placeholder={`Pilih ${employeeFieldLabels[name].toLowerCase()}`}
                                                    resetLabel={`Reset ${employeeFieldLabels[name].toLowerCase()}`}
                                                    disabled={form.processing}
                                                    {...ariaProps}
                                                    onValueChange={(value) => changeValue(name, value)}
                                                />
                                            ) : dateFields.includes(name) ? (
                                                <DatePickerInput
                                                    id={name}
                                                    name={name}
                                                    value={form.data[name]}
                                                    onValueChange={(value) => changeValue(name, value)}
                                                    disabled={form.processing}
                                                    max={name === 'sip_expired_date' ? undefined : options.today}
                                                    {...ariaProps}
                                                />
                                            ) : (
                                                <Input
                                                    {...inputProps[name]}
                                                    required={requiredFields.includes(name)}
                                                    disabled={form.processing}
                                                    {...bind(name)}
                                                    {...ariaProps}
                                                />
                                            )}
                                            {name === 'job_title_id' && options.jobTitles.length === 0 && (
                                                <FieldDescription>
                                                    Belum ada jabatan.{' '}
                                                    <Link href={jt.create().url} className="text-primary underline">
                                                        Tambah Jabatan
                                                    </Link>{' '}
                                                    terlebih dahulu.
                                                </FieldDescription>
                                            )}
                                            {form.errors[name] && (
                                                <FieldError id={errorId}>{form.errors[name]}</FieldError>
                                            )}
                                        </Field>
                                    );
                                })}
                            </FieldGroup>
                        </FieldSet>
                    ))}
                    <FormActions cancelUrl={empl.index().url} processing={form.processing} />
                </FieldGroup>
            </form>
        </FormCard>
    );
}
