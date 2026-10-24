import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { edit } from '@/routes/ref';
import type { ArchiveCandidate, ArchivePreview, ArchiveTarget } from '@/types/archive';

export function CandidateList({ candidates }: { candidates: ArchiveCandidate[] }) {
    return (
        <ul className="flex max-h-72 flex-col gap-3 overflow-y-auto" aria-label="Data dan konflik pemulihan">
            {candidates.map((candidate) => (
                <li key={candidate.record_uuid} className="flex flex-col gap-1 rounded-lg border p-3">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <span className="font-medium wrap-break-word">{candidate.label}</span>
                        <Badge variant={candidate.status === 'conflict' ? 'destructive' : 'outline'}>
                            {candidate.status === 'conflict'
                                ? 'Tetap di arsip'
                                : candidate.operation === 'merge'
                                  ? 'Gabungkan'
                                  : 'Siap dipulihkan'}
                        </Badge>
                    </div>
                    {candidate.conflicts.map((conflict, index) => (
                        <p key={`${conflict.code}-${index}`} className="text-xs text-muted-foreground">
                            {conflict.message}
                        </p>
                    ))}
                </li>
            ))}
        </ul>
    );
}

export function RestorePreview({
    preview,
    disabled,
    selectedTargetUuid,
    onTarget,
}: {
    preview: ArchivePreview;
    disabled: boolean;
    selectedTargetUuid?: string;
    onTarget: (target: ArchiveTarget) => void;
}) {
    const parent = preview.parent;
    const groups = preview.matching_groups;
    const archivedItems =
        parent?.items ??
        preview.candidates
            .filter((candidate) => candidate.entity === 'reference_item')
            .map((candidate) => ({ record_uuid: candidate.record_uuid, label: candidate.label }));
    return (
        <div className="flex flex-col gap-4">
            <p className="text-sm" role="status">
                <strong>{preview.counts.ready}</strong> data siap dipulihkan;{' '}
                <strong>{preview.counts.conflicted}</strong> data tetap di arsip karena konflik.
                {preview.target && (
                    <>
                        {' '}
                        Tujuan: <strong>{preview.target.name}</strong>.
                    </>
                )}
            </p>
            {groups.length > 0 && (
                <section aria-label="Perbandingan grup arsip dan aktif" className="grid gap-4 md:grid-cols-2">
                    <div className="flex min-w-0 flex-col gap-3 rounded-lg border p-4">
                        <h4 className="font-medium">Grup arsip</h4>
                        <p className="text-sm break-words">{parent?.name ?? 'Item yang diarsipkan'}</p>
                        <p className="text-sm whitespace-pre-wrap text-muted-foreground">
                            {parent?.description ?? 'Tidak ada deskripsi.'}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {archivedItems.length} item {parent ? 'tersisa dalam arsip' : 'dalam cakupan pemulihan'}
                        </p>
                        <ul
                            className="flex max-h-52 flex-col gap-1 overflow-y-auto text-sm"
                            aria-label="Item grup arsip"
                        >
                            {archivedItems.map((item) => (
                                <li key={item.record_uuid} className="break-words">
                                    {item.label}
                                </li>
                            ))}
                        </ul>
                    </div>
                    <div className="flex flex-col gap-3">
                        {groups.map((group) => (
                            <div key={group.uuid} className="flex min-w-0 flex-col gap-3 rounded-lg border p-4">
                                <h4 className="font-medium">Grup aktif: {group.name}</h4>
                                <p className="text-sm whitespace-pre-wrap text-muted-foreground">
                                    {group.description ?? 'Tidak ada deskripsi.'}
                                </p>
                                <p className="text-xs text-muted-foreground">{group.items.length} item aktif</p>
                                <ul
                                    className="flex max-h-52 flex-col gap-1 overflow-y-auto text-sm"
                                    aria-label={`Item aktif ${group.name}`}
                                >
                                    {group.items.map((item) => (
                                        <li key={item.uuid} className="break-words">
                                            {item.content}
                                        </li>
                                    ))}
                                </ul>
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        variant="outline"
                                        disabled={disabled || selectedTargetUuid === group.uuid}
                                        onClick={() => onTarget({ uuid: group.uuid, name: group.name })}
                                    >
                                        {selectedTargetUuid === group.uuid ? 'Tujuan dipilih' : 'Gunakan grup ini'}
                                    </Button>
                                    <Button
                                        variant="outline"
                                        nativeButton={false}
                                        disabled={disabled}
                                        render={
                                            <Link href={edit(group.uuid)} target="_blank" rel="noopener noreferrer" />
                                        }
                                    >
                                        Ubah grup aktif<span className="sr-only"> (tab baru)</span>
                                    </Button>
                                </div>
                            </div>
                        ))}
                    </div>
                </section>
            )}
            <CandidateList candidates={preview.candidates} />
            {preview.counts.conflicted > 0 && (
                <p className="text-sm text-muted-foreground">
                    Data yang bentrok tidak ditimpa. Selesaikan konflik lalu buat pratinjau baru untuk mencoba kembali.
                </p>
            )}
        </div>
    );
}
