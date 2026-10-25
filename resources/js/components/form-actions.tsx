import { Link } from '@inertiajs/react';
import { HugeiconsIcon } from '@hugeicons/react';
import { LoaderIcon, SaveIcon, XIcon } from '@hugeicons/core-free-icons';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';

export function FormActions({
    cancelUrl,
    processing,
    disabled = false,
    fullWidth = true,
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
        <Field orientation="responsive">
            <Button className={fullWidth ? 'w-full' : undefined} type="submit" disabled={busy}>
                <HugeiconsIcon
                    icon={processing ? LoaderIcon : SaveIcon}
                    strokeWidth={2}
                    data-icon="inline-start"
                    className={processing ? 'animate-spin' : undefined}
                    aria-hidden="true"
                />
                Simpan
            </Button>
            <Button
                type="button"
                className={fullWidth ? 'w-full' : undefined}
                nativeButton={!!onCancel}
                variant="destructive"
                disabled={busy}
                onClick={onCancel}
                render={onCancel ? undefined : <Link href={cancelUrl} />}
            >
                <HugeiconsIcon icon={XIcon} strokeWidth={2} data-icon="inline-start" aria-hidden="true" />
                Batal
            </Button>
        </Field>
    );
}
