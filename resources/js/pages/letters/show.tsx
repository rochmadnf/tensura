import { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { FormCard } from '@/components/form-card';
import { DeleteResourceAction } from '@/components/delete-resource-action';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { FieldDescription } from '@/components/ui/field';
import type { LetterAudit, LetterResource } from '@/schemas/letter';
import { destroy, index, show } from '@/routes/ltr';
import { LetterForm } from './components/letter-form';
import { LetterChangeAction } from './components/change-action';
import { LetterPreviewPanel } from './components/preview-panel';
import { LetterIssueAction } from './components/issue-action';
import { LetterOfficialDocument } from './components/official-document';
import { LetterLifecycleActions } from './components/lifecycle-actions';
import type { LetterLifecycle } from '@/schemas/letter';
import type { LetterIssuance } from '@/schemas/letter-preview';
import type { LetterPreview, LetterPdf } from '@/schemas/letter-preview';

type AuditPage = { data: LetterAudit[]; current_page: number; last_page: number };
export default function LetterShow({
    resource: { data: letter },
    audits,
    preview,
    issuance,
    pdf,
    lifecycle,
    versions,
    officialPdf,
}: {
    resource: { data: LetterResource };
    audits: AuditPage;
    preview: LetterPreview;
    issuance: LetterIssuance;
    pdf: LetterPdf;
    officialPdf?: LetterPdf;
    lifecycle?: LetterLifecycle;
    versions?: {
        data: { official: import('@/schemas/letter-preview').LetterOfficial; pdf: LetterPdf; current: boolean }[];
        current_page: number;
        last_page: number;
    };
}) {
    const [issuing, setIssuing] = useState(
        !!issuance.operation && ['queued', 'running'].includes(issuance.operation.status),
    );
    const [editing, setEditing] = useState(false);
    const [openedLetter, setOpenedLetter] = useState<LetterResource | null>(null);
    const [saving, setSaving] = useState(false);
    const [operation, setOperation] = useState<string | null>(null);
    const draft = letter.draft;
    const formLetter = editing && openedLetter ? openedLetter : letter;
    return (
        <>
            <Head title={letter.subject}>
                <meta name="description" content="Kelola draft dan riwayat surat" />
            </Head>
            <div className="container mx-auto flex min-w-0 flex-col gap-6">
                <Link href={index().url} className="self-start text-sm text-primary underline underline-offset-4">
                    Kembali ke daftar surat
                </Link>
                <FormCard
                    sectioned
                    title={letter.subject}
                    description={`${letter.template.name} · dibuat oleh ${letter.creator}`}
                >
                    <div className="flex flex-col gap-3">
                        <div className="flex flex-wrap items-center gap-3">
                            <Badge variant="secondary">{letter.status_label}</Badge>
                            <span className="text-sm text-muted-foreground">
                                {lifecycle?.number_owner ? (
                                    <span
                                        className="text-destructive"
                                        title={`Nomor ini telah digunakan kembali oleh surat ‘${lifecycle.number_owner.subject}’.`}
                                    >
                                        {letter.number} ·{' '}
                                        <Link href={show(lifecycle.number_owner.id).url} className="underline">
                                            {lifecycle.number_owner.subject}
                                        </Link>
                                    </span>
                                ) : (
                                    (letter.number ?? 'Belum memiliki nomor resmi')
                                )}
                            </span>
                        </div>
                        {letter.status === 3 && (
                            <FieldDescription>
                                {lifecycle?.number_owner
                                    ? 'Nomor sedang digunakan surat lain.'
                                    : 'Nomor tersedia untuk digunakan kembali.'}{' '}
                                {lifecycle?.reason} {lifecycle?.cancelled_by}{' '}
                                {lifecycle?.cancelled_at
                                    ? new Date(lifecycle.cancelled_at).toLocaleString('id-ID')
                                    : ''}
                            </FieldDescription>
                        )}
                        {lifecycle && (
                            <LetterLifecycleActions
                                letterId={letter.id}
                                status={letter.status}
                                lifecycle={lifecycle}
                                disabled={
                                    editing || saving || issuing || (operation !== null && operation !== 'lifecycle')
                                }
                                onOpenChange={(open) => setOperation(open ? 'lifecycle' : null)}
                            />
                        )}
                        {lifecycle?.number_suggestion && (
                            <FieldDescription>
                                Saran nomor pemulihan: {lifecycle.number_suggestion.number}. Pilih halaman/nomor urut
                                tersebut secara eksplisit pada draft, lalu periksa ulang.
                            </FieldDescription>
                        )}
                        {draft?.reasons.map((reason) => (
                            <FieldDescription key={reason}>{reason}</FieldDescription>
                        ))}
                        <FieldDescription>
                            {letter.status === 1
                                ? 'Simpan dan periksa preview sebelum menerbitkan surat.'
                                : 'Dokumen resmi dan riwayat penerbitan tersimpan. Perubahan resmi berikutnya menggunakan alur revisi.'}
                        </FieldDescription>
                        {draft && letter.editable && (
                            <div className="flex flex-wrap gap-2">
                                {draft.can_adjust && (
                                    <LetterChangeAction
                                        letterId={letter.id}
                                        revision={draft.revision}
                                        draftId={draft.id}
                                        baseVersion={draft.base_version}
                                        kind="template"
                                        disabled={
                                            editing ||
                                            saving ||
                                            issuing ||
                                            (operation !== null && operation !== 'template')
                                        }
                                        onOpenChange={(open) => setOperation(open ? 'template' : null)}
                                    />
                                )}
                                {draft.can_refresh && (
                                    <LetterChangeAction
                                        letterId={letter.id}
                                        revision={draft.revision}
                                        draftId={draft.id}
                                        baseVersion={draft.base_version}
                                        kind="masters"
                                        disabled={
                                            editing ||
                                            saving ||
                                            issuing ||
                                            (operation !== null && operation !== 'masters')
                                        }
                                        onOpenChange={(open) => setOperation(open ? 'masters' : null)}
                                    />
                                )}
                                {(draft.kind ?? 1) === 1 && (
                                    <DeleteResourceAction
                                        key={`delete-${letter.id}-${draft.revision}`}
                                        url={destroy(letter.id).url}
                                        name={letter.subject}
                                        title="Hapus Draft Surat?"
                                        description={
                                            <>
                                                Draft “{letter.subject}” beserta riwayat draft akan dihapus permanen.
                                                Draft belum mengambil nomor surat.
                                            </>
                                        }
                                        successMessage="Draft surat dihapus."
                                        errorMessage="Draft belum dapat dihapus. Muat status terbaru."
                                        data={{ revision: draft.revision, confirmed: true }}
                                        disabled={
                                            editing ||
                                            saving ||
                                            issuing ||
                                            (operation !== null && operation !== 'delete')
                                        }
                                        onOpenChange={(open) => setOperation(open ? 'delete' : null)}
                                    />
                                )}
                            </div>
                        )}
                    </div>
                </FormCard>
                {letter.official && (
                    <LetterOfficialDocument
                        key={letter.official.id}
                        letterId={letter.id}
                        official={letter.official}
                        pdf={officialPdf ?? pdf}
                        cancelled={letter.status === 3}
                        confirmation={lifecycle?.state_token}
                    />
                )}
                {editing && !letter.editable && (
                    <FieldDescription>
                        Status surat berubah. Salin input yang belum disimpan sebelum menutup editor atau memuat versi
                        terbaru.
                    </FieldDescription>
                )}
                {formLetter.draft && formLetter.configuration && (
                    <LetterForm
                        key={`${letter.id}-${editing}`}
                        resource={formLetter}
                        configuration={formLetter.configuration}
                        templateId={formLetter.template.id}
                        today={formLetter.draft.letter_date}
                        editing={editing}
                        disabled={saving || operation !== null || issuing}
                        onEdit={() => {
                            setOpenedLetter(letter);
                            setEditing(true);
                        }}
                        onClose={() => {
                            setOpenedLetter(null);
                            setEditing(false);
                        }}
                        onProcessingChange={setSaving}
                    />
                )}
                {draft && (
                    <LetterPreviewPanel
                        key={`${letter.id}-${draft.revision}`}
                        letterId={letter.id}
                        revision={draft.revision}
                        preview={preview}
                        pdf={pdf}
                        disabled={editing || saving || issuing || (operation !== null && operation !== 'preview')}
                        onOpenChange={(open) => setOperation(open ? 'preview' : null)}
                    />
                )}
                {draft && letter.editable && (
                    <FormCard
                        sectioned
                        title="Penerbitan Surat"
                        description="Nomor baru diambil setelah Word resmi berhasil disiapkan."
                    >
                        <LetterIssueAction
                            letterId={letter.id}
                            revision={draft.revision}
                            preview={{
                                ...preview,
                                artifact: pdf?.artifact
                                    ? { ...pdf.artifact, expires_at: preview.artifact?.expires_at ?? '' }
                                    : null,
                            }}
                            issuance={issuance}
                            disabled={editing || saving || (operation !== null && operation !== 'issue')}
                            onOpenChange={(open) => setOperation(open ? 'issue' : null)}
                            onPendingChange={setIssuing}
                        />
                    </FormCard>
                )}
                {versions && versions.data.some((version) => !version.current) && (
                    <FormCard
                        sectioned
                        title="Versi Dokumen"
                        description="Versi terdahulu dipertahankan sebagai dokumen historis."
                    >
                        <div className="flex flex-col gap-4">
                            {versions.data
                                .filter((version) => !version.current)
                                .map((version) => (
                                    <LetterOfficialDocument
                                        key={version.official.id}
                                        letterId={letter.id}
                                        official={version.official}
                                        pdf={version.pdf}
                                        historical
                                        cancelled={letter.status === 3}
                                        confirmation={lifecycle?.state_token}
                                    />
                                ))}
                            <div className="flex gap-2">
                                {versions.current_page > 1 && (
                                    <Link
                                        href={
                                            show(letter.id, { query: { versions_page: versions.current_page - 1 } }).url
                                        }
                                        preserveScroll
                                    >
                                        Sebelumnya
                                    </Link>
                                )}
                                {versions.current_page < versions.last_page && (
                                    <Link
                                        href={
                                            show(letter.id, { query: { versions_page: versions.current_page + 1 } }).url
                                        }
                                        preserveScroll
                                    >
                                        Berikutnya
                                    </Link>
                                )}
                            </div>
                        </div>
                    </FormCard>
                )}
                <FormCard
                    sectioned
                    title="Riwayat Surat"
                    description="Pembuatan, penyimpanan, pembaruan master, dan penyesuaian templat dicatat bersama pelakunya."
                >
                    <div className="flex flex-col gap-4">
                        {audits.data.map((audit) => (
                            <article key={audit.id} className="flex flex-col gap-1">
                                <p className="text-sm font-medium">{audit.description}</p>
                                {audit.reason && <p className="text-sm">Alasan: {audit.reason}</p>}
                                {audit.related_letter && (
                                    <Link href={show(audit.related_letter).url} className="text-sm underline">
                                        Lihat surat terkait
                                    </Link>
                                )}
                                <p className="text-sm text-muted-foreground">
                                    {audit.actor} · {new Date(audit.occurred_at).toLocaleString('id-ID')} · revision{' '}
                                    {audit.revision}
                                </p>
                                {audit.changes.length > 0 && (
                                    <details>
                                        <summary className="cursor-pointer text-sm">Lihat perubahan</summary>
                                        <ul className="flex flex-col gap-2">
                                            {audit.changes.map((change, index) => (
                                                <li key={`${change.label}-${index}`} className="text-sm break-words">
                                                    {change.label}: {change.before ?? '—'} → {change.after ?? '—'}
                                                </li>
                                            ))}
                                        </ul>
                                    </details>
                                )}
                            </article>
                        ))}
                        <div className="flex flex-wrap items-center gap-2">
                            <Button
                                nativeButton={false}
                                variant="outline"
                                size="sm"
                                disabled={
                                    editing || saving || issuing || operation !== null || audits.current_page <= 1
                                }
                                render={
                                    <Link
                                        href={show(letter.id, { query: { page: audits.current_page - 1 } }).url}
                                        preserveScroll
                                    />
                                }
                            >
                                Sebelumnya
                            </Button>
                            <span className="text-sm">
                                {audits.current_page} / {audits.last_page}
                            </span>
                            <Button
                                nativeButton={false}
                                variant="outline"
                                size="sm"
                                disabled={
                                    editing || saving || operation !== null || audits.current_page >= audits.last_page
                                }
                                render={
                                    <Link
                                        href={show(letter.id, { query: { page: audits.current_page + 1 } }).url}
                                        preserveScroll
                                    />
                                }
                            >
                                Berikutnya
                            </Button>
                        </div>
                    </div>
                </FormCard>
            </div>
        </>
    );
}
LetterShow.layout = { pageName: 'Detail Surat' };
