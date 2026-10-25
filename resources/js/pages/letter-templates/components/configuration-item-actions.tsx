import { Delete02Icon, Edit04Icon, Add01Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Button } from '@/components/ui/button';
import { TableActionButton } from '@/components/table-action-button';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { useState } from 'react';

export function AddConfigurationItem({
    label,
    disabled,
    onClick,
}: {
    label: string;
    disabled: boolean;
    onClick: () => void;
}) {
    return (
        <Button type="button" variant="default" size="sm" className="h-8.5" disabled={disabled} onClick={onClick}>
            <HugeiconsIcon icon={Add01Icon} data-icon="inline-start" aria-hidden="true" />
            {label}
        </Button>
    );
}

export function EditConfigurationItem({
    label,
    disabled,
    onClick,
}: {
    label: string;
    disabled: boolean;
    onClick: () => void;
}) {
    return (
        <TableActionButton
            type="button"
            variant="warning"
            icon={Edit04Icon}
            tooltip="Edit"
            label={`Edit ${label}`}
            disabled={disabled}
            onClick={onClick}
        />
    );
}

export function DeleteConfigurationItem({
    label,
    description,
    disabled,
    onDelete,
}: {
    label: string;
    description: string;
    disabled: boolean;
    onDelete: (close: () => void) => void;
}) {
    const [open, setOpen] = useState(false);
    return (
        <AlertDialog
            open={open}
            onOpenChange={(value) => {
                if (!disabled) setOpen(value);
            }}
        >
            <AlertDialogTrigger
                render={
                    <TableActionButton
                        type="button"
                        variant="destructive"
                        icon={Delete02Icon}
                        tooltip="Hapus"
                        label={`Hapus ${label}`}
                        disabled={disabled}
                    />
                }
            />
            <AlertDialogContent>
                <AlertDialogTitle>Hapus {label}?</AlertDialogTitle>
                <AlertDialogDescription>{description}</AlertDialogDescription>
                <AlertDialogFooter>
                    <AlertDialogCancel render={<Button variant="outline" disabled={disabled} />}>
                        Batal
                    </AlertDialogCancel>
                    <Button variant="destructive" disabled={disabled} onClick={() => onDelete(() => setOpen(false))}>
                        <HugeiconsIcon icon={Delete02Icon} data-icon="inline-start" aria-hidden="true" />
                        Hapus
                    </Button>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
