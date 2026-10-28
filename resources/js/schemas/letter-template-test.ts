export type TestOption = { id: string; label: string };
export type LetterTemplateTest = {
    fonts?: import('./document-font').DocumentFontReport;
    id: string;
    can_generate: boolean;
    validation_token: string;
    state: 'missing' | 'stale' | 'expired' | 'ready' | 'confirmed';
    test_token: string | null;
    tested_at: string | null;
    expires_at: string | null;
    confirmed_at: string | null;
    confirmed_by: string | null;
    limits: {
        short_text_max: number;
        long_text_max: number;
        max_rows: number;
        expires_hours: number;
        max_output_bytes: number;
        max_pdf_bytes: number;
    };
    variables: {
        id: string;
        label: string;
        type: number;
        is_required: boolean;
        is_multiple: boolean;
        options: TestOption[];
    }[];
    signer_slots: { id: string; label: string; options: TestOption[] }[];
};
