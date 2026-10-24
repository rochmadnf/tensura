import { OptionSelect } from '@/components/option-select';
import { FormCard } from '@/components/form-card';
import { FormActions } from '@/components/form-actions';
import { Field, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { gooeyToast } from '@/components/ui/goey-toaster';
import { Input } from '@/components/ui/input';
import { useValibotForm } from '@/hooks/use-valibot-form';
import jt from '@/routes/jt';
import { createJobTitleSchema, type JobTitleGroupOption } from '@/schemas/job-title';
import type { SubmitEventHandler } from 'react';
import type { JobTitleColumns } from './data-table/column';

export function JobTitleForm({ groups, jobTitle }: { groups: JobTitleGroupOption[]; jobTitle?: JobTitleColumns }) {
    const pageName = jobTitle ? 'Ubah Jabatan' : 'Tambah Jabatan';
    const { form, submit, bind } = useValibotForm(createJobTitleSchema(groups), {
        name: jobTitle?.name ?? '',
        group: jobTitle?.group.id == null ? '' : String(jobTitle.group.id),
    });
    const groupItems = groups.map((group) => ({ value: String(group.id), label: group.label }));

    const handleSubmit: SubmitEventHandler<HTMLFormElement> = (event) => {
        event.preventDefault();

        if (form.processing) return;
        const action = jobTitle ? jt.update(jobTitle.id) : jt.store();
        const submitted = submit(action.method, action.url, {
            onError: () => {
                gooeyToast.error('Galat', { description: 'Terdapat data yang tidak valid.' });
            },
            onSuccess: () => {
                gooeyToast.success('Berhasil', {
                    description: (
                        <p>
                            <strong>{form.data.name}</strong> berhasil {jobTitle ? 'diubah' : 'ditambahkan'}.
                        </p>
                    ),
                });
            },
        });

        if (!submitted) {
            gooeyToast.error('Galat', { description: 'Terdapat data yang tidak valid.' });
        }
    };

    return (
        <FormCard
            title={pageName}
            description={
                jobTitle ? 'Silakan ubah nama dan kelompok jabatan.' : 'Silakan isi nama dan kelompok jabatan.'
            }
            cancelUrl={jt.index().url}
            disabled={form.processing}
        >
            <form noValidate onSubmit={handleSubmit}>
                <FieldGroup>
                    <Field data-invalid={!!form.errors.name}>
                        <FieldLabel htmlFor="name">Nama Jabatan</FieldLabel>
                        <Input
                            type="text"
                            placeholder="Dokter Ahli Pertama"
                            minLength={3}
                            maxLength={100}
                            autoFocus
                            disabled={form.processing}
                            {...bind('name')}
                        />
                        {form.errors.name && <FieldError>{form.errors.name}</FieldError>}
                    </Field>
                    <Field data-invalid={!!form.errors.group} data-disabled={form.processing}>
                        <FieldLabel htmlFor="group">Kelompok Jabatan</FieldLabel>
                        <OptionSelect
                            id="group"
                            name="group"
                            items={groupItems}
                            value={form.data.group}
                            placeholder="Pilih kelompok jabatan"
                            resetLabel="Reset kelompok jabatan"
                            aria-invalid={!!form.errors.group}
                            disabled={form.processing}
                            onValueChange={(value) => {
                                form.setData('group', value);
                                form.clearErrors('group');
                            }}
                        />
                        {form.errors.group && <FieldError>{form.errors.group}</FieldError>}
                    </Field>
                    <FormActions cancelUrl={jt.index().url} processing={form.processing} />
                </FieldGroup>
            </form>
        </FormCard>
    );
}
