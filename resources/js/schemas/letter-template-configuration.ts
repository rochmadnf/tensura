import { defineRequest } from '@/lib/request';
import {
    array,
    boolean,
    maxLength,
    nonEmpty,
    nullable,
    number,
    object,
    pipe,
    regex,
    string,
    trim,
    type InferInput,
} from 'valibot';

const placeholder = pipe(
    string('Nama placeholder harus berupa teks.'),
    trim(),
    nonEmpty('Nama placeholder wajib diisi.'),
    maxLength(100, 'Nama placeholder maksimal 100 karakter.'),
    regex(/^[a-z][a-z0-9_]*$/, 'Gunakan huruf kecil, angka, dan garis bawah; awali dengan huruf.'),
);
const variableSchema = object({
    id: nullable(string()),
    name: placeholder,
    label: pipe(
        string(),
        trim(),
        nonEmpty('Label variabel wajib diisi.'),
        maxLength(100, 'Label variabel maksimal 100 karakter.'),
    ),
    type: number(),
    is_required: boolean(),
    option_source: nullable(number()),
    reference_group: nullable(string()),
    is_multiple: boolean(),
    columns: array(
        object({
            id: nullable(string()),
            source_attribute: pipe(string(), nonEmpty('Atribut kolom wajib dipilih.')),
            placeholder,
        }),
    ),
});
const slotSchema = object({ id: nullable(string()), candidates: array(string()) });

export const letterTemplateConfigurationSchema = defineRequest(
    object({ variables: array(variableSchema), signer_slots: array(slotSchema) }),
);
export type ConfigurationVariable = InferInput<typeof variableSchema>;
export type ConfigurationSlot = InferInput<typeof slotSchema>;
export type ConfigurationOptions = {
    types: { id: number; label: string }[];
    sources: { id: number; label: string }[];
    columns: Record<number, { id: string; label: string }[]>;
    referenceGroups: { id: string; label: string }[];
    employees: { id: string; label: string }[];
};
export type LetterTemplateConfiguration = {
    id: string;
    editable: boolean;
    has_configuration: boolean;
    variables: ConfigurationVariable[];
    signer_slots: (ConfigurationSlot & { placeholder: string })[];
    warnings: { slot: number; employee: string; name: string }[];
};
