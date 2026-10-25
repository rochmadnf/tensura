import { useEffect, useRef, useState } from 'react';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import audits from '@/routes/lt/audits';
import type { LetterTemplateAuditPage } from '@/schemas/letter-template-lifecycle';
import { Link } from '@inertiajs/react';
import { ArrowRight01Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { FormCard } from '@/components/form-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import lt from '@/routes/lt';
import type { LetterTemplateLifecycle } from '@/schemas/letter-template-lifecycle';

const emptyAudits: LetterTemplateAuditPage = { data: [], previous_cursor: null, next_cursor: null };

const date = (value: string | null, timezone: string) =>
    value
        ? new Date(value).toLocaleString('id-ID', { dateStyle: 'long', timeStyle: 'medium', timeZone: timezone })
        : '—';

export function LetterTemplateVersionTimeline({
    templateId,
    lifecycle,
    initialAudits = emptyAudits,
    disabled,
}: {
    templateId: string;
    lifecycle: LetterTemplateLifecycle;
    initialAudits?: LetterTemplateAuditPage;
    disabled: boolean;
}) {
    const [page, setPage] = useState(initialAudits);
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);
    const pendingCursor = useRef<string | null>(null);
    const controller = useRef<AbortController | null>(null);
    useEffect(() => {
        controller.current?.abort();
        setPage(initialAudits);
        setLoading(false);
        setFailed(false);
        return () => controller.current?.abort();
    }, [initialAudits]);
    async function load(cursor: string | null) {
        controller.current?.abort();
        const request = new AbortController();
        controller.current = request;
        pendingCursor.current = cursor;
        setLoading(true);
        setFailed(false);
        try {
            const response = await fetch(audits.index.url(templateId, { query: { cursor } }), {
                headers: { Accept: 'application/json' },
                signal: request.signal,
                cache: 'no-store',
            });
            if (!response.ok) throw new Error('Aktivitas gagal dimuat.');
            const next: LetterTemplateAuditPage = await response.json();
            if (!request.signal.aborted) setPage(next);
        } catch {
            if (!request.signal.aborted) setFailed(true);
        } finally {
            if (!request.signal.aborted) setLoading(false);
        }
    }
    return (
        <FormCard
            sectioned
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
            <div className="flex flex-col gap-4" aria-busy={loading}>
                <p className="text-sm font-medium">
                    Menampilkan {page.data.length} aktivitas{!page.previous_cursor ? ' terbaru' : ''}
                </p>
                {failed && (
                    <div role="alert" className="flex items-center gap-3 text-sm">
                        Aktivitas gagal dimuat.
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={loading || disabled}
                            onClick={() => load(pendingCursor.current)}
                        >
                            Coba lagi
                        </Button>
                    </div>
                )}
                {loading && (
                    <p role="status" className="text-sm text-muted-foreground">
                        Memuat aktivitas…
                    </p>
                )}
                {page.data.length === 0 ? (
                    <p className="text-sm text-muted-foreground">Belum ada aktivitas tercatat.</p>
                ) : (
                    <ol aria-label="Riwayat aktivitas templat" className="flex flex-col">
                        {page.data.map((audit) => (
                            <li
                                key={audit.id}
                                className="relative ml-3 border-l pb-6 pl-7 last:border-l-transparent last:pb-0"
                            >
                                <span
                                    aria-hidden="true"
                                    className="absolute top-1 -left-1.5 size-3 rounded-full border-2 border-background bg-primary"
                                />
                                <div className="mb-2 flex flex-wrap items-center gap-2">
                                    <p className="text-sm font-semibold">{audit.description}</p>
                                    {audit.version !== null && <Badge variant="outline">Versi {audit.version}</Badge>}
                                </div>
                                <Card size="sm" className="gap-0 py-0 shadow-none">
                                    <Collapsible>
                                        <CollapsibleTrigger className="group w-full text-left">
                                            <CardHeader className="flex flex-wrap items-center justify-between gap-2 py-3">
                                                <span className="flex items-center gap-2">
                                                    <Avatar className="size-6">
                                                        <AvatarFallback>
                                                            {audit.actor
                                                                .split(/\s+/)
                                                                .slice(0, 2)
                                                                .map((part) => part[0])
                                                                .join('')}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <span className="text-sm">{audit.actor}</span>
                                                </span>
                                                <span className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                                    <time dateTime={audit.occurred_at}>
                                                        {date(audit.occurred_at, lifecycle.timezone)}
                                                    </time>
                                                    <span className="sr-only">Detail perubahan</span>
                                                    <HugeiconsIcon
                                                        icon={ArrowRight01Icon}
                                                        className="size-4 transition-transform group-data-open:rotate-90"
                                                        aria-hidden="true"
                                                    />
                                                </span>
                                            </CardHeader>
                                        </CollapsibleTrigger>
                                        <CollapsibleContent>
                                            <CardContent className="border-t py-3">
                                                {audit.changes === null ? (
                                                    <p className="text-sm text-muted-foreground">
                                                        Rincian perubahan belum tersedia untuk aktivitas ini.
                                                    </p>
                                                ) : audit.changes.length === 0 ? (
                                                    <p className="text-sm text-muted-foreground">
                                                        Tidak ada perubahan nilai konfigurasi.
                                                    </p>
                                                ) : (
                                                    <dl className="flex flex-col gap-4">
                                                        {audit.changes.map((change) => (
                                                            <div
                                                                key={change.field}
                                                                className="flex flex-col gap-1 text-sm"
                                                            >
                                                                <dt className="font-medium">{change.label}</dt>
                                                                <dd className="grid gap-2 wrap-anywhere whitespace-pre-wrap sm:grid-cols-[1fr_auto_1fr]">
                                                                    <span>
                                                                        <span className="block text-xs text-muted-foreground">
                                                                            Sebelum
                                                                        </span>
                                                                        {change.before ?? 'Kosong'}
                                                                    </span>
                                                                    <span
                                                                        aria-hidden="true"
                                                                        className="text-muted-foreground"
                                                                    >
                                                                        →
                                                                    </span>
                                                                    <span>
                                                                        <span className="block text-xs text-muted-foreground">
                                                                            Sesudah
                                                                        </span>
                                                                        {change.after ?? 'Kosong'}
                                                                    </span>
                                                                </dd>
                                                            </div>
                                                        ))}
                                                    </dl>
                                                )}
                                            </CardContent>
                                        </CollapsibleContent>
                                    </Collapsible>
                                </Card>
                            </li>
                        ))}
                    </ol>
                )}
                <div className="flex items-center justify-between gap-3">
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        disabled={disabled || loading || !page.previous_cursor}
                        onClick={() => load(page.previous_cursor)}
                    >
                        Sebelumnya
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        disabled={disabled || loading || !page.next_cursor}
                        onClick={() => load(page.next_cursor)}
                    >
                        Berikutnya
                    </Button>
                </div>
            </div>
        </FormCard>
    );
}
