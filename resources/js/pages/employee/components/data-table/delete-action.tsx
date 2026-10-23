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
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import empl from '@/routes/empl';
import { Delete02Icon, LoaderIcon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { EmployeeResource } from '@/schemas/employee';

export function DeleteAction({ employee }: { employee: EmployeeResource }) {
    const [open, setOpen] = useState(false);
    const { delete: destroy, processing } = useForm({});

    const handleDelete = () => {
        if (processing) {
            return;
        }

        destroy(empl.destroy(employee.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                gooeyToast.success('Berhasil', {
                    description: `${employee.name.full} berhasil dihapus.`,
                });
            },
            onError: () => {
                gooeyToast.error('Galat', {
                    description: 'Pegawai gagal dihapus. Silakan coba lagi.',
                });
            },
        });
    };

    return (
        <AlertDialog
            open={open}
            onOpenChange={(nextOpen) => {
                if (!processing) {
                    setOpen(nextOpen);
                }
            }}
        >
            <Tooltip>
                <AlertDialogTrigger
                    render={
                        <Button
                            variant="destructive"
                            size="icon-sm"
                            disabled={processing}
                            aria-label={`Hapus ${employee.name.full}`}
                            render={<TooltipTrigger />}
                        />
                    }
                >
                    <HugeiconsIcon icon={Delete02Icon} strokeWidth={2} />
                </AlertDialogTrigger>
                <TooltipContent>Hapus</TooltipContent>
            </Tooltip>
            <AlertDialogContent>
                <AlertDialogTitle>Hapus Pegawai?</AlertDialogTitle>
                <AlertDialogDescription>
                    Apakah Anda yakin ingin menghapus <strong>{employee.name.full}</strong>? Data yang dihapus tidak
                    dapat dikembalikan.
                </AlertDialogDescription>
                <AlertDialogFooter>
                    <AlertDialogCancel render={<Button variant="outline" disabled={processing} />}>
                        Batal
                    </AlertDialogCancel>
                    <Button variant="destructive" onClick={handleDelete} disabled={processing}>
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
