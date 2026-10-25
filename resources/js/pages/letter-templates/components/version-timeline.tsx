import { Link } from '@inertiajs/react';
import { ArrowRight01Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { FormCard } from '@/components/form-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import lt from '@/routes/lt';
import type { LetterTemplateLifecycle } from '@/schemas/letter-template-lifecycle';

const date = (value: string | null, timezone: string) =>
    value
        ? new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short', timeZone: timezone })
        : '—';

export function LetterTemplateVersionTimeline({
    templateId,
    lifecycle,
    disabled,
}: {
    templateId: string;
    lifecycle: LetterTemplateLifecycle;
    disabled: boolean;
}) {
    return (
        <FormCard
            title="Timeline Versi"
            description="Pilih versi untuk memeriksa konfigurasi dan dokumennya. Versi terbit terdahulu dan arsip tetap tersimpan."
        >
            <Table>
                <TableHeader>
                    <TableRow>
                        {['Versi', 'Status', 'Dibuat', 'Dipublikasikan', 'Pertama digunakan', 'Aksi'].map((label) => (
                            <TableHead key={label}>{label}</TableHead>
                        ))}
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {lifecycle.versions.map((version) => (
                        <TableRow key={version.id}>
                            <TableCell>{version.number}</TableCell>
                            <TableCell>
                                <div className="flex flex-wrap gap-2">
                                    <Badge variant="outline">{version.status_label}</Badge>
                                    {version.is_current && <Badge variant="secondary">Versi terbit aktif</Badge>}
                                </div>
                            </TableCell>
                            <TableCell>{date(version.created_at, lifecycle.timezone)}</TableCell>
                            <TableCell>{date(version.published_at, lifecycle.timezone)}</TableCell>
                            <TableCell>{date(version.first_used_at, lifecycle.timezone)}</TableCell>
                            <TableCell>
                                {version.id === lifecycle.id ? (
                                    <Badge variant="secondary">Ditampilkan</Badge>
                                ) : (
                                    <Button
                                        nativeButton={false}
                                        size="sm"
                                        variant="outline"
                                        disabled={disabled}
                                        render={
                                            <Link href={lt.edit(templateId, { query: { version: version.id } }).url} />
                                        }
                                    >
                                        <HugeiconsIcon
                                            icon={ArrowRight01Icon}
                                            data-icon="inline-start"
                                            aria-hidden="true"
                                        />
                                        Lihat Versi {version.number}
                                    </Button>
                                )}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
            <details className="text-sm">
                <summary className="cursor-pointer py-2 font-medium">50 aktivitas terbaru</summary>
                {lifecycle.audits.length === 0 ? (
                    <p className="py-3 text-muted-foreground">Belum ada aktivitas tercatat.</p>
                ) : (
                    <ol className="flex flex-col gap-4 py-3">
                        {lifecycle.audits.map((audit) => (
                            <li key={audit.id} className="flex flex-col gap-1">
                                <p>{audit.description}</p>
                                <p className="text-muted-foreground">
                                    {date(audit.occurred_at, lifecycle.timezone)} · {audit.actor}
                                    {audit.version !== null ? ` · Versi ${audit.version}` : ''}
                                </p>
                            </li>
                        ))}
                    </ol>
                )}
            </details>
        </FormCard>
    );
}
