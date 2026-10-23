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
import jt from '@/routes/jt';
import { Delete02Icon, LoaderIcon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { JobTitleColumns } from './column';

export function DeleteAction({ jobTitle }: { jobTitle: JobTitleColumns }) {
    const [open, setOpen] = useState(false);
    const { delete: destroy, processing } = useForm({});

    const handleDelete = () => {
        if (processing) {
            return;
        }

        destroy(jt.destroy(jobTitle.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                gooeyToast.success('Berhasil', {
                    description: `${jobTitle.name} berhasil dihapus.`,
                });
            },
            onError: () => {
                gooeyToast.error('Galat', {
                    description: 'Jabatan gagal dihapus. Silakan coba lagi.',
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
                            aria-label={`Hapus ${jobTitle.name}`}
                            render={<TooltipTrigger />}
                        />
                    }
                >
                    <HugeiconsIcon icon={Delete02Icon} strokeWidth={2} />
                </AlertDialogTrigger>
                <TooltipContent>Hapus</TooltipContent>
            </Tooltip>
            <AlertDialogContent>
                <AlertDialogTitle>Hapus Jabatan?</AlertDialogTitle>
                <AlertDialogDescription>
                    Apakah Anda yakin ingin menghapus <strong>{jobTitle.name}</strong>? Data yang dihapus tidak dapat
                    dikembalikan.
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
