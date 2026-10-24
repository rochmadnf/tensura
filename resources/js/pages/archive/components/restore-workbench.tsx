import { useState, type SubmitEvent } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Field, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type {
    ArchivePage,
    ArchiveRecordResource,
    ArchiveResource,
    ArchiveRestoreMode,
    ArchiveTargetGroup,
} from '@/types/archive';
import type { ArchiveRestoreFlow } from './use-archive-restore';
import { canConfirmRestore, canPreviewRestore, selectedRecordUuids } from './restore-state';
import { CandidateList, RestorePreview } from './restore-preview';
import { TargetGroupPicker } from './target-group-picker';

const modeLabels: Record<ArchiveRestoreMode, string> = {
    parent_only: 'Pulihkan Induk Saja',
    parent_with_children: 'Pulihkan Induk + Item',
    children_all: 'Pulihkan Semua Item',
    children_selected: 'Pulihkan Terpilih',
    child: 'Pulihkan Satu Item',
};

function RenameGroup({ parent, flow }: { parent: ArchiveRecordResource; flow: ArchiveRestoreFlow }) {
    const [name, setName] = useState(parent.label);
    function submit(event: SubmitEvent<HTMLFormElement>) {
        event.preventDefault();
        void flow.renameGroup(name);
    }
    return (
        <form onSubmit={submit} className="flex flex-col gap-3">
            <FieldGroup className="flex-col items-end gap-3 sm:flex-row">
                <Field data-invalid={!!flow.renameErrors.name}>
                    <FieldLabel htmlFor="archived-group-name">Nama baru grup arsip</FieldLabel>
                    <Input
                        id="archived-group-name"
                        value={name}
                        disabled={flow.busy}
                        required
                        minLength={3}
                        maxLength={100}
                        aria-invalid={!!flow.renameErrors.name}
                        onChange={(event) => {
                            setName(event.target.value);
                            flow.dispatch({ type: 'rename-draft', dirty: event.target.value.trim() !== parent.label });
                        }}
                    />
                    {flow.renameErrors.name && <FieldError>{flow.renameErrors.name}</FieldError>}
                </Field>
                <Button
                    type="submit"
                    variant="outline"
                    disabled={flow.busy || name.trim() === parent.label || !name.trim()}
                >
                    Simpan nama
                </Button>
                {flow.state.renameDirty && (
                    <Button
                        type="button"
                        variant="ghost"
                        disabled={flow.busy}
                        onClick={() => {
                            setName(parent.label);
                            flow.dispatch({ type: 'rename-draft', dirty: false });
                        }}
                    >
                        Batal ubah nama
                    </Button>
                )}
            </FieldGroup>
            <p className="text-xs text-muted-foreground">
                Perubahan nama disimpan ke arsip. Tinjau pratinjau baru sesudah menyimpannya.
            </p>
        </form>
    );
}

export function RestoreWorkbench({
    archive,
    parent,
    groups,
    flow,
}: {
    archive: ArchiveResource;
    parent?: ArchiveRecordResource;
    groups?: ArchivePage<ArchiveTargetGroup>;
    flow: ArchiveRestoreFlow;
}) {
    const { state, busy, dispatch } = flow;
    const selectedCount = selectedRecordUuids(state.rowSelection).length;
    const parentMode = state.mode === 'parent_only' || state.mode === 'parent_with_children';
    const modes: ArchiveRestoreMode[] =
        archive.kind === 'package'
            ? ['parent_only', 'parent_with_children', 'children_all', 'children_selected', 'child']
            : ['child'];
    function modeDisabled(mode: ArchiveRestoreMode) {
        if (mode === 'parent_only' || mode === 'parent_with_children') return !parent?.supported;
        return archive.items_count === 0;
    }
    return (
        <section
            id="restore"
            tabIndex={-1}
            aria-labelledby="restore-heading"
            className="flex scroll-mt-4 flex-col gap-4 rounded-lg border p-4 outline-none focus-visible:ring-2 focus-visible:ring-ring"
        >
            <h3 id="restore-heading" className="font-medium">
                Pemulihan
            </h3>
            {archive.module !== 'reference' ? (
                <p className="text-sm text-muted-foreground">Pemulihan modul ini belum didukung.</p>
            ) : (
                <>
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-end">
                        <Field className="sm:max-w-sm">
                            <FieldLabel htmlFor="restore-mode">Cakupan pemulihan</FieldLabel>
                            <Select
                                items={modes.map((mode) => ({ value: mode, label: modeLabels[mode] }))}
                                value={state.mode}
                                disabled={busy}
                                onValueChange={(mode) => {
                                    if (mode) dispatch({ type: 'mode', mode: mode as ArchiveRestoreMode });
                                }}
                            >
                                <SelectTrigger id="restore-mode" className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        {modes.map((mode) => (
                                            <SelectItem key={mode} value={mode} disabled={modeDisabled(mode)}>
                                                {modeLabels[mode]}
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </Field>
                        <Button
                            disabled={busy || !canPreviewRestore(state) || modeDisabled(state.mode)}
                            onClick={() => void flow.requestPreview()}
                        >
                            {state.activity === 'preview' ? 'Memuat pratinjau...' : 'Buat pratinjau'}
                        </Button>
                    </div>
                    {state.mode === 'child' && (
                        <p className="text-sm text-muted-foreground">
                            {state.childUuid
                                ? 'Satu item dipilih. Tinjau namanya pada pratinjau sebelum mengonfirmasi.'
                                : 'Pilih Pulihkan pada satu baris item di bawah.'}
                        </p>
                    )}
                    {state.mode === 'children_selected' && (
                        <p className="text-sm text-muted-foreground">
                            {selectedCount} item dipilih lintas halaman. Hanya pilihan ini yang akan diproses.
                        </p>
                    )}
                    {state.renameDirty && (
                        <p className="text-sm text-muted-foreground">
                            Simpan atau batalkan perubahan nama sebelum membuat pratinjau.
                        </p>
                    )}
                    <div className="flex flex-wrap items-center gap-3">
                        <p className="text-sm">
                            Tujuan:{' '}
                            <strong>
                                {state.target?.name ??
                                    (parentMode
                                        ? 'Grup asal dari arsip'
                                        : (archive.target?.name ?? 'Induk asal belum tersedia'))}
                            </strong>
                        </p>
                        <TargetGroupPicker
                            archiveUuid={archive.uuid}
                            groups={groups}
                            disabled={busy}
                            onChoose={(target) => dispatch({ type: 'target', target })}
                        />
                        {state.target && (
                            <Button
                                variant="ghost"
                                disabled={busy}
                                onClick={() => dispatch({ type: 'target', target: null })}
                            >
                                Gunakan tujuan bawaan
                            </Button>
                        )}
                    </div>
                    {state.target && (
                        <p className="text-sm text-muted-foreground">
                            {parentMode
                                ? 'Induk arsip akan digabungkan ke grup aktif. Identitas dan deskripsi grup aktif tetap digunakan.'
                                : 'Item ditambahkan ke akhir grup tujuan. Snapshot induk yang masih diarsipkan tetap disimpan.'}
                        </p>
                    )}
                    {parent?.supported && !archive.parent_resolved && (
                        <RenameGroup key={parent.label} parent={parent} flow={flow} />
                    )}
                    {state.notice && (
                        <p role="alert" className="text-sm">
                            {state.notice}
                        </p>
                    )}
                    {state.preview && (
                        <>
                            <RestorePreview
                                preview={state.preview}
                                disabled={busy}
                                selectedTargetUuid={state.target?.uuid}
                                onTarget={(target) => dispatch({ type: 'target', target })}
                            />
                            <Button
                                className="self-start"
                                disabled={!canConfirmRestore(state)}
                                onClick={() => dispatch({ type: 'confirmation', open: true })}
                            >
                                Lanjutkan konfirmasi
                            </Button>
                        </>
                    )}
                    <Dialog
                        open={state.confirmationOpen}
                        onOpenChange={(open) => {
                            if (!busy) dispatch({ type: 'confirmation', open });
                        }}
                    >
                        <DialogContent showCloseButton={!busy} className="max-h-[85svh] overflow-y-auto sm:max-w-xl">
                            <DialogHeader>
                                <DialogTitle>Konfirmasi pemulihan</DialogTitle>
                                <DialogDescription>
                                    Tinjau sasaran dan tujuan terakhir. Data yang bentrok tetap berada di arsip.
                                </DialogDescription>
                            </DialogHeader>
                            {state.preview && (
                                <>
                                    <p className="text-sm font-medium">
                                        {modeLabels[state.mode]} · {archive.label}
                                    </p>
                                    <p className="text-sm">
                                        Tujuan:{' '}
                                        {state.preview.target?.name ??
                                            state.preview.parent?.name ??
                                            archive.parent?.label ??
                                            'Induk asal'}
                                    </p>
                                    <p className="text-sm">
                                        {state.preview.counts.ready} data dipulihkan; {state.preview.counts.conflicted}{' '}
                                        data dilewati.
                                    </p>
                                    <CandidateList candidates={state.preview.candidates} />
                                </>
                            )}
                            <DialogFooter>
                                <DialogClose render={<Button variant="outline" disabled={busy} />}>Batal</DialogClose>
                                <Button
                                    disabled={busy || !canConfirmRestore(state)}
                                    onClick={() => void flow.confirm()}
                                >
                                    {state.activity === 'restore' ? 'Memulihkan...' : 'Konfirmasi pemulihan'}
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </>
            )}
            {state.result && (
                <section aria-label="Hasil pemulihan" className="flex flex-col gap-3" role="status">
                    <h4 className="font-medium">Hasil pemulihan</h4>
                    <p className="text-sm">
                        {state.result.counts.restored} data dipulihkan ke {state.result.target.name};{' '}
                        {state.result.counts.remaining} data masih diarsipkan.
                    </p>
                    {state.result.conflicts.length > 0 && (
                        <>
                            <p className="text-sm text-muted-foreground">
                                Selesaikan konflik berikut, lalu buat pratinjau baru untuk mencoba kembali.
                            </p>
                            <CandidateList candidates={state.result.conflicts} />
                        </>
                    )}
                </section>
            )}
        </section>
    );
}
