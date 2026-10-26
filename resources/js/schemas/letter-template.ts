import { defineRequest } from '@/lib/request';
import { uuid, maxLength, minLength, nonEmpty, object, pipe, string, trim } from 'valibot';

export type ClassificationOption = { id: string; label: string };
export type LetterTemplateResource = {
    id: string;
    name: string;
    is_active: boolean;
    active_version: number | null;
    has_draft: boolean;
    classification: { id: string; code: string; name: string } | null;
    draft: { id: string; number: number; letter_number: string } | null;
};

export function createLetterTemplateSchema(_classifications: ClassificationOption[]) {
    return defineRequest(
        object({
            name: pipe(string(), trim(), nonEmpty(), minLength(3), maxLength(100)),
            letter_number: pipe(string(), nonEmpty(), uuid(':attribute yang dipilih tidak valid.')),
        }),
        { name: 'Nama Templat', letter_number: 'Klasifikasi' },
    );
}
