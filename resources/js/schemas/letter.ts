import {
    array,
    check,
    forward,
    integer,
    maxLength,
    maxValue,
    minValue,
    nonEmpty,
    nullable,
    object,
    optional,
    pipe,
    record,
    string,
    toNumber,
    trim,
    union,
    uuid,
    type GenericSchema,
    type InferInput,
    type InferOutput,
} from 'valibot';
import { defineRequest } from '@/lib/request';

export type LetterConfiguration = {
    version: string;
    number: number;
    date_format: string;
    classification: string;
    variables: {
        id: string;
        name: string;
        label: string;
        type: number;
        required: boolean;
        multiple: boolean;
        options: { value: string; label: string }[];
    }[];
    signers: { id: string; label: string; options: { value: string; label: string }[] }[];
};
export type LetterDraft = {
    kind?: number;
    subject?: string;
    reason?: string | null;
    base_version?: string | null;
    id: string;
    revision: number;
    template_version: string;
    version_number: number;
    letter_date: string;
    numbering_mode: number;
    number_year: number;
    number_month: number;
    page_number: number | null;
    sequence_number: number | null;
    values: Record<string, string | string[] | null>;
    signers: Record<string, string | null>;
    complete: boolean;
    resolved: Record<string, string>;
    updated_by: string;
    current_template: boolean;
    can_adjust: boolean;
    can_refresh: boolean;
    reasons: string[];
};
export type LetterResource = {
    official?: import('@/schemas/letter-preview').LetterOfficial;
    id: string;
    subject: string;
    number: string | null;
    template: { id: string; name: string; active: boolean };
    letter_date: string;
    status: number;
    status_label: string;
    creator: string;
    creator_id: string;
    updated_at: string;
    revision: number | null;
    editable: boolean;
    draft?: LetterDraft;
    configuration?: LetterConfiguration;
};
export type LetterChange = { label: string; before: string | null; after: string | null };
export type LetterChangePreview = {
    revision: number;
    target_version: number;
    changes: LetterChange[];
    change_token: string;
    complete: boolean;
};
export type LetterAudit = {
    id: string;
    actor: string;
    event: string;
    description: string;
    revision: number;
    occurred_at: string;
    changes: LetterChange[];
    reason?: string | null;
    related_letter?: string | null;
};

const numeric = (max: number) => pipe(string(), nonEmpty(), toNumber(), integer(), minValue(1), maxValue(max));
export function createLetterSchema(configuration: LetterConfiguration) {
    const base = object({
        template: pipe(string(), uuid()),
        template_version: pipe(string(), uuid()),
        revision: numeric(2147483647),
        draft_id: optional(nullable(pipe(string(), uuid()))),
        base_version: optional(nullable(pipe(string(), uuid()))),
        reason: optional(pipe(string(), trim(), maxLength(2000))),
        subject: pipe(string(), trim(), nonEmpty('Perihal wajib diisi.'), maxLength(255)),
        letter_date: pipe(string(), nonEmpty('Tanggal surat wajib diisi.')),
        numbering_mode: pipe(string(), toNumber(), integer(), minValue(1), maxValue(2)),
        number_year: numeric(9999),
        number_month: numeric(12),
        page_number: nullable(numeric(2147483647)),
        sequence_number: nullable(numeric(99)),
        values: record(string(), union([string(), array(string()), nullable(string())])),
        signers: record(string(), nullable(string())),
    });
    let schema: GenericSchema<InferInput<typeof base>, InferOutput<typeof base>> = base;
    for (const variable of configuration.variables) {
        schema = pipe(
            schema,
            forward(
                check(
                    (input) => {
                        const value = input.values[variable.id];
                        if (!variable.required && (value === undefined || value === null || value === '')) return true;
                        if (variable.multiple)
                            return (
                                Array.isArray(value) && value.length <= 100 && (!variable.required || value.length > 0)
                            );
                        return (
                            typeof value === 'string' &&
                            (!variable.required || value.trim().length > 0) &&
                            value.length <= (variable.type === 2 ? 10000 : 255)
                        );
                    },
                    `${variable.label}: isi nilai yang sesuai${variable.required ? ' (wajib)' : ''}.`,
                ),
                ['values', variable.id],
            ),
        );
    }
    for (const slot of configuration.signers)
        schema = pipe(
            schema,
            forward(
                check((input) => !!input.signers[slot.id], `Pilih ${slot.label}.`),
                ['signers', slot.id],
            ),
        );
    schema = pipe(
        schema,
        forward(
            check(
                (input) => input.numbering_mode !== 2 || input.page_number !== null,
                'Halaman wajib untuk nomor manual.',
            ),
            ['page_number'],
        ),
        forward(
            check(
                (input) => input.numbering_mode !== 2 || input.sequence_number !== null,
                'Nomor urut wajib untuk nomor manual.',
            ),
            ['sequence_number'],
        ),
    );
    return defineRequest(schema, {
        subject: 'Perihal',
        number_year: 'Tahun',
        number_month: 'Bulan',
        page_number: 'Halaman',
        sequence_number: 'Nomor urut',
    });
}

export function letterInitialValues(
    configuration: LetterConfiguration,
    template: string,
    today: string,
    resource?: LetterResource,
) {
    const draft = resource?.draft;
    return {
        template,
        template_version: configuration.version,
        revision: String(draft?.revision ?? 1),
        draft_id: draft?.id ?? null,
        base_version: draft?.base_version ?? null,
        reason: draft?.reason ?? '',
        subject: draft?.subject ?? resource?.subject ?? '',
        letter_date: draft?.letter_date ?? today,
        numbering_mode: String(draft?.numbering_mode ?? 1),
        number_year: String(draft?.number_year ?? Number(today.slice(0, 4))),
        number_month: String(draft?.number_month ?? Number(today.slice(5, 7))),
        page_number: draft?.page_number ? String(draft.page_number) : (null as string | null),
        sequence_number: draft?.sequence_number ? String(draft.sequence_number) : (null as string | null),
        values: Object.fromEntries(
            configuration.variables.map((variable) => [
                variable.id,
                draft?.values[variable.id] ?? (variable.multiple ? [] : ''),
            ]),
        ) as Record<string, string | string[] | null>,
        signers: Object.fromEntries(
            configuration.signers.map((slot) => [slot.id, draft?.signers[slot.id] ?? '']),
        ) as Record<string, string | null>,
    };
}
export function preserveLetterSelectionOrder(previous: string[], selected: string[]): string[] {
    return [...previous.filter((id) => selected.includes(id)), ...selected.filter((id) => !previous.includes(id))];
}

export type LetterLifecycle = {
    number_suggestion?: { page: number; sequence: number; number: string } | null;
    state_token: string;
    base_version: string | null;
    has_draft: boolean;
    draft_kind: number | null;
    cancelled_at: string | null;
    cancelled_by: string | null;
    reason: string | null;
    number_owner: { id: string; subject: string } | null;
};
