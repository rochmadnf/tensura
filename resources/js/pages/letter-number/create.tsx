import { Button } from '@/components/ui/button';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { useValibotForm } from '@/hooks/use-valibot-form';
import ln from '@/routes/ln';
import { LetterNumberSchema } from '@/schemas/letter-number';
import { LoaderIcon, SaveIcon, XIcon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Head, Link } from '@inertiajs/react';
import { cn } from 'cn';
import { gooeyToast } from '@/components/ui/goey-toaster';
import { SubmitEventHandler } from 'react';

const pageName = 'Tambah Penomoran Surat';

export default function LetterNumberCreate() {
    const { form, submit, bind } = useValibotForm(LetterNumberSchema, {
        classification_name: '',
        classification_code: '',
        page_number: '1',
        sequence_number: '1',
    });

    const handleSubmit: SubmitEventHandler<HTMLFormElement> = (e) => {
        e.preventDefault();

        submit(ln.store().method, ln.store().url, {
            onError: () => {
                gooeyToast.error('Galat', {
                    description: 'Terdapat data yang tidak valid.',
                });
            },
            onSuccess: () => {
                gooeyToast.success('Berhasil', {
                    description: (
                        <p>
                            <strong>{form.data.classification_name}</strong> berhasil ditambahkan.
                        </p>
                    ),
                });
            },
        });
    };

    return (
        <>
            <Head title={pageName}>
                <meta name="description" content="Halaman untuk menambahkan penomoran surat baru" />
            </Head>

            <div className="container mx-auto space-y-4">
                <div className="flex items-center"></div>
                <Card className="w-full">
                    <CardHeader>
                        <CardTitle>Tambah Nomor</CardTitle>
                        <CardDescription>Silakan isi formulir dibawah ini.</CardDescription>
                        <CardAction>
                            <Button
                                nativeButton={false}
                                type="button"
                                variant={'secondary'}
                                render={<Link href={ln.index().url} />}
                            >
                                <HugeiconsIcon icon={XIcon} strokeWidth={2} />
                            </Button>
                        </CardAction>
                    </CardHeader>
                    <CardContent>
                        <form noValidate onSubmit={handleSubmit}>
                            <FieldGroup>
                                <Field data-invalid={!!form.errors.classification_name}>
                                    <FieldLabel>Nama Klasifikasi Surat</FieldLabel>
                                    <Input
                                        type="text"
                                        placeholder="Surat Keluar"
                                        autoFocus
                                        tabIndex={1}
                                        {...bind('classification_name')}
                                    />
                                    <FieldDescription className="text-xs text-blue-500 italic">
                                        Cth: Surat Keluar, Surat Tugas dan sebagainya.
                                    </FieldDescription>
                                    {form.errors.classification_name && (
                                        <FieldError>{form.errors.classification_name}</FieldError>
                                    )}
                                </Field>
                                <Field data-invalid={!!form.errors.classification_code}>
                                    <FieldLabel>Kode Klasifikasi Surat</FieldLabel>
                                    <Input
                                        type="text"
                                        placeholder="445"
                                        tabIndex={2}
                                        maxLength={3}
                                        minLength={3}
                                        {...bind('classification_code')}
                                    />
                                    {form.errors.classification_code && (
                                        <FieldError>{form.errors.classification_code}</FieldError>
                                    )}
                                </Field>
                                <Field data-invalid={!!form.errors.page_number}>
                                    <FieldLabel>Nomor Halaman</FieldLabel>
                                    <Input
                                        type="number"
                                        placeholder="1"
                                        min={1}
                                        max={99}
                                        tabIndex={3}
                                        {...bind('page_number')}
                                    />
                                    {form.errors.page_number && <FieldError>{form.errors.page_number}</FieldError>}
                                </Field>
                                <Field data-invalid={!!form.errors.sequence_number}>
                                    <FieldLabel>Nomor Urut</FieldLabel>
                                    <Input
                                        type="number"
                                        placeholder="1"
                                        min={1}
                                        max={99}
                                        tabIndex={4}
                                        {...bind('sequence_number')}
                                    />
                                    {form.errors.sequence_number && (
                                        <FieldError>{form.errors.sequence_number}</FieldError>
                                    )}
                                </Field>
                                <Field orientation={'responsive'}>
                                    <Button className={'w-full'} type="submit" tabIndex={5} disabled={form.processing}>
                                        <HugeiconsIcon
                                            icon={form.processing ? LoaderIcon : SaveIcon}
                                            strokeWidth={2}
                                            className={cn(form.processing ? 'animate-spin' : null)}
                                        />
                                        Simpan
                                    </Button>
                                    <Button
                                        type="button"
                                        tabIndex={6}
                                        disabled={form.processing}
                                        className={'w-full'}
                                        nativeButton={false}
                                        variant={'destructive'}
                                        render={<Link href={ln.index().url} />}
                                    >
                                        <HugeiconsIcon icon={XIcon} strokeWidth={2} />
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

LetterNumberCreate.layout = {
    pageName: pageName,
};
