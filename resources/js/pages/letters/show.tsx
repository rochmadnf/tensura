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
import type { LetterIssuance } from '@/schemas/letter-preview';
import type { LetterPreview } from '@/schemas/letter-preview';

type AuditPage = { data: LetterAudit[]; current_page: number; last_page: number };
export default function LetterShow({
    resource: { data: letter },
    audits,
    preview,
    issuance,
}: {
    resource: { data: LetterResource };
    audits: AuditPage;
    preview: LetterPreview;
    issuance: LetterIssuance;
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
                                {letter.number ?? 'Belum memiliki nomor resmi'}
                            </span>
                        </div>
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
                                <DeleteResourceAction
                                    key={`delete-${letter.id}-${draft.revision}`}
                                    url={destroy(letter.id).url}
                                    name={letter.subject}
                                    title="Hapus Draft Surat?"
                                    description={
                                        <>
                                            Draft “{letter.subject}” beserta riwayat draft akan dihapus permanen. Draft
                                            belum mengambil nomor surat.
                                        </>
                                    }
                                    successMessage="Draft surat dihapus."
                                    errorMessage="Draft belum dapat dihapus. Muat status terbaru."
                                    data={{ revision: draft.revision, confirmed: true }}
                                    disabled={
                                        editing || saving || issuing || (operation !== null && operation !== 'delete')
                                    }
                                    onOpenChange={(open) => setOperation(open ? 'delete' : null)}
                                />
                            </div>
                        )}
                    </div>
                </FormCard>
                {letter.official && <LetterOfficialDocument letterId={letter.id} official={letter.official} />}
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
                            preview={preview}
                            issuance={issuance}
                            disabled={editing || saving || (operation !== null && operation !== 'issue')}
                            onOpenChange={(open) => setOperation(open ? 'issue' : null)}
                            onPendingChange={setIssuing}
                        />
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
