import { Button } from '@/components/ui/button';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { gooeyToast } from '@/components/ui/goey-toaster';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useValibotForm } from '@/hooks/use-valibot-form';
import jt from '@/routes/jt';
import { createJobTitleSchema, type JobTitleGroupOption } from '@/schemas/job-title';
import { LoaderIcon, SaveIcon, XIcon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Head, Link } from '@inertiajs/react';
import { cn } from 'cn';
import type { SubmitEventHandler } from 'react';
import type { JobTitleColumns } from './components/data-table/column';

const pageName = 'Ubah Jabatan';

interface JobTitleEditProps {
    groups: JobTitleGroupOption[];
    resource: { data: JobTitleColumns };
}

export default function JobTitleEdit({ groups, resource }: JobTitleEditProps) {
    const jobTitle = resource.data;
    const { form, submit, bind } = useValibotForm(createJobTitleSchema(groups), {
        name: jobTitle.name,
        group: jobTitle.group.id === null ? '' : String(jobTitle.group.id),
    });
    const groupItems = groups.map((group) => ({ value: String(group.id), label: group.label }));

    const handleSubmit: SubmitEventHandler<HTMLFormElement> = (event) => {
        event.preventDefault();

        const action = jt.update(jobTitle.id);
        const submitted = submit(action.method, action.url, {
            onError: () => {
                gooeyToast.error('Galat', { description: 'Terdapat data yang tidak valid.' });
            },
            onSuccess: () => {
                gooeyToast.success('Berhasil', {
                    description: (
                        <p>
                            <strong>{form.data.name}</strong> berhasil diubah.
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
        <>
            <Head title={pageName}>
                <meta name="description" content="Halaman untuk mengubah data jabatan" />
            </Head>
            <div className="container mx-auto">
                <Card className="w-full">
                    <CardHeader>
                        <CardTitle>{pageName}</CardTitle>
                        <CardDescription>Silakan ubah nama dan kelompok jabatan.</CardDescription>
                        <CardAction>
                            <Button
                                type="button"
                                nativeButton={false}
                                variant="secondary"
                                size="icon"
                                disabled={form.processing}
                                aria-label="Tutup"
                                render={<Link href={jt.index().url} />}
                            >
                                <HugeiconsIcon icon={XIcon} strokeWidth={2} />
                            </Button>
                        </CardAction>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-6">
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
                                <Field data-invalid={!!form.errors.group}>
                                    <FieldLabel htmlFor="group">Kelompok Jabatan</FieldLabel>
                                    <Select
                                        name="group"
                                        items={groupItems}
                                        value={form.data.group || null}
                                        disabled={form.processing}
                                        onValueChange={(value) => {
                                            form.setData('group', value ?? '');
                                            form.clearErrors('group');
                                        }}
                                    >
                                        <SelectTrigger id="group" className="w-full" aria-invalid={!!form.errors.group}>
                                            <SelectValue placeholder="Pilih kelompok jabatan" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                {groupItems.map((group) => (
                                                    <SelectItem key={group.value} value={group.value}>
                                                        {group.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                    {form.errors.group && <FieldError>{form.errors.group}</FieldError>}
                                </Field>
                                <Field orientation="responsive">
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
                                        render={<Link href={jt.index().url} />}
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

JobTitleEdit.layout = { pageName };
