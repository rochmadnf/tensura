export type VersionPublicationCheck = { key: string; label: string; passed: boolean; message: string };
export type LetterTemplateLifecycle = {
    id: string;
    number: number;
    timezone: string;
    status: 1 | 2 | 3;
    status_label: string;
    is_current: boolean;
    editable: boolean;
    classification: { id: string; code: string; name: string };
    published_at: string | null;
    first_used_at: string | null;
    version_token: string;
    readiness: { ready: boolean; checks: VersionPublicationCheck[] };
    can_start_draft: boolean;
    draft_mode: 'reopen' | 'copy';
    can_archive: boolean;
    draft: { id: string; number: number } | null;
    versions: {
        id: string;
        number: number;
        status: 1 | 2 | 3;
        status_label: string;
        is_current: boolean;
        created_at: string | null;
        published_at: string | null;
        first_used_at: string | null;
    }[];
};

export type LetterTemplateAudit = {
    id: string;
    version: number | null;
    actor: string;
    occurred_at: string;
    operation: string;
    description: string;
    changes: { field: string; label: string; before: string | null; after: string | null }[] | null;
};
export type LetterTemplateAuditPage = {
    data: LetterTemplateAudit[];
    previous_cursor: string | null;
    next_cursor: string | null;
};
