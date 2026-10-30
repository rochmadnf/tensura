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
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import { OptionSelect } from '@/components/option-select';
import { DatePickerInput } from '@/components/date-picker-input';
import { Field, FieldLabel, FieldError, FieldGroup } from '@/components/ui/field';
import { Alert, AlertTitle, AlertDescription } from '@/components/reui/alert';
import { CalendarEvent, CalendarYear } from '@/schemas/work-calendar';
import {
    storeCalendarEvent,
    updateCalendarEvent,
    destroyCalendarEvent,
    CalendarApiError,
} from '@/lib/work-calendar-api';
import { HugeiconsIcon } from '@hugeicons/react';
import { Alert02Icon, Delete02Icon, FloppyDiskIcon } from '@hugeicons/core-free-icons';

interface EventDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    event: CalendarEvent | null;
    initialDate?: string;
    initialHour?: string;
    calendarYears: CalendarYear[];
    onSaved: () => void;
    onDeleted?: () => void;
    onRefreshCalendar: () => Promise<void>;
}

const KIND_OPTIONS = [
    { value: 'activity', label: 'Kegiatan Biasa' },
    { value: 'holiday', label: 'Hari Libur' },
    { value: 'working_day', label: 'Hari Kerja Pengganti' },
];

export function EventDialog({
    open,
    onOpenChange,
    event,
    initialDate,
    initialHour,
    calendarYears,
    onSaved,
    onDeleted,
    onRefreshCalendar,
}: EventDialogProps) {
    const isEdit = event !== null;

    const [title, setTitle] = React.useState('');
    const [kind, setKind] = React.useState<'activity' | 'holiday' | 'working_day'>('activity');
    const [startDate, setStartDate] = React.useState('');
    const [endDate, setEndDate] = React.useState('');
    const [allDay, setAllDay] = React.useState(true);
    const [startTime, setStartTime] = React.useState('');
    const [endTime, setEndTime] = React.useState('');
    const [location, setLocation] = React.useState('');
    const [description, setDescription] = React.useState('');

    const [busy, setBusy] = React.useState(false);
    const [errorMessage, setErrorMessage] = React.useState<string | null>(null);
    const [fieldErrors, setFieldErrors] = React.useState<Record<string, string[]>>({});
    const [hasConflict, setHasConflict] = React.useState(false);
    const [showDeleteConfirm, setShowDeleteConfirm] = React.useState(false);

    // Initialize or reset form when dialog opens or event changes
    React.useEffect(() => {
        if (!open) {
            setErrorMessage(null);
            setFieldErrors({});
            setHasConflict(false);
            setShowDeleteConfirm(false);
            return;
        }

        if (event) {
            setTitle(event.title);
            setKind(event.kind);
            setStartDate(event.start_date);
            setEndDate(event.end_date);
            setAllDay(event.all_day);
            setStartTime(event.start_time ?? '');
            setEndTime(event.end_time ?? '');
            setLocation(event.location ?? '');
            setDescription(event.description ?? '');
        } else {
            const today = initialDate || new Date().toISOString().slice(0, 10);
            setTitle('');
            setKind('activity');
            setStartDate(today);
            setEndDate(today);
            if (initialHour) {
                setAllDay(false);
                setStartTime(initialHour);
                const hourNum = parseInt(initialHour.split(':')[0], 10);
                setEndTime(`${String((hourNum + 1) % 24).padStart(2, '0')}:00`);
            } else {
                setAllDay(true);
                setStartTime('');
                setEndTime('');
            }
            setLocation('');
            setDescription('');
        }
        setErrorMessage(null);
        setFieldErrors({});
        setHasConflict(false);
        setShowDeleteConfirm(false);
    }, [open, event, initialDate, initialHour]);

    // Build calendar revisions mapping for affected years
    const buildRevisionsMap = React.useCallback(
        (start: string, end: string): Record<string, number> => {
            const startY = parseInt(start.slice(0, 4), 10) || new Date().getFullYear();
            const endY = parseInt(end.slice(0, 4), 10) || startY;
            const map: Record<string, number> = {};
            for (let y = startY; y <= endY; y++) {
                const yearData = calendarYears.find((item) => item.year === y);
                if (yearData) {
                    map[String(y)] = yearData.revision;
                }
            }
            return map;
        },
        [calendarYears],
    );

    const handleSave = async (e: React.FormEvent) => {
        e.preventDefault();
        setBusy(true);
        setErrorMessage(null);
        setFieldErrors({});
        setHasConflict(false);

        try {
            const revisions = buildRevisionsMap(startDate, endDate);
            const payload = {
                title,
                kind,
                start_date: startDate,
                end_date: endDate,
                all_day: allDay,
                start_time: allDay ? null : startTime || null,
                end_time: allDay ? null : endTime || null,
                location: location || null,
                description: description || null,
                calendar_revisions: revisions,
            };

            if (isEdit && event) {
                await updateCalendarEvent(event.id, {
                    ...payload,
                    revision: event.revision,
                });
            } else {
                await storeCalendarEvent(payload);
            }

            onSaved();
            onOpenChange(false);
        } catch (err: unknown) {
            const apiError = err as CalendarApiError;
            if (apiError.code === 'revision_conflict') {
                setHasConflict(true);
                setErrorMessage(
                    apiError.message || 'Data kalender telah berubah di server. Segarkan data sebelum menyimpan.',
                );
            } else if (apiError.code === 'calendar_exception_conflict') {
                setErrorMessage(apiError.message || 'Ada pengecualian pada tanggal yang sama.');
            } else if (apiError.message) {
                setErrorMessage(apiError.message);
            } else {
                setErrorMessage('Terjadi kesalahan saat menyimpan kegiatan.');
            }
            if (apiError.errors) {
                setFieldErrors(apiError.errors);
            }
        } finally {
            setBusy(false);
        }
    };

    const handleDelete = async () => {
        if (!event) return;
        setBusy(true);
        setErrorMessage(null);
        setFieldErrors({});

        try {
            const revisions = buildRevisionsMap(event.start_date, event.end_date);
            await destroyCalendarEvent(event.id, {
                revision: event.revision,
                calendar_revisions: revisions,
            });
            onDeleted?.();
            onOpenChange(false);
        } catch (err: unknown) {
            const apiError = err as CalendarApiError;
            if (apiError.code === 'revision_conflict') {
                setHasConflict(true);
                setErrorMessage(apiError.message || 'Data kalender telah berubah. Segarkan data sebelum menghapus.');
            } else if (apiError.message) {
                setErrorMessage(apiError.message);
            } else {
                setErrorMessage('Terjadi kesalahan saat menghapus kegiatan.');
            }
        } finally {
            setBusy(false);
        }
    };

    const handleRefreshData = async () => {
        setBusy(true);
        try {
            await onRefreshCalendar();
            setHasConflict(false);
            setErrorMessage(null);
        } catch {
            setErrorMessage('Gagal memuat versi kalender terbaru.');
        } finally {
            setBusy(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>{isEdit ? 'Ubah Kegiatan' : 'Tambah Kegiatan'}</DialogTitle>
                    <DialogDescription>
                        {isEdit
                            ? 'Perbarui rincian kegiatan atau hari libur pada kalender.'
                            : 'Tambahkan kegiatan baru, hari libur, atau hari kerja pengganti.'}
                    </DialogDescription>
                </DialogHeader>

                {errorMessage && (
                    <Alert variant={hasConflict ? 'warning' : 'destructive'} className="my-2">
                        <HugeiconsIcon icon={Alert02Icon} strokeWidth={2} />
                        <AlertTitle>{hasConflict ? 'Perubahan Versi' : 'Kesalahan'}</AlertTitle>
                        <AlertDescription className="flex flex-col gap-2">
                            <p>{errorMessage}</p>
                            {hasConflict && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={handleRefreshData}
                                    disabled={busy}
                                >
                                    Segarkan versi kalender
                                </Button>
                            )}
                        </AlertDescription>
                    </Alert>
                )}

                <form onSubmit={handleSave} className="flex flex-col gap-4">
                    <FieldGroup>
                        {/* Title */}
                        <Field>
                            <FieldLabel htmlFor="event-title">Judul Kegiatan</FieldLabel>
                            <Input
                                id="event-title"
                                value={title}
                                onChange={(e) => setTitle(e.target.value)}
                                placeholder="Contoh: Rapat Koordinasi / Libur Nasional"
                                required
                                maxLength={150}
                                disabled={busy}
                            />
                            {fieldErrors.title && <FieldError>{fieldErrors.title.join(', ')}</FieldError>}
                        </Field>

                        {/* Kind */}
                        <Field>
                            <FieldLabel>Jenis Kegiatan</FieldLabel>
                            <OptionSelect
                                id="event-kind"
                                name="kind"
                                items={KIND_OPTIONS}
                                value={kind}
                                onValueChange={(val) => setKind(val as 'activity' | 'holiday' | 'working_day')}
                                placeholder="Pilih jenis kegiatan"
                                disabled={busy}
                            />
                            {fieldErrors.kind && <FieldError>{fieldErrors.kind.join(', ')}</FieldError>}
                        </Field>

                        {/* Dates */}
                        <div className="grid grid-cols-2 gap-3">
                            <Field>
                                <FieldLabel htmlFor="event-start-date">Tanggal Mulai</FieldLabel>
                                <DatePickerInput
                                    id="event-start-date"
                                    name="start_date"
                                    value={startDate}
                                    onValueChange={setStartDate}
                                    disabled={busy}
                                />
                                {fieldErrors.start_date && <FieldError>{fieldErrors.start_date.join(', ')}</FieldError>}
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="event-end-date">Tanggal Selesai</FieldLabel>
                                <DatePickerInput
                                    id="event-end-date"
                                    name="end_date"
                                    value={endDate}
                                    onValueChange={setEndDate}
                                    disabled={busy}
                                />
                                {fieldErrors.end_date && <FieldError>{fieldErrors.end_date.join(', ')}</FieldError>}
                            </Field>
                        </div>

                        {/* All Day Switch */}
                        <Field orientation="horizontal" className="flex items-center justify-between">
                            <FieldLabel htmlFor="event-all-day">Sepanjang Hari</FieldLabel>
                            <Switch id="event-all-day" checked={allDay} onCheckedChange={setAllDay} disabled={busy} />
                        </Field>

                        {/* Times if not all-day */}
                        {!allDay && (
                            <div className="grid grid-cols-2 gap-3">
                                <Field>
                                    <FieldLabel htmlFor="event-start-time">Jam Mulai</FieldLabel>
                                    <Input
                                        id="event-start-time"
                                        type="time"
                                        value={startTime}
                                        onChange={(e) => setStartTime(e.target.value)}
                                        disabled={busy}
                                    />
                                    {fieldErrors.start_time && (
                                        <FieldError>{fieldErrors.start_time.join(', ')}</FieldError>
                                    )}
                                </Field>
                                <Field>
                                    <FieldLabel htmlFor="event-end-time">Jam Selesai</FieldLabel>
                                    <Input
                                        id="event-end-time"
                                        type="time"
                                        value={endTime}
                                        onChange={(e) => setEndTime(e.target.value)}
                                        disabled={busy}
                                    />
                                    {fieldErrors.end_time && <FieldError>{fieldErrors.end_time.join(', ')}</FieldError>}
                                </Field>
                            </div>
                        )}

                        {/* Location */}
                        <Field>
                            <FieldLabel htmlFor="event-location">Lokasi (Opsional)</FieldLabel>
                            <Input
                                id="event-location"
                                value={location}
                                onChange={(e) => setLocation(e.target.value)}
                                placeholder="Contoh: Ruang Rapat Lt. 2"
                                maxLength={255}
                                disabled={busy}
                            />
                            {fieldErrors.location && <FieldError>{fieldErrors.location.join(', ')}</FieldError>}
                        </Field>

                        {/* Description */}
                        <Field>
                            <FieldLabel htmlFor="event-description">Keterangan (Opsional)</FieldLabel>
                            <Textarea
                                id="event-description"
                                value={description}
                                onChange={(e) => setDescription(e.target.value)}
                                placeholder="Rincian catatan kegiatan…"
                                maxLength={5000}
                                disabled={busy}
                                rows={3}
                            />
                            {fieldErrors.description && <FieldError>{fieldErrors.description.join(', ')}</FieldError>}
                        </Field>
                    </FieldGroup>

                    {showDeleteConfirm ? (
                        <div className="mt-2 flex flex-col gap-2 rounded-md border border-destructive/30 bg-destructive/5 p-3">
                            <p className="text-xs font-semibold text-destructive">
                                Yakin ingin menghapus kegiatan ini?
                            </p>
                            <div className="flex justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setShowDeleteConfirm(false)}
                                    disabled={busy}
                                >
                                    Batal
                                </Button>
                                <Button
                                    type="button"
                                    variant="destructive"
                                    size="sm"
                                    onClick={handleDelete}
                                    disabled={busy}
                                >
                                    <HugeiconsIcon icon={Delete02Icon} strokeWidth={2} data-icon="inline-start" />
                                    {busy ? 'Menghapus…' : 'Ya, Hapus'}
                                </Button>
                            </div>
                        </div>
                    ) : null}

                    <DialogFooter className="mt-4 flex items-center justify-between gap-2">
                        {isEdit && !showDeleteConfirm ? (
                            <Button
                                type="button"
                                variant="destructive"
                                size="sm"
                                onClick={() => setShowDeleteConfirm(true)}
                                disabled={busy}
                            >
                                <HugeiconsIcon icon={Delete02Icon} strokeWidth={2} data-icon="inline-start" />
                                Hapus
                            </Button>
                        ) : (
                            <div />
                        )}
                        <div className="flex items-center gap-2">
                            <DialogClose
                                render={
                                    <Button type="button" variant="outline" disabled={busy}>
                                        Batal
                                    </Button>
                                }
                            />
                            <Button type="submit" disabled={busy}>
                                <HugeiconsIcon icon={FloppyDiskIcon} strokeWidth={2} data-icon="inline-start" />
                                {busy ? 'Menyimpan…' : 'Simpan'}
                            </Button>
                        </div>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
