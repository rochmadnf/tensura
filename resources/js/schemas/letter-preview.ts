import type { DocumentFontReport } from '@/schemas/document-font';

export type LetterOperation = {
    id: string;
    letter_id: string;
    subject: string;
    kind: 'preview';
    revision: number;
    status: 'queued' | 'running' | 'succeeded' | 'failed' | 'stale';
    status_label: string;
    status_revision: number;
    message: string | null;
    updated_at: string;
};
export type LetterPreview = {
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
