import type { SubmitEventHandler } from 'react';
import { FormCard } from '@/components/form-card';
import { FormActions } from '@/components/form-actions';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { gooeyToast } from '@/components/ui/goey-toaster';
import { Input } from '@/components/ui/input';
import { useValibotForm } from '@/hooks/use-valibot-form';
import ln from '@/routes/ln';
import { LetterNumberSchema, UpdateLetterNumberSchema } from '@/schemas/letter-number';
import type { LetterNumberColumns } from './data-table/column';

export function LetterNumberForm({ letterNumber }: { letterNumber?: LetterNumberColumns }) {
    const { form, submit, bind } = useValibotForm(
        {
            schema: LetterNumberSchema.schema,
            validate: (input: unknown) =>
                (letterNumber ? UpdateLetterNumberSchema : LetterNumberSchema).validate(input),
        },
        {
            classification_name: letterNumber?.name ?? '',
            classification_code: letterNumber?.code ?? '',
            page_number: String(letterNumber?.page_number ?? 1),
            sequence_number: String(letterNumber?.sequence_number ?? 1),
        },
    );
    const showError = () => gooeyToast.error('Galat', { description: 'Terdapat data yang tidak valid.' });
    const handleSubmit: SubmitEventHandler<HTMLFormElement> = (event) => {
        event.preventDefault();
        if (form.processing) return;
        const action = letterNumber ? ln.update(letterNumber.id) : ln.store();
        const submitted = submit(action.method, action.url, {
            onError: showError,
            onSuccess: () => {
                gooeyToast.success('Berhasil', {
                    description: (
                        <p>
                            <strong>{form.data.classification_name}</strong> berhasil{' '}
                            {letterNumber ? 'diubah' : 'ditambahkan'}.
                        </p>
                    ),
                });
            },
        });
        if (!submitted) showError();
    };

    return (
        <FormCard
            title={letterNumber ? 'Ubah Nomor' : 'Tambah Nomor'}
            description={
                letterNumber ? 'Silakan ubah nama dan kode klasifikasi surat.' : 'Silakan isi formulir dibawah ini.'
            }
            cancelUrl={ln.index().url}
            disabled={form.processing}
        >
            <form noValidate onSubmit={handleSubmit}>
                <FieldGroup>
                    <Field data-invalid={!!form.errors.classification_name} data-disabled={form.processing}>
                        <FieldLabel htmlFor="classification_name">Nama Klasifikasi Surat</FieldLabel>
                        <Input
                            type="text"
                            placeholder="Surat Keluar"
                            autoFocus
                            disabled={form.processing}
                            {...bind('classification_name')}
                        />
                        <FieldDescription>Cth: Surat Keluar, Surat Tugas dan sebagainya.</FieldDescription>
                        {form.errors.classification_name && <FieldError>{form.errors.classification_name}</FieldError>}
                    </Field>
                    <Field data-invalid={!!form.errors.classification_code} data-disabled={form.processing}>
                        <FieldLabel htmlFor="classification_code">Kode Klasifikasi Surat</FieldLabel>
                        <Input
                            type="text"
                            inputMode="numeric"
                            placeholder="445"
                            minLength={3}
                            maxLength={3}
                            disabled={form.processing}
                            {...bind('classification_code')}
                        />
                        {form.errors.classification_code && <FieldError>{form.errors.classification_code}</FieldError>}
                    </Field>
                    {(['page_number', 'sequence_number'] as const).map((name) => (
                        <Field
                            key={name}
                            data-invalid={!!form.errors[name]}
                            data-disabled={!!letterNumber || form.processing}
                        >
                            <FieldLabel htmlFor={name}>
                                {name === 'page_number' ? 'Nomor Halaman' : 'Nomor Urut'}
                            </FieldLabel>
                            <Input
                                type="number"
                                min={1}
                                max={letterNumber ? undefined : 99}
                                disabled={!!letterNumber || form.processing}
                                {...bind(name)}
                            />
                            {form.errors[name] && <FieldError>{form.errors[name]}</FieldError>}
                        </Field>
                    ))}
                    <FormActions cancelUrl={ln.index().url} processing={form.processing} />
                </FieldGroup>
            </form>
        </FormCard>
    );
}
