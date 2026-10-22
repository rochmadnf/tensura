import { defineRequest } from '@/lib/request';
import { object, pipe, string, trim, nonEmpty, minLength, InferInput } from 'valibot';

export const InputSearchSchema = defineRequest(
    object({
        keyword: pipe(string(), trim(), nonEmpty(), minLength(2)),
    }),
    {
        keyword: 'Kata kunci',
    },
);

export type InputSearchProps = InferInput<typeof InputSearchSchema.schema>;
