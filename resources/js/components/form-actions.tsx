import { LoaderIcon, SaveIcon, XIcon } from '@hugeicons/core-free-icons';
import { TableActionButton } from '@/components/table-action-button';

export function FormActions({
    cancelUrl,
    processing,
    disabled = false,
    onCancel,
}: {
    cancelUrl: string;
    processing: boolean;
    disabled?: boolean;
    fullWidth?: boolean;
    onCancel?: () => void;
}) {
    const busy = processing || disabled;
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
                href={onCancel ? undefined : cancelUrl}
            />
        </div>
    );
}
