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
import ln from '@/routes/ln';
import { Delete02Icon, LoaderIcon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { LetterNumberColumns } from './column';

export function DeleteAction({ letterNumber }: { letterNumber: LetterNumberColumns }) {
    const [open, setOpen] = useState(false);
    const { delete: destroy, processing } = useForm({});

    const handleDelete = () => {
        if (processing) {
            return;
        }

        destroy(ln.destroy(letterNumber.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                gooeyToast.success('Berhasil', {
                    description: `${letterNumber.name} berhasil dihapus.`,
                });
            },
            onError: () => {
                gooeyToast.error('Galat', {
                    description: 'Penomoran surat gagal dihapus. Silakan coba lagi.',
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
                            aria-label={`Hapus ${letterNumber.name}`}
                            render={<TooltipTrigger />}
                        />
                    }
                >
                    <HugeiconsIcon icon={Delete02Icon} strokeWidth={2} />
                </AlertDialogTrigger>
                <TooltipContent>Hapus</TooltipContent>
            </Tooltip>
            <AlertDialogContent>
                <AlertDialogTitle>Hapus Penomoran Surat?</AlertDialogTitle>
                <AlertDialogDescription>
                    Apakah Anda yakin ingin menghapus <strong>{letterNumber.name}</strong> ({letterNumber.code})? Data
                    yang dihapus tidak dapat dikembalikan.
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
