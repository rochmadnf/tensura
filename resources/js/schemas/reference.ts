import { defineRequest } from '@/lib/request';
import { maxLength, minLength, nonEmpty, object, pipe, string, transform, trim } from 'valibot';

export interface ReferenceItemResource {
    id: string;
    content: string;
    sort_order: number;
}

export interface ReferenceGroupResource {
    id: string;
    name: string;
    description: string | null;
    items_count: number;
}

export interface ReferenceGroupDetail extends ReferenceGroupResource {
    items: ReferenceItemResource[];
}

export const referenceGroupSchema = defineRequest(
    object({
        name: pipe(string(), trim(), nonEmpty(), minLength(3), maxLength(100)),
        description: pipe(
            string(),
            trim(),
            maxLength(255),
            transform((value) => value || null),
        ),
    }),
    { name: 'Nama Grup', description: 'Deskripsi' },
);

export const referenceItemSchema = defineRequest(
    object({ content: pipe(string(), trim(), nonEmpty(), maxLength(255)) }),
    { content: 'Konten Item' },
);
