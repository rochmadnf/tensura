import { object, pipe, string, trim, nonEmpty, minLength, InferInput, regex } from 'valibot';

export const LoginSchema = object({
    username: pipe(
        string(),
        trim(),
        nonEmpty('Nama pengguna wajib diisi.'),
        minLength(5, 'Nama pengguna minimal 5 karakter.'),
        regex(/^[a-zA-Z_][a-zA-Z0-9_]*$/, 'Nama pengguna tidak sesuai format.'),
    ),
    password: pipe(string(), nonEmpty('Kata sandi wajib diisi.'), minLength(8, 'Kata sandi minimal 8 karakter.')),
});

export type LoginFormProps = InferInput<typeof LoginSchema>;
