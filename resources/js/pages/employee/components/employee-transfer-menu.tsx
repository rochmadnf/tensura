import { useRef, useState } from 'react';
import { Link } from '@inertiajs/react';
import { HugeiconsIcon } from '@hugeicons/react';
import {
    ArrowDown01Icon,
    Cancel01Icon,
    Download01Icon,
    Download04Icon,
    InformationCircleIcon,
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
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Alert, AlertDescription, AlertTitle } from '@/components/reui/alert';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { OptionSelect } from '@/components/option-select';
import empl from '@/routes/empl';
import employeeImport from '@/routes/empl/import';

export function EmployeeTransferMenu() {
    const triggerRef = useRef<HTMLButtonElement>(null);
    const [open, setOpen] = useState(false);
    const [scope, setScope] = useState('all');
    const [helpOpen, setHelpOpen] = useState(false);

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
                                setHelpOpen(false);
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
                <DialogContent finalFocus={triggerRef} className="flex max-h-[85dvh] flex-col">
                    <DialogHeader className="shrink-0">
                        <DialogTitle>Ekspor Data Pegawai</DialogTitle>
                        <DialogDescription>Pilih cakupan data untuk diunduh sebagai Excel.</DialogDescription>
                    </DialogHeader>
                    <FieldGroup className="min-h-0 gap-2.5 overflow-y-auto">
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
                        </Field>
                        <Collapsible open={helpOpen} onOpenChange={setHelpOpen}>
                            <CollapsibleTrigger
                                render={<Button type="button" variant="link" className="justify-start px-0" />}
                            >
                                Petunjuk
                            </CollapsibleTrigger>
                            <CollapsibleContent>
                                <div className="flex flex-col gap-3 pt-2">
                                    {scope && (
                                        <Alert variant="info" role="note">
                                            <HugeiconsIcon icon={InformationCircleIcon} aria-hidden="true" />
                                            <AlertTitle>
                                                {scope === 'all' ? 'Seluruh Pegawai' : 'Sesuai Pencarian/Filter'}
                                            </AlertTitle>
                                            <AlertDescription>
                                                {scope === 'all'
                                                    ? 'Anda akan mendapatkan seluruh data pegawai dalam satu file Excel, tanpa mengikuti pencarian, filter, atau halaman yang sedang dibuka.'
                                                    : 'Anda akan mendapatkan data pegawai yang sesuai dengan pencarian dan filter aktif dari semua halaman. Jika tidak ada pencarian atau filter, hasilnya mencakup seluruh pegawai.'}
                                            </AlertDescription>
                                        </Alert>
                                    )}
                                    <Alert variant="warning" role="note">
                                        <HugeiconsIcon icon={Upload01Icon} aria-hidden="true" />
                                        <AlertTitle>Impor Ulang</AlertTitle>
                                        <AlertDescription>
                                            File hasil ekspor menggunakan format template impor. Bagi file sesuai batas
                                            pada halaman impor dan lengkapi jabatan yang kosong sebelum mengunggahnya
                                            kembali. Data pegawai yang sudah ada tidak diperbarui.
                                        </AlertDescription>
                                    </Alert>
                                </div>
                            </CollapsibleContent>
                        </Collapsible>
                    </FieldGroup>
                    <DialogFooter className="shrink-0">
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
