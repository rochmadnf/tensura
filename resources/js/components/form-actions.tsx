import { Link } from '@inertiajs/react';
import { LoaderIcon, SaveIcon, XIcon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { TableActionButton } from '@/components/table-action-button';
import { Button } from '@/components/ui/button';

/**
 * Simpan / Batal untuk form.
 *
 * - Default: ikon + kata kerja (form kartu, halaman create/edit).
 * - `compact`: ikon saja + tooltip. WAJIB dipakai bila berada di dalam tabel atau baris daftar
 *   (editor inline di `TableRow`/`TableCell`, item daftar), agar seragam dengan tombol aksi tabel.
 */
export function FormActions({
    cancelUrl,
    processing,
    disabled = false,
    compact = false,
    onCancel,
}: {
    cancelUrl: string;
    processing: boolean;
    disabled?: boolean;
    compact?: boolean;
    fullWidth?: boolean;
    onCancel?: () => void;
}) {
    const busy = processing || disabled;
    const cancelHref = onCancel ? undefined : cancelUrl;

    if (compact) {
        return (
            <div className="flex items-center gap-2">
                <TableActionButton
                    type="submit"
                    icon={processing ? LoaderIcon : SaveIcon}
                    label={processing ? 'Menyimpan' : 'Simpan'}
                    tooltip={processing ? 'Menyimpan…' : 'Simpan'}
                    disabled={busy}
                    loading={processing}
                />
                <TableActionButton
                    type="button"
                    icon={XIcon}
                    label="Batal"
                    tooltip="Batal"
                    variant="destructive"
                    disabled={busy}
                    onClick={onCancel}
                    href={cancelHref}
                />
            </div>
        );
    }

    return (
        <div className="flex items-center gap-2">
            <Button type="submit" disabled={busy} aria-busy={processing || undefined}>
                <HugeiconsIcon
                    icon={processing ? LoaderIcon : SaveIcon}
                    strokeWidth={2}
                    data-icon="inline-start"
                    className={processing ? 'animate-spin' : undefined}
                    aria-hidden="true"
                />
                {processing ? 'Menyimpan…' : 'Simpan'}
            </Button>
            <Button
                type="button"
                variant="destructive"
                disabled={busy}
                onClick={onCancel}
                nativeButton={!cancelHref}
                render={cancelHref ? <Link href={cancelHref} /> : undefined}
            >
                <HugeiconsIcon icon={XIcon} strokeWidth={2} data-icon="inline-start" aria-hidden="true" />
                Batal
            </Button>
        </div>
    );
}
