import * as React from 'react';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
    DialogClose,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Badge } from '@/components/ui/badge';
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
import { Alert, AlertTitle, AlertDescription } from '@/components/reui/alert';
import { CalendarYear, HolidayPreview, HolidayItem } from '@/schemas/work-calendar';
import { formatFullDate, parseDateString } from '@/lib/calendar-date';
import { previewHolidays, applyHolidays, CalendarApiError } from '@/lib/work-calendar-api';
import { HugeiconsIcon } from '@hugeicons/react';
import { Download04Icon, Alert02Icon, CheckmarkCircle02Icon, CalendarCheck01Icon } from '@hugeicons/core-free-icons';

interface HolidayImportDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    year: number;
    calendarYear: CalendarYear | null;
    onApplied: () => void;
}

export function HolidayImportDialog({ open, onOpenChange, year, calendarYear, onApplied }: HolidayImportDialogProps) {
    const [includeCollectiveLeave, setIncludeCollectiveLeave] = React.useState(false);
    const [preview, setPreview] = React.useState<HolidayPreview | null>(null);
    const [selectedIds, setSelectedIds] = React.useState<string[]>([]);

    const [busy, setBusy] = React.useState(false);
    const [errorMessage, setErrorMessage] = React.useState<string | null>(null);
    const [successMessage, setSuccessMessage] = React.useState<string | null>(null);

    // Reset when modal opens/closes
    React.useEffect(() => {
        if (!open) {
            setPreview(null);
            setSelectedIds([]);
            setErrorMessage(null);
            setSuccessMessage(null);
            setIncludeCollectiveLeave(false);
        }
    }, [open]);

    // Handle fetching preview
    const handleFetchPreview = async () => {
        if (!calendarYear) {
            setErrorMessage(`Pengaturan tahun ${year} belum tersedia. Silakan inisialisasi tahun terlebih dahulu.`);
            return;
        }

        setBusy(true);
        setErrorMessage(null);
        setSuccessMessage(null);

        try {
            const result = await previewHolidays(year, {
                revision: calendarYear.revision,
                include_collective_leave: includeCollectiveLeave,
            });

            setPreview(result);

            // Default: select all items that are new and selectable
            const initialSelected = result.items.filter((item) => item.selectable).map((item) => item.id);
            setSelectedIds(initialSelected);
        } catch (err: unknown) {
            const apiError = err as CalendarApiError;
            setErrorMessage(apiError.message || 'Gagal mengambil pratinjau hari libur dari sumber resmi.');
        } finally {
            setBusy(false);
        }
    };

    // Toggle item selection
    const toggleItem = (item: HolidayItem) => {
        if (!item.selectable) return;
        setSelectedIds((prev) => (prev.includes(item.id) ? prev.filter((id) => id !== item.id) : [...prev, item.id]));
    };

    const selectAllSelectable = () => {
        if (!preview) return;
        const all = preview.items.filter((i) => i.selectable).map((i) => i.id);
        setSelectedIds(all);
    };

    const deselectAll = () => {
        setSelectedIds([]);
    };

    // Handle applying selected holidays
    const handleApply = async () => {
        if (!preview || selectedIds.length === 0) return;

        setBusy(true);
        setErrorMessage(null);
        setSuccessMessage(null);

        try {
            const result = await applyHolidays(year, {
                preview_id: preview.id,
                preview_token: preview.token,
                selected_ids: selectedIds,
            });

            setSuccessMessage(`Berhasil menambahkan ${result.created} hari libur ke kalender tahun ${year}.`);
            onApplied();
            setTimeout(() => {
                onOpenChange(false);
            }, 1200);
        } catch (err: unknown) {
            const apiError = err as CalendarApiError;
            setErrorMessage(apiError.message || 'Gagal menerapkan hari libur ke kalender.');
        } finally {
            setBusy(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-xl">
                <DialogHeader>
                    <DialogTitle>Ambil Hari Libur Nasional ({year})</DialogTitle>
                    <DialogDescription>
                        Impor daftar hari libur resmi Indonesia untuk tahun {year} secara langsung.
                    </DialogDescription>
                </DialogHeader>

                {errorMessage && (
                    <Alert variant="destructive" className="my-2">
                        <HugeiconsIcon icon={Alert02Icon} strokeWidth={2} />
                        <AlertTitle>Gagal</AlertTitle>
                        <AlertDescription>{errorMessage}</AlertDescription>
                    </Alert>
                )}

                {successMessage && (
                    <Alert variant="success" className="my-2">
                        <HugeiconsIcon icon={CheckmarkCircle02Icon} strokeWidth={2} />
                        <AlertTitle>Berhasil</AlertTitle>
                        <AlertDescription>{successMessage}</AlertDescription>
                    </Alert>
                )}

                {!preview ? (
                    /* Initial Preview Options Form */
                    <div className="flex flex-col gap-4 py-2">
                        <Field orientation="horizontal" className="flex items-center gap-3">
                            <Checkbox
                                id="include-collective-leave"
                                checked={includeCollectiveLeave}
                                onCheckedChange={(checked) => setIncludeCollectiveLeave(Boolean(checked))}
                                disabled={busy}
                            />
                            <div>
                                <FieldLabel htmlFor="include-collective-leave" className="cursor-pointer font-normal">
                                    Sertakan Cuti Bersama
                                </FieldLabel>
                                <FieldDescription>
                                    Secara default hanya libur nasional resmi yang diambil.
                                </FieldDescription>
                            </div>
                        </Field>

                        <div className="mt-4 flex justify-end gap-2">
                            <DialogClose
                                render={
                                    <Button type="button" variant="outline" disabled={busy}>
                                        Batal
                                    </Button>
                                }
                            />
                            <Button type="button" onClick={handleFetchPreview} disabled={busy}>
                                <HugeiconsIcon icon={Download04Icon} strokeWidth={2} data-icon="inline-start" />
                                {busy ? 'Mengambil Data…' : 'Pratinjau Hari Libur'}
                            </Button>
                        </div>
                    </div>
                ) : (
                    /* Preview & Selection Table */
                    <div className="flex flex-col gap-4">
                        <div className="flex items-center justify-between border-b pb-2 text-xs">
                            <span className="font-medium text-muted-foreground">
                                {selectedIds.length} dari {preview.items.filter((i) => i.selectable).length} hari libur
                                dipilih
                            </span>
                            <div className="flex items-center gap-2">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={selectAllSelectable}
                                    disabled={busy}
                                >
                                    Pilih Semua
                                </Button>
                                <Button type="button" variant="ghost" size="sm" onClick={deselectAll} disabled={busy}>
                                    Hapus Pilihan
                                </Button>
                            </div>
                        </div>

                        {/* List of holidays */}
                        <div className="max-h-[320px] divide-y overflow-y-auto rounded-md border">
                            {preview.items.map((item) => {
                                const isChecked = selectedIds.includes(item.id);
                                const dateObj = parseDateString(item.date);

                                return (
                                    <div
                                        key={item.id}
                                        className="flex items-start gap-3 p-3 transition-colors hover:bg-muted/30"
                                    >
                                        <Checkbox
                                            id={`holiday-${item.id}`}
                                            checked={isChecked}
                                            onCheckedChange={() => toggleItem(item)}
                                            disabled={busy || !item.selectable}
                                            className="mt-0.5"
                                        />
                                        <div className="flex flex-1 flex-col gap-0.5">
                                            <div className="flex items-center justify-between gap-2">
                                                <FieldLabel
                                                    htmlFor={`holiday-${item.id}`}
                                                    className="cursor-pointer text-sm leading-tight font-medium"
                                                >
                                                    {item.name}
                                                </FieldLabel>
                                                {item.decision === 'new' && (
                                                    <Badge variant="success" size="sm">
                                                        Baru
                                                    </Badge>
                                                )}
                                                {item.decision === 'duplicate' && (
                                                    <Badge variant="secondary" size="sm">
                                                        Sudah Ada
                                                    </Badge>
                                                )}
                                                {item.decision === 'conflict' && (
                                                    <Badge variant="destructive" size="sm">
                                                        Konflik
                                                    </Badge>
                                                )}
                                            </div>
                                            <span className="text-xs text-muted-foreground">
                                                {formatFullDate(dateObj)}
                                            </span>
                                            {item.reason && (
                                                <span className="text-xs text-destructive">
                                                    {item.reason === 'manual_working_day'
                                                        ? 'Terdapat hari kerja pengganti manual pada tanggal ini.'
                                                        : item.reason === 'source_changed'
                                                          ? 'Nama hari libur di sistem berbeda dari pembaruan sumber.'
                                                          : item.reason === 'existing_holiday'
                                                            ? 'Hari libur sudah tersimpan sebelumnya.'
                                                            : item.reason}
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>

                        <DialogFooter className="mt-2 flex items-center justify-between">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => setPreview(null)}
                                disabled={busy}
                            >
                                Kembali
                            </Button>
                            <div className="flex items-center gap-2">
                                <DialogClose
                                    render={
                                        <Button type="button" variant="outline" disabled={busy}>
                                            Batal
                                        </Button>
                                    }
                                />
                                <Button type="button" onClick={handleApply} disabled={busy || selectedIds.length === 0}>
                                    <HugeiconsIcon
                                        icon={CalendarCheck01Icon}
                                        strokeWidth={2}
                                        data-icon="inline-start"
                                    />
                                    {busy ? 'Menerapkan…' : `Terapkan (${selectedIds.length}) Libur`}
                                </Button>
                            </div>
                        </DialogFooter>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
