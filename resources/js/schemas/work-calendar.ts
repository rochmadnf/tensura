export type CalendarView = 'month' | 'week' | 'day' | 'agenda';

export type CalendarEventKind = 'activity' | 'holiday' | 'working_day';

export type CalendarEventOrigin = 'manual' | 'national_holiday' | 'collective_leave';

export type CalendarQuery = {
    year?: number;
    date?: string;
    view?: CalendarView;
    search?: string;
    kind?: CalendarEventKind;
};

export type CalendarYear = {
    id: string;
    year: number;
    revision: number;
    work_revision: number;
    status: 'draft' | 'ready';
    working_weekdays: number[];
    confirmed_at: string | null;
    confirmed_by: { id: string; name: string } | null;
};

export type CalendarEvent = {
    id: string;
    revision: number;
    title: string;
    kind: CalendarEventKind;
    start_date: string;
    end_date: string;
    all_day: boolean;
    start_time: string | null;
    end_time: string | null;
    location: string | null;
    description: string | null;
    origin: CalendarEventOrigin;
    source_url: string | null;
    imported_at: string | null;
    calendar_revisions: Record<string, number>;
};

export type CalendarDay = {
    date: string;
    weekday: number;
    in_selected_month: boolean;
    is_today: boolean;
    is_working: boolean | null;
    basis: 'weekly' | 'holiday' | 'working_day' | 'unconfigured';
    exception_id: string | null;
};

export type CalendarPageData = {
    contract_version: 1;
    timezone: string;
    query: {
        year: number;
        date: string;
        view: CalendarView;
        search: string | null;
        kind: CalendarEventKind | null;
    };
    calendar: CalendarYear | null;
    calendar_years: CalendarYear[];
    period: { start_date: string; end_date: string };
    days: CalendarDay[];
    events: CalendarEvent[];
    missing_years: number[];
    truncated: boolean;
};

export type DeletedEvent = {
    id: string;
    calendar_revisions: Record<string, number>;
};

export type HolidayItem = {
    id: string;
    date: string;
    name: string;
    kind: 'national_holiday' | 'collective_leave';
    decision: 'new' | 'duplicate' | 'conflict';
    reason: 'manual_working_day' | 'existing_holiday' | 'source_changed' | null;
    selectable: boolean;
};

export type HolidayPreview = {
    id: string;
    token: string;
    year: number;
    calendar_revision: number;
    include_collective_leave: boolean;
    source_url: string;
    fetched_at: string;
    from_cache: boolean;
    expires_at: string;
    items: HolidayItem[];
    summary: {
        new: number;
        duplicate: number;
        conflict: number;
    };
};

export type HolidayApplyResult = {
    preview_id: string;
    created: number;
    skipped: number;
    calendar: CalendarYear;
    calendar_revisions: Record<string, number>;
};

export type DayCountReason = 'calendar_day' | 'working_day' | 'weekly_off' | 'holiday';

export type DayCount = {
    start_date: string;
    end_date: string;
    basis: 'calendar_days' | 'working_days';
    days: number;
    words: string;
    duration: string;
    dates: { date: string; counted: boolean; reason: DayCountReason }[];
    calendar_state: { state: 'current'; years: { year: number; work_revision: number }[] } | null;
};

export type ResolvedQuestionField = {
    id: string;
    value: unknown;
    display: string;
    words: string | null;
    tokens: Record<string, string>;
    calendar_state: {
        state: 'current' | 'stale' | 'missing';
        years?: { year: number; work_revision: number }[];
        changed_years?: number[];
    } | null;
};

export type ResolvedQuestions = {
    contract_version: 1;
    fields: ResolvedQuestionField[];
};
