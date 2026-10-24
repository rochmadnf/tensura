import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { gooeyToast } from '@/components/ui/goey-toaster';
import { Input } from '@/components/ui/input';
import { useValibotForm } from '@/hooks/use-valibot-form';
import { getInitialName } from '@/lib/utils';
import { store, destroy } from '@/routes/account/avatar';
import { update as updatePassword } from '@/routes/account/password';
import { update as updateProfile } from '@/routes/account/profile';
import { AccountAvatarSchema, AccountPasswordSchema, AccountProfileSchema } from '@/schemas/account';
import type { User } from '@/types/auth';
import { LoaderIcon, SaveIcon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState, type SubmitEventHandler } from 'react';

const pageName = 'Akun';

function showError() {
    gooeyToast.error('Galat', { description: 'Terdapat data yang tidak valid. Silakan periksa kembali.' });
}

function SaveButton({ processing, children = 'Simpan' }: { processing: boolean; children?: React.ReactNode }) {
    return (
        <Button type="submit" disabled={processing}>
            <HugeiconsIcon
                icon={processing ? LoaderIcon : SaveIcon}
                strokeWidth={2}
                data-icon="inline-start"
                className={processing ? 'animate-spin' : undefined}
            />
            {children}
        </Button>
    );
}

function ProfileForm({ user }: { user: User }) {
    const { form, bind, submit } = useValibotForm(AccountProfileSchema, {
        name: user.name,
        username: user.username,
        email: user.email,
    });
    const handleSubmit: SubmitEventHandler<HTMLFormElement> = (event) => {
        event.preventDefault();
        const route = updateProfile();
        if (
            !submit(route.method, route.url, {
                preserveScroll: true,
                onError: showError,
                onSuccess: (page) => {
                    const updated = page.props.auth.user;
                    form.setData({ name: updated.name, username: updated.username, email: updated.email });
                    form.setDefaults({ name: updated.name, username: updated.username, email: updated.email });
                    gooeyToast.success('Berhasil', { description: 'Profil berhasil diperbarui.' });
                },
            })
        ) {
            showError();
        }
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Profil</CardTitle>
                <CardDescription>Ubah nama, username, dan email akun Anda.</CardDescription>
            </CardHeader>
            <CardContent>
                <form noValidate onSubmit={handleSubmit}>
                    <FieldGroup>
                        <Field data-invalid={!!form.errors.name}>
                            <FieldLabel htmlFor="name">Nama</FieldLabel>
                            <Input {...bind('name')} autoComplete="name" disabled={form.processing} />
                            {form.errors.name && <FieldError>{form.errors.name}</FieldError>}
                        </Field>
                        <Field data-invalid={!!form.errors.username}>
                            <FieldLabel htmlFor="username">Nama Pengguna</FieldLabel>
                            <Input {...bind('username')} autoComplete="username" disabled={form.processing} />
                            <FieldDescription>
                                6–100 karakter. Gunakan username baru saat login berikutnya.
                            </FieldDescription>
                            {form.errors.username && <FieldError>{form.errors.username}</FieldError>}
                        </Field>
                        <Field data-invalid={!!form.errors.email}>
                            <FieldLabel htmlFor="email">Email</FieldLabel>
                            <Input {...bind('email')} type="email" autoComplete="email" disabled={form.processing} />
                            {form.errors.email && <FieldError>{form.errors.email}</FieldError>}
                        </Field>
                        <Field orientation="responsive">
                            <SaveButton processing={form.processing}>Simpan profil</SaveButton>
                        </Field>
                    </FieldGroup>
                </form>
            </CardContent>
        </Card>
    );
}

function AvatarForm({ user, hasAvatar }: { user: User; hasAvatar: boolean }) {
    const { form, submit } = useValibotForm(AccountAvatarSchema, { avatar: null });
    const deletion = useForm({});
    const input = useRef<HTMLInputElement>(null);
    const [preview, setPreview] = useState<string>();
    const processing = form.processing || deletion.processing;

    useEffect(() => {
        if (!form.data.avatar) {
            setPreview(undefined);
            return;
        }
        const url = URL.createObjectURL(form.data.avatar);
        setPreview(url);
        return () => URL.revokeObjectURL(url);
    }, [form.data.avatar]);

    function clearSelection() {
        form.reset();
        form.clearErrors();
        if (input.current) {
            input.current.value = '';
        }
    }

    const handleSubmit: SubmitEventHandler<HTMLFormElement> = (event) => {
        event.preventDefault();
        const route = store();
        if (
            !submit(route.method, route.url, {
                forceFormData: true,
                preserveScroll: true,
                onError: showError,
                onSuccess: () => {
                    clearSelection();
                    gooeyToast.success('Berhasil', { description: 'Foto profil berhasil diperbarui.' });
                },
            })
        ) {
            showError();
        }
    };

    function removeAvatar() {
        deletion.delete(destroy().url, {
            preserveScroll: true,
            onError: showError,
            onSuccess: () => {
                clearSelection();
                gooeyToast.success('Berhasil', { description: 'Foto profil berhasil dihapus.' });
            },
        });
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>Foto Profil</CardTitle>
                <CardDescription>Pilih foto yang akan tampil di akun Anda.</CardDescription>
            </CardHeader>
            <CardContent>
                <form noValidate onSubmit={handleSubmit}>
                    <FieldGroup>
                        <Avatar className="size-20">
                            <AvatarImage src={preview ?? user.avatar} alt={user.name} className="object-cover" />
                            <AvatarFallback>{getInitialName(user.name)}</AvatarFallback>
                        </Avatar>
                        <Field data-invalid={!!form.errors.avatar}>
                            <FieldLabel htmlFor="avatar">Foto Profil</FieldLabel>
                            <Input
                                ref={input}
                                id="avatar"
                                name="avatar"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                aria-invalid={!!form.errors.avatar}
                                aria-describedby="avatar-help"
                                disabled={processing}
                                onChange={(event) => {
                                    form.setData('avatar', event.target.files?.[0] ?? null);
                                    form.clearErrors('avatar');
                                }}
                            />
                            <FieldDescription id="avatar-help">
                                JPG, JPEG, PNG, atau WebP. Maksimal 2 MB dan 4096 × 4096 piksel.
                            </FieldDescription>
                            {form.errors.avatar && <FieldError>{form.errors.avatar}</FieldError>}
                        </Field>
                        <Field orientation="responsive">
                            <SaveButton processing={processing}>Simpan foto</SaveButton>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={processing || !hasAvatar}
                                onClick={removeAvatar}
                            >
                                {deletion.processing && (
                                    <HugeiconsIcon
                                        icon={LoaderIcon}
                                        data-icon="inline-start"
                                        className="animate-spin"
                                    />
                                )}
                                Hapus foto
                            </Button>
                        </Field>
                    </FieldGroup>
                </form>
            </CardContent>
        </Card>
    );
}

function PasswordForm() {
    const { form, bind, submit } = useValibotForm(AccountPasswordSchema, {
        current_password: '',
        password: '',
        password_confirmation: '',
    });
    const handleSubmit: SubmitEventHandler<HTMLFormElement> = (event) => {
        event.preventDefault();
        const route = updatePassword();
        if (
            !submit(route.method, route.url, {
                preserveScroll: true,
                onError: showError,
                onSuccess: () => {
                    form.reset();
                    gooeyToast.success('Berhasil', { description: 'Kata sandi berhasil diperbarui.' });
                },
            })
        ) {
            showError();
        }
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Kata Sandi</CardTitle>
                <CardDescription>Masukkan kata sandi saat ini untuk menggantinya.</CardDescription>
            </CardHeader>
            <CardContent>
                <form noValidate onSubmit={handleSubmit}>
                    <FieldGroup>
                        <Field data-invalid={!!form.errors.current_password}>
                            <FieldLabel htmlFor="current_password">Kata Sandi Saat Ini</FieldLabel>
                            <Input
                                {...bind('current_password')}
                                type="password"
                                autoComplete="current-password"
                                disabled={form.processing}
                            />
                            {form.errors.current_password && <FieldError>{form.errors.current_password}</FieldError>}
                        </Field>
                        <Field data-invalid={!!form.errors.password}>
                            <FieldLabel htmlFor="password">Kata Sandi Baru</FieldLabel>
                            <Input
                                {...bind('password')}
                                type="password"
                                autoComplete="new-password"
                                disabled={form.processing}
                            />
                            <FieldDescription>
                                Minimal 8 karakter dan berbeda dari kata sandi saat ini.
                            </FieldDescription>
                            {form.errors.password && <FieldError>{form.errors.password}</FieldError>}
                        </Field>
                        <Field data-invalid={!!form.errors.password_confirmation}>
                            <FieldLabel htmlFor="password_confirmation">Konfirmasi Kata Sandi Baru</FieldLabel>
                            <Input
                                {...bind('password_confirmation')}
                                type="password"
                                autoComplete="new-password"
                                disabled={form.processing}
                            />
                            {form.errors.password_confirmation && (
                                <FieldError>{form.errors.password_confirmation}</FieldError>
                            )}
                        </Field>
                        <Field orientation="responsive">
                            <SaveButton processing={form.processing}>Simpan kata sandi</SaveButton>
                        </Field>
                    </FieldGroup>
                </form>
            </CardContent>
        </Card>
    );
}

export default function AccountEdit({ hasAvatar }: { hasAvatar: boolean }) {
    const { auth } = usePage().props;
    return (
        <>
            <Head title={pageName}>
                <meta name="description" content="Kelola profil, foto, dan kata sandi akun Anda." />
            </Head>
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6">
                <ProfileForm user={auth.user} />
                <AvatarForm user={auth.user} hasAvatar={hasAvatar} />
                <PasswordForm />
            </div>
        </>
    );
}

AccountEdit.layout = { pageName };
