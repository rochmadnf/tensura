import type { PaginationMeta } from '@/components/data-table';

export type ArchiveContext = 'reference' | null;
export type ArchiveKind = 'package' | 'record';
export type ArchiveRestoreMode =
    | 'parent_only'
    | 'parent_with_children'
    | 'children_all'
    | 'child'
    | 'children_selected';
export type ArchiveDeleteMode = 'package' | 'child' | 'children_selected';
export type ArchiveParentStatus = 'archived' | 'active' | 'missing' | 'unavailable';
export type ArchiveTarget = { uuid: string; name: string };

export interface ArchiveResource {
    uuid: string;
    module: string;
    kind: ArchiveKind;
    entity: string;
    label: string;
    parent: { uuid: string | null; label: string | null } | null;
    parent_status: ArchiveParentStatus;
    parent_resolved: boolean;
    target: ArchiveTarget | null;
    records_count: number;
    items_count: number;
    archived_at: string | null;
    archived_by_name: string | null;
}

export interface ArchiveRecordResource {
    uuid: string;
    entity: string;
    label: string;
    source_key: string;
    source_uuid: string | null;
    schema_version: number;
    supported: boolean;
    unsupported_reason: string | null;
    description: string | null;
    sort_order: number | null;
    original_created_at: string | null;
    original_updated_at: string | null;
}

export interface ArchiveFilters {
    keyword: string;
    module: string;
    kind: ArchiveKind | '';
}

export interface ArchivePage<T> {
    data: T[];
    meta: PaginationMeta;
}

export interface ArchiveConflict {
    code:
        | 'unsupported_snapshot'
        | 'identity_conflict'
        | 'name_conflict'
        | 'parent_blocked'
        | 'parent_missing'
        | 'parent_mismatch'
        | 'content_conflict';
    message: string;
}

export interface ArchiveCandidate {
    record_uuid: string;
    entity: string;
    label: string;
    operation: 'restore' | 'merge' | 'delete_forever';
    status: 'ready' | 'conflict';
    conflicts: ArchiveConflict[];
}

export type ArchiveSelection = {
    record_uuids?: string[];
} & (
    | { action: 'restore'; mode: ArchiveRestoreMode; target_group_uuid?: string | null }
    | { action: 'delete_forever'; mode: ArchiveDeleteMode; target_group_uuid?: never }
);

export interface ArchivePreview {
    archive_uuid: string;
    action: 'restore' | 'delete_forever';
    mode: ArchiveRestoreMode | ArchiveDeleteMode;
    parent: { record_uuid: string; uuid: string; name: string; description: string | null } | null;
    target: ArchivePreviewGroup | null;
    matching_groups: ArchivePreviewGroup[];
    candidates: ArchiveCandidate[];
    conflicts: ArchiveCandidate[];
    counts: { total: number; ready: number; conflicted: number };
    can_confirm: boolean;
    confirmation_token: string | null;
}

export interface ArchivePreviewGroup extends ArchiveTarget {
    description: string | null;
    items: { uuid: string; content: string; sort_order: number }[];
}

export interface ArchiveRestoreResult {
    archive_uuid: string;
    mode: ArchiveRestoreMode;
    target: ArchiveTarget;
    counts: { requested: number; restored: number; conflicted: number; remaining: number };
    archive_exists: boolean;
    conflicts: ArchiveCandidate[];
}

export interface ArchiveDeleteResult {
    archive_uuid: string;
    mode: ArchiveDeleteMode;
    counts: { requested: number; deleted: number; remaining: number };
    archive_exists: boolean;
}
