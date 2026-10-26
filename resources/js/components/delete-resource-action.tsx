import { useRef, useState, type ReactNode } from 'react';
import { useForm } from '@inertiajs/react';
import { Delete02Icon, LoaderIcon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { gooeyToast } from '@/components/ui/goey-toaster';
import { TableActionButton } from '@/components/table-action-button';

interface DeleteResourceActionProps {
    url: string;
    name: string;
    title: string;
    description: ReactNode;
    successMessage: string;
    errorMessage: string;
    data?: Record<string, string | number | boolean>;
    disabled?: boolean;
    onOpenChange?: (value: boolean) => void;
}

export function DeleteResourceAction({
    url,
    name,
    title,
    description,
    successMessage,
    errorMessage,
    data = {},
    disabled = false,
    onOpenChange,
}: DeleteResourceActionProps) {
    const [open, setOpen] = useState(false);
    const operationLock = useRef(false);
    const { delete: destroy, processing } = useForm(data);

    function handleDelete() {
        if (operationLock.current || disabled) return;
        operationLock.current = true;
        destroy(url, {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                onOpenChange?.(false);
                gooeyToast.success('Berhasil', { description: successMessage });
            },
            onError: (errors) =>
                gooeyToast.error('Galat', { description: errors.deletion ?? Object.values(errors)[0] ?? errorMessage }),
            onFinish: () => {
                operationLock.current = false;
            },
        });
    }

    return (
        <AlertDialog
            open={open}
            onOpenChange={(value) => {
                if (!operationLock.current) {
                    setOpen(value);
                    onOpenChange?.(value);
                }
            }}
        >
            <AlertDialogTrigger
                render={
                    <TableActionButton
                        variant="destructive"
                        icon={Delete02Icon}
                        disabled={processing || disabled}
                        label={`Hapus ${name}`}
                        tooltip="Hapus"
                    />
                }
            />
            <AlertDialogContent>
                <AlertDialogTitle>{title}</AlertDialogTitle>
                <AlertDialogDescription>{description}</AlertDialogDescription>
                <AlertDialogFooter>
                    <AlertDialogCancel render={<Button variant="outline" disabled={processing || disabled} />}>
                        Batal
                    </AlertDialogCancel>
                    <Button variant="destructive" onClick={handleDelete} disabled={processing || disabled}>
                        <HugeiconsIcon
                            icon={processing ? LoaderIcon : Delete02Icon}
                            strokeWidth={2}
                            data-icon="inline-start"
                            className={processing ? 'animate-spin' : undefined}
                        />
                        {processing ? 'Menghapus...' : 'Hapus'}
                    </Button>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
