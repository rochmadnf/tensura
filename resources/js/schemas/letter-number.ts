import { defineRequest } from '@/lib/request';
import { object, pipe, string, trim, nonEmpty, minLength, length, regex, InferInput, maxLength, pick } from 'valibot';

export const LetterNumberSchema = defineRequest(
    object({
        classification_name: pipe(string(), trim(), nonEmpty(), minLength(3), maxLength(100)),
        classification_code: pipe(
            string(),
            trim(),
            nonEmpty(),
            length(3),
            regex(/^\d{3}$/, ':attribute berupa 3 digit angka'),
        ),
    }),
    {
        classification_name: 'Nama Klasifikasi Surat',
        classification_code: 'Kode Klasifikasi Surat',
    },
);

export type LetterNumberProps = InferInput<typeof LetterNumberSchema.schema>;

export const UpdateLetterNumberSchema = defineRequest(
    pick(LetterNumberSchema.schema, ['classification_name', 'classification_code']),
    {
        classification_name: 'Nama Klasifikasi Surat',
        classification_code: 'Kode Klasifikasi Surat',
    },
);
