import { useState } from 'react';
import { HugeiconsIcon } from '@hugeicons/react';
import { Cancel01Icon, Download01Icon } from '@hugeicons/core-free-icons';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import employeeImport from '@/routes/empl/import';

export function EmployeeImportHelp({
    instructions,
    hasJobTitles,
    processing,
}: {
    instructions: string[];
    hasJobTitles: boolean;
    processing: boolean;
}) {
    const [open, setOpen] = useState(false);
    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <div className="flex flex-wrap gap-2">
                {hasJobTitles ? (
                    <Button type="button" variant="dark" disabled={processing} onClick={() => setOpen(true)}>
                        <HugeiconsIcon icon={Download01Icon} data-icon="inline-start" aria-hidden="true" />
                        Unduh Templat
                    </Button>
                ) : null}
            </div>
            <DialogContent className="max-h-[85dvh] grid-rows-[auto_minmax(0,1fr)_auto] sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Petunjuk Impor Pegawai</DialogTitle>
                    <DialogDescription>Pelajari cara pengisian sebelum mengunggah data pegawai.</DialogDescription>
                </DialogHeader>
                <div className="min-h-0 overflow-y-auto">
                    <ul className="flex list-disc flex-col gap-3 pl-5 text-sm leading-relaxed">
                        {instructions.map((instruction) => (
                            <li key={instruction}>{instruction}</li>
                        ))}
                        <li>
                            Setelah proses selesai, periksa ringkasan dan alasan setiap baris yang dilewati. Perbaiki
                            data lalu unggah ulang.
                        </li>
                    </ul>
                </div>
                <DialogFooter>
                    <DialogClose render={<Button type="button" variant="secondary" />}>
                        <HugeiconsIcon icon={Cancel01Icon} data-icon="inline-start" aria-hidden="true" />
                        Tutup
                    </DialogClose>
                    {hasJobTitles && !processing ? (
                        <Button
                            variant="dark"
                            nativeButton={false}
                            render={<a href={employeeImport.template().url} download />}
                            onClick={() => setOpen(false)}
                        >
                            <HugeiconsIcon icon={Download01Icon} data-icon="inline-start" aria-hidden="true" />
                            Unduh Templat
                        </Button>
                    ) : (
                        <Button disabled>
                            <HugeiconsIcon icon={Download01Icon} data-icon="inline-start" aria-hidden="true" />
                            Unduh Templat
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
