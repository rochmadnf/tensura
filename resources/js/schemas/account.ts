import { defineRequest } from '@/lib/request';
import * as v from 'valibot';

export const AccountProfileSchema = defineRequest(
    v.object({
        name: v.pipe(v.string(), v.trim(), v.nonEmpty(), v.minLength(3), v.maxLength(100)),
        username: v.pipe(
            v.string(),
            v.trim(),
            v.nonEmpty(),
            v.minLength(6),
            v.maxLength(100),
            v.regex(/^[a-zA-Z_][a-zA-Z0-9_]*$/, ':attribute tidak sesuai format.'),
        ),
        email: v.pipe(v.string(), v.trim(), v.toLowerCase(), v.nonEmpty(), v.email(), v.maxLength(100)),
    }),
    { name: 'Nama', username: 'Nama Pengguna', email: 'Email' },
);

export const AccountPasswordSchema = defineRequest(
    v.pipe(
        v.object({
            current_password: v.pipe(v.string(), v.nonEmpty()),
            password: v.pipe(v.string(), v.nonEmpty(), v.minLength(8)),
            password_confirmation: v.pipe(v.string(), v.nonEmpty()),
        }),
        v.forward(
            v.partialCheck(
                [['password'], ['password_confirmation']],
                (input) => input.password === input.password_confirmation,
                'Konfirmasi Kata Sandi Baru tidak cocok.',
            ),
            ['password_confirmation'],
        ),
        v.forward(
            v.partialCheck(
                [['current_password'], ['password']],
                (input) => input.current_password !== input.password,
                'Kata Sandi Baru harus berbeda dari kata sandi saat ini.',
            ),
            ['password'],
        ),
    ),
    {
        current_password: 'Kata Sandi Saat Ini',
        password: 'Kata Sandi Baru',
        password_confirmation: 'Konfirmasi Kata Sandi Baru',
    },
);

export const AccountAvatarSchema = defineRequest(
    v.object({
        avatar: v.pipe(
            v.nullable(
                v.pipe(v.file(), v.mimeType(['image/jpeg', 'image/png', 'image/webp']), v.maxSize(2 * 1024 * 1024)),
            ),
            v.check((file) => file !== null, ':attribute wajib dipilih.'),
        ),
    }),
    { avatar: 'Foto Profil' },
);
