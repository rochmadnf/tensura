import { Button } from '@/components/ui/button';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { gooeyToast } from '@/components/ui/goey-toaster';
import { Input } from '@/components/ui/input';
import { useValibotForm } from '@/hooks/use-valibot-form';
import ln from '@/routes/ln';
import { UpdateLetterNumberSchema } from '@/schemas/letter-number';
import { LoaderIcon, SaveIcon, XIcon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Head, Link } from '@inertiajs/react';
import { cn } from 'cn';
import type { SubmitEventHandler } from 'react';
import type { LetterNumberColumns } from './components/data-table/column';

const pageName = 'Ubah Penomoran Surat';

export default function LetterNumberEdit({ resource }: { resource: { data: LetterNumberColumns } }) {
    const letterNumber = resource.data;
    const { form, submit, bind } = useValibotForm(UpdateLetterNumberSchema, {
        classification_name: letterNumber.name,
        classification_code: letterNumber.code,
    });

    const handleSubmit: SubmitEventHandler<HTMLFormElement> = (e) => {
        e.preventDefault();

        const update = ln.update(letterNumber.id);
        submit(update.method, update.url, {
            onError: () => {
                gooeyToast.error('Galat', { description: 'Terdapat data yang tidak valid.' });
            },
            onSuccess: () => {
                gooeyToast.success('Berhasil', {
                    description: (
                        <p>
                            <strong>{form.data.classification_name}</strong> berhasil diubah.
                        </p>
                    ),
                });
            },
        });
    };

    return (
        <>
            <Head title={pageName}>
                <meta name="description" content="Halaman untuk mengubah penomoran surat" />
            </Head>
            <div className="container mx-auto">
                <Card className="w-full">
                    <CardHeader>
                        <CardTitle>Ubah Nomor</CardTitle>
                        <CardDescription>Silakan ubah nama dan kode klasifikasi surat.</CardDescription>
                        <CardAction>
                            <Button
                                nativeButton={false}
                                variant="secondary"
                                size="icon"
                                disabled={form.processing}
                                aria-label="Tutup"
                                render={<Link href={ln.index().url} />}
                            >
                                <HugeiconsIcon icon={XIcon} strokeWidth={2} />
                            </Button>
                        </CardAction>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-6">
                        <form noValidate onSubmit={handleSubmit}>
                            <FieldGroup>
                                <Field data-invalid={!!form.errors.classification_name}>
                                    <FieldLabel htmlFor="classification_name">Nama Klasifikasi Surat</FieldLabel>
                                    <Input
                                        type="text"
                                        placeholder="Surat Keluar"
                                        autoFocus
                                        {...bind('classification_name')}
                                    />
                                    <FieldDescription>Cth: Surat Keluar, Surat Tugas dan sebagainya.</FieldDescription>
                                    {form.errors.classification_name && (
                                        <FieldError>{form.errors.classification_name}</FieldError>
                                    )}
                                </Field>
                                <Field data-invalid={!!form.errors.classification_code}>
                                    <FieldLabel htmlFor="classification_code">Kode Klasifikasi Surat</FieldLabel>
                                    <Input
                                        type="text"
                                        inputMode="numeric"
                                        placeholder="445"
                                        maxLength={3}
                                        minLength={3}
                                        {...bind('classification_code')}
                                    />
                                    {form.errors.classification_code && (
                                        <FieldError>{form.errors.classification_code}</FieldError>
                                    )}
                                </Field>
                                <Field>
                                    <FieldLabel htmlFor="page_number">Nomor Halaman</FieldLabel>
                                    <Input disabled id="page_number" type="number" value={letterNumber.page_number} />
                                </Field>
                                <Field>
                                    <FieldLabel htmlFor="sequence_number">Nomor Urut</FieldLabel>
                                    <Input
                                        id="sequence_number"
                                        type="number"
                                        value={letterNumber.sequence_number}
                                        disabled
                                    />
                                </Field>
                                <Field orientation="responsive">
                                    <Button className="w-full" type="submit" disabled={form.processing}>
                                        <HugeiconsIcon
                                            icon={form.processing ? LoaderIcon : SaveIcon}
                                            strokeWidth={2}
                                            data-icon="inline-start"
                                            className={cn(form.processing ? 'animate-spin' : null)}
                                        />
                                        Simpan
                                    </Button>
                                    <Button
                                        disabled={form.processing}
                                        className="w-full"
                                        nativeButton={false}
                                        variant="destructive"
                                        render={<Link href={ln.index().url} />}
                                    >
                                        <HugeiconsIcon icon={XIcon} strokeWidth={2} data-icon="inline-start" />
                                        Batal
                                    </Button>
                                </Field>
                            </FieldGroup>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

LetterNumberEdit.layout = { pageName };
