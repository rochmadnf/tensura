import { object, pipe, string, trim, nonEmpty, minLength, InferInput } from 'valibot';

export const InputSearchSchema = object({
    keyword: pipe(
        string(),
        trim(),
        nonEmpty('Kata kunci wajib diisi.'),
        minLength(2, 'Kata kunci minimal 2 karakter.'),
    ),
});

export type InputSearchProps = InferInput<typeof InputSearchSchema>;
