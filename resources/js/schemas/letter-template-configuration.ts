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
    string('Nama Variabel harus berupa teks.'),
    trim(),
    nonEmpty('Nama Variabel wajib diisi.'),
    maxLength(100, 'Nama Variabel maksimal 100 karakter.'),
    regex(/^[a-z][a-z0-9_]*$/, 'Gunakan huruf kecil, angka, dan garis bawah; awali dengan huruf.'),
);
const employeeAttributeSchema = object({
    source_attribute: string(),
    default_text: nullable(pipe(string(), maxLength(10000, 'Teks alternatif maksimal 10.000 karakter.'))),
    date_format: nullable(pipe(string(), maxLength(100, 'Format tanggal maksimal 100 karakter.'))),
});
export type EmployeeAttributeSetting = InferInput<typeof employeeAttributeSchema>;
export type EmployeeAttributeOption = { id: string; label: string; suffix: string; type: string };
const variableSchema = object({
    id: nullable(string()),
    name: placeholder,
    label: pipe(
        string(),
        trim(),
        nonEmpty('Pertanyaan wajib diisi.'),
        maxLength(100, 'Pertanyaan maksimal 100 karakter.'),
    ),
    type: number(),
    is_required: boolean(),
    option_source: nullable(number()),
    reference_group: nullable(string()),
    is_multiple: boolean(),
    default_text: nullable(pipe(string(), maxLength(10000, 'Teks alternatif maksimal 10.000 karakter.'))),
    employee_attributes: array(employeeAttributeSchema),
    columns: array(
        object({
            id: nullable(string()),
            source_attribute: pipe(string(), nonEmpty('Atribut kolom wajib dipilih.')),
            placeholder,
            date_format: nullable(string()),
        }),
    ),
});
const slotSchema = object({
    id: nullable(string()),
    candidates: array(string()),
    employee_attributes: array(employeeAttributeSchema),
});

export const letterTemplateConfigurationSchema = defineRequest(
    object({ variables: array(variableSchema), signer_slots: array(slotSchema) }),
);
export type ConfigurationVariable = InferInput<typeof variableSchema>;
export type ConfigurationSlot = InferInput<typeof slotSchema>;
export type ConfigurationOptions = {
    employeeAttributes: EmployeeAttributeOption[];
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

export const letterTemplateVariableItemSchema = defineRequest(object({ item: variableSchema }));
export const letterTemplateSlotItemSchema = defineRequest(object({ item: slotSchema }));
export const letterTemplateAttributeItemSchema = defineRequest(
    object({
        default_text: nullable(pipe(string(), maxLength(10000, 'Teks alternatif maksimal 10.000 karakter.'))),
        date_format: nullable(pipe(string(), maxLength(100, 'Format tanggal maksimal 100 karakter.'))),
    }),
);
