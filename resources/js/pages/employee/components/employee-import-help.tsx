import { useRef, useState } from 'react';
import { HugeiconsIcon } from '@hugeicons/react';
import { Cancel01Icon, Download01Icon } from '@hugeicons/core-free-icons';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type { ExcelImportInstructionSection } from '@/types/import-excel';
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
    instructionSections,
    hasJobTitles,
    processing,
}: {
    instructionSections: ExcelImportInstructionSection[];
    hasJobTitles: boolean;
    processing: boolean;
}) {
    const [open, setOpen] = useState(false);
    const triggerRef = useRef<HTMLButtonElement>(null);
    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <div className="flex flex-wrap gap-2">
                <Button
                    ref={triggerRef}
                    type="button"
                    variant="dark"
                    disabled={processing}
                    onClick={() => setOpen(true)}
                >
                    <HugeiconsIcon icon={Download01Icon} data-icon="inline-start" aria-hidden="true" />
                    Template &amp; Petunjuk
                </Button>
            </div>
            <DialogContent finalFocus={triggerRef} className="flex h-[85dvh] flex-col overflow-hidden sm:max-w-2xl">
                <DialogHeader className="shrink-0">
                    <DialogTitle>Template &amp; Petunjuk Impor</DialogTitle>
                    <DialogDescription>Isi template sesuai panduan berikut, lalu unggah file Excel.</DialogDescription>
                </DialogHeader>
                <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain p-1">
                    <div className="grid gap-3 sm:grid-cols-2">
                        {instructionSections.map((section) => (
                            <Card key={section.id} size="sm" className="last:sm:col-span-2">
                                <CardHeader>
                                    <CardTitle>
                                        <h3>{section.title}</h3>
                                    </CardTitle>
                                    <CardDescription>
                                        {section.id === 'import-behavior'
                                            ? 'Informasi proses'
                                            : section.id === 'data-format'
                                              ? 'Panduan pengisian'
                                              : 'Aturan'}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <ul className="flex list-disc flex-col gap-2 pl-4 text-sm leading-relaxed">
                                        {section.items.map((instruction) => (
                                            <li key={instruction}>{instruction}</li>
                                        ))}
                                    </ul>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                </div>
                <DialogFooter className="shrink-0">
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
                            Unduh Template
                        </Button>
                    ) : (
                        <Button disabled>
                            <HugeiconsIcon icon={Download01Icon} data-icon="inline-start" aria-hidden="true" />
                            Unduh Template
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
