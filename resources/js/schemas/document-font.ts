import { defineRequest } from '@/lib/request';
import { check, file, nullable, object, pipe } from 'valibot';

export type DocumentFontEntry = {
    family: string;
    style: string;
    bold: boolean;
    italic: boolean;
    available: boolean;
    browser_url: string | null;
    hash: string | null;
    fallback: string | null;
};
export type DocumentFontReport = {
    available: boolean;
    entries: DocumentFontEntry[];
    warning: string | null;
    fallback: string | null;
    limitations?: string[];
};
export type DocumentFontPage = {
    available: boolean;
    warning: string | null;
    data: { family: string; style: string }[];
    page: number;
    total: number;
    last_page: number;
    max_upload_kb: number;
};
export function createDocumentFontSchema(maxKb: number) {
    return defineRequest(
        object({
            font: pipe(
                nullable(file('Pilih berkas font.')),
                check((value) => value !== null, 'Pilih berkas font.'),
                check((value) => value === null || /\.(ttf|otf)$/i.test(value.name), 'Gunakan font .ttf atau .otf.'),
                check(
                    (value) => value === null || value.size <= maxKb * 1024,
                    `Ukuran font maksimal ${maxKb / 1024} MB.`,
                ),
            ),
        }),
    );
}
