import { Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/archives';
import { index as referenceArchiveIndex } from '@/routes/ref/archive';
import type {
    ArchiveContext,
    ArchivePage,
    ArchiveRecordResource,
    ArchiveResource,
    ArchiveTargetGroup,
} from '@/types/archive';
import { archivedDate, moduleLabel, parentStatusLabels } from './components/presentation';
import { RecordTable } from './components/record-table';
import { useArchiveRestore } from './components/use-archive-restore';
import { RestoreWorkbench } from './components/restore-workbench';
import { selectedRecordUuids } from './components/restore-state';

interface ArchiveShowProps {
    resource: { data: ArchiveResource };
    parent_record: { data: ArchiveRecordResource } | null;
    resources: ArchivePage<ArchiveRecordResource>;
    context: ArchiveContext;
    target_groups?: ArchivePage<ArchiveTargetGroup>;
}

export default function ArchiveShow(props: ArchiveShowProps) {
    return <ArchiveDetail key={props.resource.data.uuid} {...props} />;
}

function ArchiveDetail({
    resource: { data: archive },
    parent_record,
    resources,
    context,
    target_groups,
}: ArchiveShowProps) {
    const parent = parent_record?.data;
    const flow = useArchiveRestore(archive, !!parent, context);
    const selectedCount = selectedRecordUuids(flow.state.rowSelection).length;
    return (
        <>
            <Head title={`Arsip · ${archive.label}`} />
            <div className="container mx-auto flex min-w-0 flex-col gap-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="flex min-w-0 flex-col gap-2">
                        <h2 className="text-xl font-semibold break-words">{archive.label}</h2>
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant="secondary">{moduleLabel(archive.module)}</Badge>
                            <Badge variant="outline">{archive.kind === 'package' ? 'Grup' : 'Item'}</Badge>
                            <Badge variant="outline">Induk: {parentStatusLabels[archive.parent_status]}</Badge>
                        </div>
                    </div>
                    <Button
                        variant="outline"
                        nativeButton={false}
                        render={<Link href={context === 'reference' ? referenceArchiveIndex() : index()} />}
                    >
                        Kembali ke arsip
                    </Button>
                </div>
                <dl className="grid grid-cols-1 gap-4 rounded-lg border p-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div className="flex flex-col gap-1">
                        <dt className="text-sm text-muted-foreground">Induk asal</dt>
                        <dd className="text-sm break-words">{archive.parent?.label ?? '—'}</dd>
                    </div>
                    <div className="flex flex-col gap-1">
                        <dt className="text-sm text-muted-foreground">Tujuan aktif</dt>
                        <dd className="text-sm break-words">{archive.target?.name ?? 'Tidak tersedia'}</dd>
                    </div>
                    <div className="flex flex-col gap-1">
                        <dt className="text-sm text-muted-foreground">Item tersisa</dt>
                        <dd className="text-sm tabular-nums">{archive.items_count}</dd>
                    </div>
                    <div className="flex flex-col gap-1">
                        <dt className="text-sm text-muted-foreground">Diarsipkan pada (WITA)</dt>
                        <dd className="text-sm">{archivedDate(archive.archived_at)}</dd>
                    </div>
                    <div className="flex flex-col gap-1">
                        <dt className="text-sm text-muted-foreground">Diarsipkan oleh</dt>
                        <dd className="text-sm break-words">{archive.archived_by_name ?? '—'}</dd>
                    </div>
                    <div className="flex flex-col gap-1">
                        <dt className="text-sm text-muted-foreground">UUID arsip</dt>
                        <dd className="text-xs break-all">{archive.uuid}</dd>
                    </div>
                </dl>
                {parent && (
                    <section aria-labelledby="archived-parent" className="flex flex-col gap-3">
                        <h3 id="archived-parent" className="font-medium">
                            Grup yang diarsipkan
                        </h3>
                        <p className="text-sm break-words">{parent.label}</p>
                        <p className="text-sm whitespace-pre-wrap text-muted-foreground">
                            {parent.description ?? 'Tidak ada deskripsi.'}
                        </p>
                        <p className="text-xs break-all text-muted-foreground">
                            UUID asal: {parent.source_uuid ?? '—'}
                        </p>
                        {parent.unsupported_reason && (
                            <p className="text-sm text-destructive">{parent.unsupported_reason}</p>
                        )}
                    </section>
                )}
                <RestoreWorkbench archive={archive} parent={parent} groups={target_groups} flow={flow} />
                <section aria-labelledby="archived-items" className="flex min-w-0 flex-col gap-4">
                    <h3 id="archived-items" className="font-medium">
                        Item yang diarsipkan
                    </h3>
                    {archive.kind === 'package' && archive.module === 'reference' && (
                        <div className="flex flex-wrap items-center gap-3">
                            <p className="text-sm" role="status">
                                {selectedCount} item dipilih lintas halaman
                            </p>
                            <Button
                                variant="outline"
                                disabled={flow.busy || selectedCount === 0}
                                onClick={() => {
                                    flow.dispatch({ type: 'mode', mode: 'children_selected' });
                                    document.getElementById('restore')?.focus();
                                    document
                                        .getElementById('restore')
                                        ?.scrollIntoView({ behavior: 'instant', block: 'start' });
                                }}
                            >
                                Pulihkan Terpilih
                            </Button>
                            <Button
                                variant="ghost"
                                disabled={flow.busy || selectedCount === 0}
                                onClick={() => flow.setRowSelection({})}
                            >
                                Bersihkan pilihan
                            </Button>
                        </div>
                    )}
                    <RecordTable
                        resources={resources}
                        archiveUuid={archive.uuid}
                        rowSelection={flow.state.rowSelection}
                        onRowSelectionChange={flow.setRowSelection}
                        onRestore={flow.chooseChild}
                        selectable={archive.kind === 'package' && archive.module === 'reference'}
                        restorable={archive.module === 'reference'}
                        disabled={flow.busy}
                    />
                </section>
            </div>
        </>
    );
}
ArchiveShow.layout = { pageName: 'Detail Archive' };
