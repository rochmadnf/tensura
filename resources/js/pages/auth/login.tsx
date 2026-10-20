import { Button } from '@/components/ui/button';
import { Field, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { useValibotForm } from '@/hooks/use-valibot-form';
import login from '@/routes/login';
import { LoginSchema } from '@/schemas/login';
import { Head } from '@inertiajs/react';
import { SubmitEventHandler } from 'react';

export default function Login() {
    const { form, submit, bind } = useValibotForm(LoginSchema, {
        username: '',
        password: '',
    });

    const handleLogin: SubmitEventHandler<HTMLFormElement> = (e) => {
        e.preventDefault();

        submit('post', login.post().url);
    };

    return (
        <>
            <Head title="Masuk">
                <meta name="description" content="Halaman masuk pengguna." />
            </Head>

            <form onSubmit={handleLogin} noValidate>
                <FieldGroup>
                    <Field data-invalid={!!form.errors.username}>
                        <FieldLabel htmlFor="username">Nama Pengguna</FieldLabel>
                        <Input
                            type="text"
                            placeholder="rochmadnf"
                            autoFocus
                            autoComplete="username"
                            tabIndex={1}
                            {...bind('username')}
                        />
                        {form.errors.username && <FieldError>{form.errors.username}</FieldError>}
                    </Field>
                    <Field data-invalid={!!form.errors.password}>
                        <FieldLabel htmlFor="password">Kata Sandi</FieldLabel>
                        <Input
                            type="password"
                            tabIndex={2}
                            placeholder="********"
                            autoComplete="current-password"
                            {...bind('password')}
                        />
                        {form.errors.password && <FieldError>{form.errors.password}</FieldError>}
                    </Field>
                    <Field>
                        <Button type="submit" tabIndex={3} disabled={form.processing}>
                            Login
                        </Button>
                    </Field>
                </FieldGroup>
            </form>
        </>
    );
}

Login.layout = {
    title: 'Masuk',
    description: 'Masukkan kredensial Anda untuk masuk.',
};
