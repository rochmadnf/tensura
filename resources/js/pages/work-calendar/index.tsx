import * as React from 'react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { OptionSelect } from '@/components/option-select';
import { Alert, AlertTitle, AlertDescription } from '@/components/reui/alert';
import { CalendarPageData, CalendarEvent } from '@/schemas/work-calendar';
import { MonthView } from './components/month-view';
import { WeekView } from './components/week-view';
import { DayView } from './components/day-view';
import { AgendaView } from './components/agenda-view';
import { EventDialog } from './components/event-dialog';
import { YearSettingsDialog } from './components/year-settings-dialog';
import { HolidayImportDialog } from './components/holiday-import-dialog';
import { TableActionButton } from '@/components/table-action-button';
import {
    parseDateString,
    toDateString,
    formatMonthYear,
    formatShortDate,
    addMonths,
    addDays,
} from '@/lib/calendar-date';
import wc from '@/routes/wc';
import { router } from '@inertiajs/react';
import { HugeiconsIcon } from '@hugeicons/react';
import {
    ArrowLeft01Icon,
    ArrowRight01Icon,
    CalendarCogIcon,
    Download04Icon,
    Add01Icon,
    Search01Icon,
    Alert02Icon,
} from '@hugeicons/core-free-icons';

interface WorkCalendarIndexProps {
    calendarData: CalendarPageData;
}

const KIND_FILTER_OPTIONS = [
    { value: '', label: 'Semua Kategori' },
    { value: 'activity', label: 'Kegiatan Biasa' },
    { value: 'holiday', label: 'Hari Libur' },
    { value: 'working_day', label: 'Hari Kerja Pengganti' },
];

export default function WorkCalendarIndex({ calendarData }: WorkCalendarIndexProps) {
    const { query, calendar, calendar_years, days, events, period } = calendarData;

    // Local filter state
    const [searchTerm, setSearchTerm] = React.useState(query.search || '');
    const searchDebounceRef = React.useRef<ReturnType<typeof setTimeout> | null>(null);

    // Dialog states
    const [eventDialogOpen, setEventDialogOpen] = React.useState(false);
    const [selectedEvent, setSelectedEvent] = React.useState<CalendarEvent | null>(null);
    const [selectedDateForNewEvent, setSelectedDateForNewEvent] = React.useState<string | undefined>(undefined);
    const [selectedHourForNewEvent, setSelectedHourForNewEvent] = React.useState<string | undefined>(undefined);

    const [yearSettingsOpen, setYearSettingsOpen] = React.useState(false);
    const [holidayImportOpen, setHolidayImportOpen] = React.useState(false);

    // Current date object from query
    const currentDate = React.useMemo(() => parseDateString(query.date), [query.date]);

    // Navigation helper to update Inertia URL
    const navigate = React.useCallback(
        (newParams: Partial<typeof query>) => {
            const nextQuery = {
                ...query,
                ...newParams,
            };
            // Clean empty params
            const filteredParams: Record<string, string | number> = {};
            if (nextQuery.year) filteredParams.year = nextQuery.year;
            if (nextQuery.date) filteredParams.date = nextQuery.date;
            if (nextQuery.view) filteredParams.view = nextQuery.view;
            if (nextQuery.search) filteredParams.search = nextQuery.search;
            if (nextQuery.kind) filteredParams.kind = nextQuery.kind;

            router.get(wc.index().url, filteredParams, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        },
        [query],
    );

    // Handle Search input change with debounce
    const handleSearchChange = (value: string) => {
        setSearchTerm(value);
        if (searchDebounceRef.current) {
            clearTimeout(searchDebounceRef.current);
        }
        searchDebounceRef.current = setTimeout(() => {
            navigate({ search: value });
        }, 350);
    };

    // Handle Category Filter change
    const handleKindFilterChange = (value: string) => {
        navigate({ kind: (value || null) as 'activity' | 'holiday' | 'working_day' | null });
    };

    // Handle View tab change
    const handleViewChange = (view: string) => {
        navigate({ view: view as 'month' | 'week' | 'day' | 'agenda' });
    };

    // Navigation buttons: Prev, Next, Today
    const handlePrev = () => {
        let newDate: Date;
        if (query.view === 'month' || query.view === 'agenda') {
            newDate = addMonths(currentDate, -1);
        } else if (query.view === 'week') {
            newDate = addDays(currentDate, -7);
        } else {
            newDate = addDays(currentDate, -1);
        }
        navigate({ date: toDateString(newDate), year: newDate.getFullYear() });
    };

    const handleNext = () => {
        let newDate: Date;
        if (query.view === 'month' || query.view === 'agenda') {
            newDate = addMonths(currentDate, 1);
        } else if (query.view === 'week') {
            newDate = addDays(currentDate, 7);
        } else {
            newDate = addDays(currentDate, 1);
        }
        navigate({ date: toDateString(newDate), year: newDate.getFullYear() });
    };

    const handleToday = () => {
        const today = new Date();
        navigate({ date: toDateString(today), year: today.getFullYear() });
    };

    // Date header display title
    const headerTitle = React.useMemo(() => {
        if (query.view === 'month' || query.view === 'agenda') {
            return formatMonthYear(currentDate);
        }
        if (query.view === 'week') {
            const startObj = parseDateString(period.start_date);
            const endObj = parseDateString(period.end_date);
            return `${formatShortDate(startObj)} - ${formatShortDate(endObj)}`;
        }
        return formatShortDate(currentDate);
    }, [query.view, currentDate, period]);

    // Open Event dialog for create
    const handleCreateEvent = (date?: string, hour?: string) => {
        setSelectedEvent(null);
        setSelectedDateForNewEvent(date || query.date);
        setSelectedHourForNewEvent(hour);
        setEventDialogOpen(true);
    };

    // Open Event dialog for edit
    const handleEditEvent = (event: CalendarEvent) => {
        setSelectedEvent(event);
        setSelectedDateForNewEvent(undefined);
        setSelectedHourForNewEvent(undefined);
        setEventDialogOpen(true);
    };

    // Refresh calendar data via Inertia reload
    const handleRefreshCalendar = React.useCallback(async () => {
        router.reload({ only: ['calendarData'] });
    }, []);

    const isYearUnconfigured = calendar === null;
    const isYearUnconfirmed = calendar !== null && calendar.status === 'draft';

    return (
        <AppLayout pageName="Kalender Kerja">
            <div className="flex flex-col gap-5">
                {/* Header Information */}
                <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="text-xl font-bold tracking-tight">Kalender Kerja</h2>
                        <p className="text-sm text-muted-foreground">
                            Kelola jadwal kegiatan, hari libur nasional, dan hari kerja organisasi.
                        </p>
                    </div>
                </div>

                {/* Readiness warning banners */}
                {isYearUnconfigured ? (
                    <Alert variant="warning">
                        <HugeiconsIcon icon={Alert02Icon} strokeWidth={2} />
                        <AlertTitle>Kalender Belum Diatur</AlertTitle>
                        <AlertDescription className="flex flex-col items-start gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <span>
                                Kalender tahun {query.year} belum diinisialisasi. Atur pola hari kerja agar perhitungan
                                durasi dapat berjalan normal.
                            </span>
                            <Button type="button" size="sm" variant="outline" onClick={() => setYearSettingsOpen(true)}>
                                Inisialisasi Tahun {query.year}
                            </Button>
                        </AlertDescription>
                    </Alert>
                ) : isYearUnconfirmed ? (
                    <Alert variant="warning">
                        <HugeiconsIcon icon={Alert02Icon} strokeWidth={2} />
                        <AlertTitle>Konfirmasi Kesiapan Diperlukan</AlertTitle>
                        <AlertDescription className="flex flex-col items-start gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <span>
                                Pengaturan hari kerja tahun {query.year} belum dikonfirmasi atau terdapat perubahan
                                setelah konfirmasi terakhir.
                            </span>
                            <Button type="button" size="sm" variant="outline" onClick={() => setYearSettingsOpen(true)}>
                                Periksa &amp; Konfirmasi
                            </Button>
                        </AlertDescription>
                    </Alert>
                ) : null}

                {/* Calendar Main Toolbar */}
                <div className="flex flex-col gap-3 rounded-lg border bg-card p-3 shadow-2xs">
                    {/* Top Row: Navigation & Actions */}
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        {/* Navigation */}
                        <div className="flex items-center gap-2">
                            <Button type="button" variant="outline" size="sm" onClick={handleToday}>
                                Hari Ini
                            </Button>
                            <div className="flex items-center gap-1">
                                <TableActionButton
                                    label="Periode sebelumnya"
                                    tooltip="Periode sebelumnya"
                                    variant="outline"
                                    icon={ArrowLeft01Icon}
                                    onClick={handlePrev}
                                />
                                <TableActionButton
                                    label="Periode berikutnya"
                                    tooltip="Periode berikutnya"
                                    variant="outline"
                                    icon={ArrowRight01Icon}
                                    onClick={handleNext}
                                />
                            </div>
                            <span className="text-base font-semibold capitalize">{headerTitle}</span>
                        </div>

                        {/* Actions */}
                        <div className="flex flex-wrap items-center gap-2">
                            <Button type="button" variant="outline" size="sm" onClick={() => setYearSettingsOpen(true)}>
                                <HugeiconsIcon icon={CalendarCogIcon} strokeWidth={2} data-icon="inline-start" />
                                Pengaturan Tahun
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => setHolidayImportOpen(true)}
                                disabled={isYearUnconfigured}
                            >
                                <HugeiconsIcon icon={Download04Icon} strokeWidth={2} data-icon="inline-start" />
                                Ambil Hari Libur
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                onClick={() => handleCreateEvent()}
                                disabled={isYearUnconfigured}
                            >
                                <HugeiconsIcon icon={Add01Icon} strokeWidth={2} data-icon="inline-start" />
                                Tambah Kegiatan
                            </Button>
                        </div>
                    </div>

                    {/* Bottom Row: Filters & View Switcher */}
                    <div className="flex flex-wrap items-center justify-between gap-3 border-t pt-3">
                        <div className="flex flex-1 flex-wrap items-center gap-2">
                            {/* Search */}
                            <div className="relative max-w-xs min-w-[200px] flex-1">
                                <HugeiconsIcon
                                    icon={Search01Icon}
                                    strokeWidth={2}
                                    className="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                                />
                                <Input
                                    value={searchTerm}
                                    onChange={(e) => handleSearchChange(e.target.value)}
                                    placeholder="Cari kegiatan…"
                                    className="pl-8"
                                />
                            </div>

                            {/* Kind Filter */}
                            <div className="w-[180px]">
                                <OptionSelect
                                    id="kind-filter"
                                    name="kind"
                                    items={KIND_FILTER_OPTIONS}
                                    value={query.kind || ''}
                                    onValueChange={handleKindFilterChange}
                                    placeholder="Semua Kategori"
                                />
                            </div>
                        </div>

                        {/* View Switcher Tabs */}
                        <Tabs value={query.view} onValueChange={handleViewChange}>
                            <TabsList>
                                <TabsTrigger value="month">Bulan</TabsTrigger>
                                <TabsTrigger value="week">Minggu</TabsTrigger>
                                <TabsTrigger value="day">Hari</TabsTrigger>
                                <TabsTrigger value="agenda">Agenda</TabsTrigger>
                            </TabsList>
                        </Tabs>
                    </div>
                </div>

                {/* Calendar Active View */}
                <div>
                    {query.view === 'month' && (
                        <MonthView
                            days={days}
                            events={events}
                            onSelectDay={(date) => handleCreateEvent(date)}
                            onSelectEvent={handleEditEvent}
                        />
                    )}
                    {query.view === 'week' && (
                        <WeekView
                            days={days}
                            events={events}
                            onSelectDay={(date, hour) => handleCreateEvent(date, hour)}
                            onSelectEvent={handleEditEvent}
                        />
                    )}
                    {query.view === 'day' && (
                        <DayView
                            day={days.find((d) => d.date === query.date) || days[0]}
                            events={events}
                            onSelectDay={(date, hour) => handleCreateEvent(date, hour)}
                            onSelectEvent={handleEditEvent}
                        />
                    )}
                    {query.view === 'agenda' && (
                        <AgendaView
                            events={events}
                            onSelectEvent={handleEditEvent}
                            onDeleteEvent={(event) => {
                                setSelectedEvent(event);
                                setEventDialogOpen(true);
                            }}
                        />
                    )}
                </div>

                {/* Dialogs */}
                <EventDialog
                    open={eventDialogOpen}
                    onOpenChange={setEventDialogOpen}
                    event={selectedEvent}
                    initialDate={selectedDateForNewEvent}
                    initialHour={selectedHourForNewEvent}
                    calendarYears={calendar_years}
                    onSaved={handleRefreshCalendar}
                    onDeleted={handleRefreshCalendar}
                    onRefreshCalendar={handleRefreshCalendar}
                />

                <YearSettingsDialog
                    open={yearSettingsOpen}
                    onOpenChange={setYearSettingsOpen}
                    year={query.year}
                    calendarYear={calendar}
                    allCalendarYears={calendar_years}
                    onSaved={handleRefreshCalendar}
                />

                <HolidayImportDialog
                    open={holidayImportOpen}
                    onOpenChange={setHolidayImportOpen}
                    year={query.year}
                    calendarYear={calendar}
                    onApplied={handleRefreshCalendar}
                />
            </div>
        </AppLayout>
    );
}
