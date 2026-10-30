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
import { OptionSelect } from '@/components/option-select';
import { Field, FieldLabel, FieldDescription, FieldError, FieldGroup } from '@/components/ui/field';
import { Alert, AlertTitle, AlertDescription } from '@/components/reui/alert';
import { CalendarYear } from '@/schemas/work-calendar';
import { INDONESIAN_WEEKDAYS } from '@/lib/calendar-date';
import { storeCalendarYear, updateCalendarYear, confirmCalendarYear, CalendarApiError } from '@/lib/work-calendar-api';
import { HugeiconsIcon } from '@hugeicons/react';
import { CheckmarkCircle02Icon, Alert02Icon, FloppyDiskIcon, CalendarCheck01Icon } from '@hugeicons/core-free-icons';

interface YearSettingsDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    year: number;
    calendarYear: CalendarYear | null;
    allCalendarYears: CalendarYear[];
    onSaved: () => void;
}

export function YearSettingsDialog({
    open,
    onOpenChange,
    year,
    calendarYear,
    allCalendarYears,
    onSaved,
}: YearSettingsDialogProps) {
    const isConfigured = calendarYear !== null;

    // Form state for creating new year
    const [copyFromYear, setCopyFromYear] = React.useState<string>('');

    // Form state for updating existing year
    const [selectedWeekdays, setSelectedWeekdays] = React.useState<number[]>([1, 2, 3, 4, 5, 6]);

    const [busy, setBusy] = React.useState(false);
    const [errorMessage, setErrorMessage] = React.useState<string | null>(null);
    const [successMessage, setSuccessMessage] = React.useState<string | null>(null);

    // Initialize state
    React.useEffect(() => {
        if (!open) {
            setErrorMessage(null);
            setSuccessMessage(null);
            return;
        }

        if (calendarYear) {
            setSelectedWeekdays([...calendarYear.working_weekdays]);
        } else {
            // Find if there's a previous year available
            const prev = allCalendarYears.find((y) => y.year === year - 1);
            setCopyFromYear(prev ? String(prev.year) : '');
            setSelectedWeekdays([1, 2, 3, 4, 5, 6]);
        }
        setErrorMessage(null);
        setSuccessMessage(null);
    }, [open, calendarYear, year, allCalendarYears]);

    const handleCreateYear = async (e: React.FormEvent) => {
        e.preventDefault();
        setBusy(true);
        setErrorMessage(null);
        setSuccessMessage(null);

        try {
            await storeCalendarYear({
                year,
                copy_from_year: copyFromYear ? parseInt(copyFromYear, 10) : null,
            });
            setSuccessMessage(`Pengaturan tahun ${year} berhasil dibuat.`);
            onSaved();
        } catch (err: unknown) {
            const apiError = err as CalendarApiError;
            setErrorMessage(apiError.message || 'Gagal membuat pengaturan tahun.');
        } finally {
            setBusy(false);
        }
    };

    const handleUpdateWeekdays = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!calendarYear) return;

        if (selectedWeekdays.length === 0) {
            setErrorMessage('Pilih minimal satu hari kerja dalam seminggu.');
            return;
        }

        setBusy(true);
        setErrorMessage(null);
        setSuccessMessage(null);

        try {
            await updateCalendarYear(year, {
                revision: calendarYear.revision,
                working_weekdays: selectedWeekdays,
            });
            setSuccessMessage(`Pola hari kerja tahun ${year} berhasil disimpan.`);
            onSaved();
        } catch (err: unknown) {
            const apiError = err as CalendarApiError;
            setErrorMessage(apiError.message || 'Gagal memperbarui pola hari kerja.');
        } finally {
            setBusy(false);
        }
    };

    const handleConfirmYear = async () => {
        if (!calendarYear) return;
        setBusy(true);
        setErrorMessage(null);
        setSuccessMessage(null);

        try {
            await confirmCalendarYear(year, {
                revision: calendarYear.revision,
            });
            setSuccessMessage(`Kesiapan kalender tahun ${year} berhasil dikonfirmasi.`);
            onSaved();
        } catch (err: unknown) {
            const apiError = err as CalendarApiError;
            setErrorMessage(apiError.message || 'Gagal mengonfirmasi kesiapan kalender.');
        } finally {
            setBusy(false);
        }
    };

    const toggleWeekday = (id: number) => {
        setSelectedWeekdays((prev) =>
            prev.includes(id) ? prev.filter((d) => d !== id) : [...prev, id].sort((a, b) => a - b),
        );
    };

    const isConfirmed = calendarYear?.status === 'ready';

    const availableYearsToCopy = allCalendarYears
        .filter((y) => y.year !== year)
        .map((y) => ({
            value: String(y.year),
            label: `Tahun ${y.year}`,
        }));

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Pengaturan Kalender Tahun {year}</DialogTitle>
                    <DialogDescription>
                        Kelola pola hari kerja mingguan dan konfirmasi kesiapan kalender tahun {year}.
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

                {!isConfigured ? (
                    /* Initial Year Setup Form */
                    <form onSubmit={handleCreateYear} className="flex flex-col gap-4">
                        <Alert variant="warning">
                            <HugeiconsIcon icon={Alert02Icon} strokeWidth={2} />
                            <AlertTitle>Tahun Belum Dibuat</AlertTitle>
                            <AlertDescription>
                                Kalender tahun {year} belum diinisialisasi di sistem. Buat kalender tahun ini untuk
                                mulai mengatur hari kerja dan jadwal kegiatan.
                            </AlertDescription>
                        </Alert>

                        {availableYearsToCopy.length > 0 && (
                            <Field>
                                <FieldLabel>Salin dari Tahun Lain (Opsional)</FieldLabel>
                                <OptionSelect
                                    id="copy-from-year"
                                    name="copy_from_year"
                                    items={availableYearsToCopy}
                                    value={copyFromYear}
                                    onValueChange={setCopyFromYear}
                                    placeholder="Gunakan pengaturan default (Senin–Sabtu)"
                                    disabled={busy}
                                />
                                <FieldDescription>
                                    Menyalin pola hari kerja dari tahun yang sudah ada sebelumnya.
                                </FieldDescription>
                            </Field>
                        )}

                        <DialogFooter className="mt-4">
                            <DialogClose
                                render={
                                    <Button type="button" variant="outline" disabled={busy}>
                                        Batal
                                    </Button>
                                }
                            />
                            <Button type="submit" disabled={busy}>
                                <HugeiconsIcon icon={FloppyDiskIcon} strokeWidth={2} data-icon="inline-start" />
                                {busy ? 'Membuat…' : `Inisialisasi Tahun ${year}`}
                            </Button>
                        </DialogFooter>
                    </form>
                ) : (
                    /* Existing Year Settings */
                    <div className="flex flex-col gap-5">
                        <form onSubmit={handleUpdateWeekdays} className="flex flex-col gap-4">
                            <FieldGroup>
                                <Field>
                                    <FieldLabel>Hari Kerja Mingguan</FieldLabel>
                                    <FieldDescription>
                                        Pilih hari yang dihitung sebagai hari kerja reguler pada tahun {year}.
                                    </FieldDescription>
                                    <div className="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                                        {INDONESIAN_WEEKDAYS.map((w) => {
                                            const checked = selectedWeekdays.includes(w.id);
                                            return (
                                                <div
                                                    key={w.id}
                                                    className="flex items-center gap-2 rounded-md border p-2.5"
                                                >
                                                    <Checkbox
                                                        id={`weekday-${w.id}`}
                                                        checked={checked}
                                                        onCheckedChange={() => toggleWeekday(w.id)}
                                                        disabled={busy}
                                                    />
                                                    <FieldLabel
                                                        htmlFor={`weekday-${w.id}`}
                                                        className="cursor-pointer font-normal"
                                                    >
                                                        {w.name}
                                                    </FieldLabel>
                                                </div>
                                            );
                                        })}
                                    </div>
                                    {selectedWeekdays.length === 0 && (
                                        <FieldError>Minimal 1 hari kerja harus dipilih.</FieldError>
                                    )}
                                </Field>
                            </FieldGroup>

                            <div className="flex justify-end">
                                <Button type="submit" disabled={busy} size="sm">
                                    <HugeiconsIcon icon={FloppyDiskIcon} strokeWidth={2} data-icon="inline-start" />
                                    {busy ? 'Menyimpan…' : 'Simpan Pola Hari Kerja'}
                                </Button>
                            </div>
                        </form>

                        <div className="border-t pt-4">
                            <h4 className="text-sm font-semibold">Konfirmasi Kesiapan Kalender</h4>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Konfirmasi kesiapan menandai bahwa pengaturan hari kerja tahun ini telah siap dijadikan
                                dasar perhitungan resmi hari kerja pada surat.
                            </p>

                            {isConfirmed ? (
                                <Alert variant="success" className="mt-3">
                                    <HugeiconsIcon icon={CheckmarkCircle02Icon} strokeWidth={2} />
                                    <AlertTitle>Telah Dikonfirmasi</AlertTitle>
                                    <AlertDescription>
                                        Kalender telah dikonfirmasi oleh{' '}
                                        {calendarYear.confirmed_by?.name || 'Administrator'} pada{' '}
                                        {calendarYear.confirmed_at
                                            ? new Date(calendarYear.confirmed_at).toLocaleDateString('id-ID', {
                                                  day: 'numeric',
                                                  month: 'long',
                                                  year: 'numeric',
                                                  hour: '2-digit',
                                                  minute: '2-digit',
                                              })
                                            : '—'}{' '}
                                        (Revisi kerja #{calendarYear.work_revision}).
                                    </AlertDescription>
                                </Alert>
                            ) : (
                                <Alert variant="warning" className="mt-3">
                                    <HugeiconsIcon icon={Alert02Icon} strokeWidth={2} />
                                    <AlertTitle>Belum Dikonfirmasi</AlertTitle>
                                    <AlertDescription>
                                        {calendarYear.confirmed_at === null
                                            ? 'Kalender tahun ini belum pernah dikonfirmasi.'
                                            : 'Terdapat perubahan pola atau pengecualian kerja sejak konfirmasi terakhir.'}
                                    </AlertDescription>
                                </Alert>
                            )}

                            <div className="mt-3 flex justify-end">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={handleConfirmYear}
                                    disabled={busy || isConfirmed}
                                >
                                    <HugeiconsIcon
                                        icon={CalendarCheck01Icon}
                                        strokeWidth={2}
                                        data-icon="inline-start"
                                    />
                                    {busy ? 'Mengonfirmasi…' : 'Konfirmasi Kesiapan Kalender'}
                                </Button>
                            </div>
                        </div>

                        <DialogFooter className="mt-2">
                            <DialogClose
                                render={
                                    <Button type="button" variant="outline" disabled={busy}>
                                        Tutup
                                    </Button>
                                }
                            />
                        </DialogFooter>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
