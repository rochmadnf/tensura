import { Button } from '@/components/ui/button';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { gooeyToast } from '@/components/ui/goey-toaster';
import { Input } from '@/components/ui/input';
import { useValibotForm } from '@/hooks/use-valibot-form';
import ref from '@/routes/ref';
import { referenceGroupSchema, type ReferenceGroupResource } from '@/schemas/reference';
import { LoaderIcon, SaveIcon, XIcon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Link } from '@inertiajs/react';
import { cn } from 'cn';
import type { SubmitEventHandler } from 'react';

interface ReferenceGroupFormProps {
    group?: ReferenceGroupResource;
    disabled?: boolean;
    onProcessingChange?: (processing: boolean) => void;
}
export function ReferenceGroupForm({ group, disabled = false, onProcessingChange }: ReferenceGroupFormProps) {
    const { form, submit, bind } = useValibotForm(referenceGroupSchema, {
        name: group?.name ?? '',
        description: group?.description ?? '',
    });
    const busy = disabled || form.processing;
    const showError = () =>
        gooeyToast.error('Galat', { description: 'Terdapat data yang tidak valid atau grup gagal disimpan.' });
    const handleSubmit: SubmitEventHandler<HTMLFormElement> = (event) => {
        event.preventDefault();
        if (busy) return;
        const action = group ? ref.update(group.id) : ref.store();
        const submitted = submit(action.method, action.url, {
            preserveScroll: true,
            onStart: () => onProcessingChange?.(true),
            onFinish: () => onProcessingChange?.(false),
            onError: showError,
            onNetworkError: () => {
                showError();
                return false;
            },
            onHttpException: () => {
                showError();
                return false;
            },
            onSuccess: () =>
                gooeyToast.success('Berhasil', {
                    description: group
                        ? `Grup ${form.data.name.trim()} berhasil diubah.`
                        : `Grup ${form.data.name.trim()} berhasil ditambahkan. Silakan tambahkan item.`,
                }),
        });
        if (!submitted) showError();
    };
    return (
        <Card>
            <CardHeader>
                <CardTitle>{group ? 'Informasi Grup' : 'Tambah Daftar Referensi'}</CardTitle>
                <CardDescription>Isi nama grup dan deskripsi. Item ditambahkan setelah grup disimpan.</CardDescription>
                <CardAction>
                    <Button
                        type="button"
                        nativeButton={false}
                        variant="secondary"
                        size="icon"
                        disabled={busy}
                        aria-label="Tutup"
                        render={<Link href={ref.index().url} />}
                    >
                        <HugeiconsIcon icon={XIcon} />
                    </Button>
                </CardAction>
            </CardHeader>
            <CardContent>
                <form noValidate onSubmit={handleSubmit}>
                    <FieldGroup>
                        <Field data-invalid={!!form.errors.name} data-disabled={busy}>
                            <FieldLabel htmlFor="name">Nama Grup</FieldLabel>
                            <Input
                                {...bind('name')}
                                minLength={3}
                                maxLength={100}
                                disabled={busy}
                                autoFocus={!group}
                                placeholder="Contoh: Golongan Darah"
                            />
                            {form.errors.name && <FieldError>{form.errors.name}</FieldError>}
                        </Field>
                        <Field data-invalid={!!form.errors.description} data-disabled={busy}>
                            <FieldLabel htmlFor="description">Deskripsi (opsional)</FieldLabel>
                            <Input
                                {...bind('description')}
                                maxLength={255}
                                disabled={busy}
                                placeholder="Keterangan singkat grup referensi"
                            />
                            {form.errors.description && <FieldError>{form.errors.description}</FieldError>}
                        </Field>
                        <Field orientation="responsive">
                            <Button type="submit" disabled={busy}>
                                <HugeiconsIcon
                                    icon={form.processing ? LoaderIcon : SaveIcon}
                                    data-icon="inline-start"
                                    className={cn(form.processing && 'animate-spin')}
                                />
                                Simpan
                            </Button>
                            <Button
                                type="button"
                                nativeButton={false}
                                variant="destructive"
                                disabled={busy}
                                render={<Link href={ref.index().url} />}
                            >
                                <HugeiconsIcon icon={XIcon} data-icon="inline-start" />
                                Batal
                            </Button>
                        </Field>
                    </FieldGroup>
                </form>
            </CardContent>
        </Card>
    );
}
