import { OptionSelect } from '@/components/option-select';
import { Button } from '@/components/ui/button';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
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
import { LoaderIcon, SaveIcon, XIcon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Link } from '@inertiajs/react';
import { cn } from 'cn';
import type { SubmitEventHandler } from 'react';

interface EmployeeFormProps extends EmployeeFormOptions {
    employee?: EmployeeResource;
}

const textFields = [
    { name: 'nik', maxLength: 16, inputMode: 'numeric', required: true },
    { name: 'nip', maxLength: 18, inputMode: 'numeric' },
    { name: 'name', maxLength: 100, required: true },
    { name: 'front_title', maxLength: 50 },
    { name: 'back_title', maxLength: 50 },
    { name: 'rank', maxLength: 50 },
    { name: 'grade', maxLength: 50 },
    { name: 'email', maxLength: 100, type: 'email' },
    { name: 'join_date', type: 'date', required: true },
] as const;

export function EmployeeForm({ employee, ...options }: EmployeeFormProps) {
    const pageName = employee ? 'Ubah Pegawai' : 'Tambah Pegawai';
    const { form, submit, bind } = useValibotForm(createEmployeeSchema(options), {
        nik: employee?.nik ?? '',
        nip: employee?.nip ?? '',
        name: employee?.name.raw ?? '',
        front_title: employee?.name.first ?? '',
        back_title: employee?.name.last ?? '',
        job_title_id: employee?.job_title.id ?? '',
        rank: employee?.rank ?? '',
        grade: employee?.grade ?? '',
        join_date: employee?.join_date_input ?? '',
        email: employee?.email ?? '',
        contract_status: employee?.contract_status.id == null ? '' : String(employee.contract_status.id),
        status: employee?.status.id == null ? '' : String(employee.status.id),
    });
    const selections = [
        { name: 'job_title_id', items: options.jobTitles },
        { name: 'contract_status', items: options.contractStatuses },
        { name: 'status', items: options.statuses },
    ] as const;

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
        <Card className="w-full">
            <CardHeader>
                <CardTitle>{pageName}</CardTitle>
                <CardDescription>Isi data identitas dan kepegawaian. Kolom bertanda * wajib diisi.</CardDescription>
                <CardAction>
                    <Button
                        type="button"
                        nativeButton={false}
                        variant="secondary"
                        size="icon"
                        disabled={form.processing}
                        aria-label="Tutup"
                        render={<Link href={empl.index().url} />}
                    >
                        <HugeiconsIcon icon={XIcon} strokeWidth={2} />
                    </Button>
                </CardAction>
            </CardHeader>
            <CardContent>
                <form noValidate onSubmit={handleSubmit}>
                    <FieldGroup className="grid gap-6 md:grid-cols-2">
                        {textFields.map(({ name, ...inputProps }) => (
                            <Field key={name} data-invalid={!!form.errors[name]} data-disabled={form.processing}>
                                <FieldLabel htmlFor={name}>
                                    {employeeFieldLabels[name]}
                                    {'required' in inputProps && inputProps.required ? (
                                        <span className="text-red-500">*</span>
                                    ) : (
                                        ''
                                    )}
                                </FieldLabel>
                                <Input
                                    {...inputProps}
                                    autoFocus={name === 'nik'}
                                    max={name === 'join_date' ? options.today : undefined}
                                    disabled={form.processing}
                                    {...bind(name)}
                                />
                                {form.errors[name] && <FieldError>{form.errors[name]}</FieldError>}
                            </Field>
                        ))}
                        {selections.map(({ name, items }) => (
                            <Field key={name} data-invalid={!!form.errors[name]} data-disabled={form.processing}>
                                <FieldLabel htmlFor={name}>
                                    {employeeFieldLabels[name]} <span className="text-red-500">*</span>
                                </FieldLabel>
                                <OptionSelect
                                    id={name}
                                    name={name}
                                    items={items.map((item) => ({ value: String(item.id), label: item.label }))}
                                    value={form.data[name]}
                                    placeholder={`Pilih ${employeeFieldLabels[name].toLowerCase()}`}
                                    resetLabel={`Reset ${employeeFieldLabels[name].toLowerCase()}`}
                                    aria-invalid={!!form.errors[name]}
                                    disabled={form.processing}
                                    onValueChange={(value) => {
                                        form.setData(name, value);
                                        form.clearErrors(name);
                                    }}
                                />
                                {name === 'job_title_id' && options.jobTitles.length === 0 && (
                                    <FieldDescription>
                                        Belum ada jabatan.{' '}
                                        <Link href={jt.create().url} className="text-primary underline">
                                            Tambah Jabatan
                                        </Link>{' '}
                                        terlebih dahulu.
                                    </FieldDescription>
                                )}
                                {form.errors[name] && <FieldError>{form.errors[name]}</FieldError>}
                            </Field>
                        ))}
                        <Field orientation="responsive" className="md:col-span-2">
                            <Button className="w-full" type="submit" disabled={form.processing}>
                                <HugeiconsIcon
                                    icon={form.processing ? LoaderIcon : SaveIcon}
                                    strokeWidth={2}
                                    data-icon="inline-start"
                                    className={cn(form.processing && 'animate-spin')}
                                />
                                Simpan
                            </Button>
                            <Button
                                type="button"
                                disabled={form.processing}
                                className="w-full"
                                nativeButton={false}
                                variant="destructive"
                                render={<Link href={empl.index().url} />}
                            >
                                <HugeiconsIcon icon={XIcon} strokeWidth={2} data-icon="inline-start" />
                                Batal
                            </Button>
                        </Field>
                    </FieldGroup>
                </form>
            </CardContent>
        </Card>
    );
}
