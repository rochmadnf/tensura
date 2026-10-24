import { defineRequest } from '@/lib/request';
import { object, pipe, string, trim, nonEmpty, minLength, InferInput, regex } from 'valibot';

export const LoginSchema = defineRequest(
    object({
        username: pipe(
            string(),
            trim(),
            nonEmpty(),
            minLength(6),
            regex(/^[a-zA-Z_][a-zA-Z0-9_]*$/, ':attribute tidak sesuai format.'),
        ),
        password: pipe(string(), nonEmpty(), minLength(8)),
    }),
    {
        username: 'Nama Pengguna',
        password: 'Katasandi',
    },
);

export type LoginFormProps = InferInput<typeof LoginSchema.schema>;
