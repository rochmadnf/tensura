import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import type { ArchivePreview } from '@/types/archive';
import { canDeleteArchive } from './delete-state';
import type { ArchiveDeleteFlow } from './use-archive-delete';
import { Alert, AlertDescription } from '@/components/reui/alert';

export function DeleteSummary({ preview }: { preview: ArchivePreview }) {
    const items = preview.candidates.filter((candidate) => candidate.entity === 'reference_item').length;
    const groups = preview.candidates.filter((candidate) => candidate.entity === 'reference_group').length;
    return (
        <div className="flex flex-col gap-3">
            <p className="text-sm">
                <strong>{preview.counts.total}</strong> data arsip akan dihapus permanen: {groups} grup dan {items}{' '}
                item.
            </p>
            <ul aria-label="Sasaran penghapusan permanen" className="flex max-h-64 flex-col gap-2 overflow-y-auto">
                {preview.candidates.map((candidate) => (
                    <li key={candidate.record_uuid} className="text-sm break-words">
                        {candidate.label}
                    </li>
                ))}
            </ul>
        </div>
    );
}

export function DeleteArchiveDialog({ flow }: { flow: ArchiveDeleteFlow }) {
    const { state } = flow;
    const processing = state.phase === 'deleting' || state.phase === 'refresh';
    return (
        <AlertDialog
            open={state.phase !== 'closed' && state.phase !== 'refresh'}
            onOpenChange={(open) => {
                if (!open) flow.close();
            }}
        >
            <AlertDialogContent className="max-h-[85svh] overflow-y-auto sm:max-w-xl">
                <AlertDialogTitle>Hapus permanen arsip?</AlertDialogTitle>
                <AlertDialogDescription>
                    Data yang dihapus permanen tidak dapat dipulihkan. Data yang sudah aktif tetap utuh.
                </AlertDialogDescription>
                <p className="text-sm wrap-break-word">
                    Arsip: <strong>{state.subject?.label}</strong>
                </p>
                <p className="text-sm">
                    Cakupan:{' '}
                    {state.selection?.mode === 'package'
                        ? 'Seluruh paket arsip tersisa'
                        : state.selection?.mode === 'child'
                          ? 'Satu item arsip'
                          : 'Item arsip terpilih lintas halaman'}
                </p>
                {state.notice && (
                    <Alert variant="destructive">
                        <AlertDescription>{state.notice}</AlertDescription>
                    </Alert>
                )}
                {state.phase === 'preview' ? (
                    <div aria-busy="true" className="flex flex-col gap-2">
                        <p className="text-sm" role="status">
                            Menyiapkan konfirmasi terbaru...
                        </p>
                        <Skeleton className="h-20 w-full" />
                    </div>
                ) : (
                    state.preview && <DeleteSummary preview={state.preview} />
                )}
                <AlertDialogFooter>
                    <AlertDialogCancel render={<Button variant="outline" disabled={processing} />}>
                        Batal
                    </AlertDialogCancel>
                    {state.phase === 'review' && !state.preview && (
                        <Button variant="outline" onClick={flow.retryPreview}>
                            Siapkan konfirmasi baru
                        </Button>
                    )}
                    <Button
                        variant="destructive"
                        disabled={processing || !canDeleteArchive(state)}
                        onClick={() => void flow.confirm()}
                    >
                        {processing ? 'Menghapus permanen...' : 'Hapus permanen'}
                    </Button>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
