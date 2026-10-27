import type { DocumentFontReport } from '@/schemas/document-font';

export type LetterOperation = {
    id: string;
    letter_id: string;
    subject: string;
    kind: 'preview' | 'issue' | 'pdf';
    source_id?: string | null;
    revision: number;
    status: 'queued' | 'running' | 'succeeded' | 'failed' | 'stale';
    status_label: string;
    status_revision: number;
    message: string | null;
    updated_at: string;
};
export type LetterPreview = {
    draft_id?: string | null;
    base_version?: string | null;
    revision: number;
    operation: LetterOperation | null;
    artifact: {
        id: string;
        fingerprint: string;
        font_report: DocumentFontReport;
        expires_at: string;
        confirmed: boolean;
    } | null;
    can_generate: boolean;
    reason: string | null;
    max_bytes: number;
};

export type LetterIssuance = {
    operation: LetterOperation | null;
    publication_month: string;
    crossed_month: boolean;
    date: string | null;
    period: string | null;
};
export type LetterOfficial = {
    reason?: string | null;
    word_id?: string;
    id: string;
    version: number;
    number: string;
    subject: string;
    letter_date: string;
    issued_at: string;
    issued_by: string;
    resolved: Record<string, string>;
    max_bytes: number;
    font_report: DocumentFontReport;
};

export type LetterPdf = {
    draft_id?: string | null;
    base_version?: string | null;
    source_id: string | null;
    operation: LetterOperation | null;
    artifact: {
        id: string;
        fingerprint: string;
        confirmed: boolean;
        font_report: DocumentFontReport;
        substitutions: Record<string, string>;
        converter: string;
    } | null;
    reason: string | null;
    max_bytes: number;
};
