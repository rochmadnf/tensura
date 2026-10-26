import { defineRequest } from '@/lib/request';
import { check, file, nullable, object, pipe } from 'valibot';

export function createLetterTemplateDocxSchema(maxKb: number) {
    return defineRequest(
        object({
            docx: pipe(
                nullable(file('Pilih berkas DOCX yang valid.')),
                check((value) => value !== null, 'DOCX sumber wajib dipilih.'),
                check(
                    (value) => value === null || value.name.toLowerCase().endsWith('.docx'),
                    'Berkas harus menggunakan ekstensi .docx.',
                ),
                check(
                    (value) => value === null || value.size <= maxKb * 1024,
                    `Ukuran DOCX maksimal ${maxKb / 1024} MB.`,
                ),
            ),
        }),
    );
}
export type LetterTemplateDocument = {
    id: string;
    letter_date_format?: string;
    fonts?: import('./document-font').DocumentFontReport | null;
    source: { name: string; size: number } | null;
    state: 'pending' | 'valid' | 'invalid' | 'stale';
    validated_at: string | null;
    errors: string[];
    placeholders: string[];
};
export type LetterTemplateDocumentVersion = { id: string; number: number; status: string; has_source: boolean };
