import { defineRequest } from '@/lib/request';
import {
    object,
    pipe,
    string,
    trim,
    nonEmpty,
    minLength,
    length,
    regex,
    minValue,
    number,
    integer,
    toNumber,
    InferInput,
} from 'valibot';

export const LetterNumberSchema = defineRequest(
    object({
        classification_name: pipe(string(), trim(), nonEmpty(), minLength(3)),
        classification_code: pipe(
            string(),
            trim(),
            nonEmpty(),
            length(3),
            regex(/^\d{3}$/, ':attribute berupa 3 digit angka'),
        ),
        page_number: pipe(string(), toNumber(), number(), integer(), minValue(1)),
        sequence_number: pipe(string(), toNumber(), number(), integer(), minValue(1)),
    }),
    {
        classification_name: 'Nama Klasifikasi Surat',
        classification_code: 'Kode Klasifikasi Surat',
        page_number: 'Nomor Halaman',
        sequence_number: 'Nomor Urut',
    },
);

export type LetterNumberProps = InferInput<typeof LetterNumberSchema.schema>;
