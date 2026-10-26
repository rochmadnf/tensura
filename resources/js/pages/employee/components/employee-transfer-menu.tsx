import { useRef, useState } from 'react';
import { Link } from '@inertiajs/react';
import { HugeiconsIcon } from '@hugeicons/react';
import {
    ArrowDown01Icon,
    Cancel01Icon,
    Download01Icon,
    Download04Icon,
    Upload01Icon,
    Xls01Icon,
} from '@hugeicons/core-free-icons';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Field, FieldDescription, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { OptionSelect } from '@/components/option-select';
import empl from '@/routes/empl';
import employeeImport from '@/routes/empl/import';

export function EmployeeTransferMenu() {
    const triggerRef = useRef<HTMLButtonElement>(null);
    const [open, setOpen] = useState(false);
    const [scope, setScope] = useState('all');

    function downloadUrl() {
        const current = new URLSearchParams(window.location.search);
        return empl.export({
            query: {
                scope,
                ...(scope === 'filtered'
                    ? { keyword: current.get('keyword') ?? '', filter: current.get('filter') ?? '' }
                    : {}),
            },
        }).url;
    }

    return (
        <>
            <DropdownMenu>
                <Tooltip>
                    <TooltipTrigger
                        render={<DropdownMenuTrigger render={<Button ref={triggerRef} variant="secondary" />} />}
                        aria-label="Impor/Ekspor"
                    >
                        <HugeiconsIcon icon={Xls01Icon} data-icon="inline-start" aria-hidden="true" />
                        <HugeiconsIcon icon={ArrowDown01Icon} data-icon="inline-end" aria-hidden="true" />
                    </TooltipTrigger>
                    <TooltipContent>Impor/Ekspor</TooltipContent>
                </Tooltip>
                <DropdownMenuContent align="end" className="w-fit">
                    <DropdownMenuGroup>
                        <DropdownMenuItem render={<Link href={employeeImport.create().url} />}>
                            <HugeiconsIcon icon={Upload01Icon} aria-hidden="true" />
                            Impor
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            onClick={() => {
                                setScope('all');
                                setOpen(true);
                            }}
                        >
                            <HugeiconsIcon icon={Download01Icon} aria-hidden="true" />
                            Ekspor
                        </DropdownMenuItem>
                    </DropdownMenuGroup>
                </DropdownMenuContent>
            </DropdownMenu>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent finalFocus={triggerRef}>
                    <DialogHeader>
                        <DialogTitle>Ekspor Data Pegawai</DialogTitle>
                        <DialogDescription>
                            Unduh data dalam format Excel yang sesuai dengan template impor.
                        </DialogDescription>
                    </DialogHeader>
                    <FieldGroup>
                        <Field>
                            <FieldLabel htmlFor="employee-export-scope">Data yang diekspor</FieldLabel>
                            <OptionSelect
                                id="employee-export-scope"
                                name="scope"
                                value={scope}
                                onValueChange={setScope}
                                items={[
                                    { value: 'all', label: 'Seluruh pegawai' },
                                    { value: 'filtered', label: 'Sesuai pencarian/filter aktif' },
                                ]}
                                placeholder="Pilih cakupan ekspor"
                                resetLabel="Reset cakupan ekspor"
                            />
                            <FieldDescription>
                                Hasil filter mencakup semua halaman, bukan hanya halaman yang sedang dibuka. Jika tidak
                                ada filter, hasilnya sama dengan seluruh pegawai.
                            </FieldDescription>
                        </Field>
                        <p className="text-sm">
                            Untuk impor ulang, bagi file sesuai batas pada halaman impor. Pegawai tanpa jabatan perlu
                            dilengkapi sebelum diimpor.
                        </p>
                    </FieldGroup>
                    <DialogFooter>
                        <DialogClose render={<Button variant="secondary" />}>
                            <HugeiconsIcon icon={Cancel01Icon} data-icon="inline-start" aria-hidden="true" />
                            Tutup
                        </DialogClose>
                        <Button
                            disabled={!scope}
                            onClick={() => {
                                if (!scope) return;
                                window.location.assign(downloadUrl());
                                setOpen(false);
                            }}
                        >
                            <HugeiconsIcon icon={Download04Icon} data-icon="inline-start" aria-hidden="true" />
                            Ekspor Excel
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
