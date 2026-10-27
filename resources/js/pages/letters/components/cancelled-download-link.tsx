import { useState, type ComponentProps } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export function CancelledDownloadLink({ cancelled, ...props }: ComponentProps<'a'> & { cancelled: boolean }) {
    const [open, setOpen] = useState(false);
    return (
        <>
            <a
                {...props}
                onClick={(event) => {
                    if (cancelled) {
                        event.preventDefault();
                        event.stopPropagation();
                        setOpen(true);
                    } else props.onClick?.(event);
                }}
            />
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Unduh Dokumen Surat Batal?</DialogTitle>
                        <DialogDescription>
                            Surat ini telah dibatalkan. Dokumen yang diunduh merupakan arsip versi yang pernah
                            diterbitkan.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setOpen(false)}>
                            Kembali
                        </Button>
                        <Button nativeButton={false} render={<a href={props.href} />} onClick={() => setOpen(false)}>
                            Saya memahami, unduh
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
