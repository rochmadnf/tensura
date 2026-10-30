import { http } from '@inertiajs/react';
import wc from '@/routes/wc';
import years from '@/routes/wc/years';
import events from '@/routes/wc/events';
import holidays from '@/routes/wc/holidays';
import {
    CalendarPageData,
    CalendarQuery,
    CalendarYear,
    CalendarEvent,
    HolidayPreview,
    HolidayApplyResult,
    DayCount,
} from '@/schemas/work-calendar';

export interface CalendarApiError {
    code: string;
    message: string;
    errors?: Record<string, string[]>;
}

async function requestJson<T>(config: {
    url: string;
    method?: 'get' | 'post' | 'put' | 'patch' | 'delete';
    data?: unknown;
    signal?: AbortSignal;
}): Promise<T> {
    const client = http.getClient();
    try {
        const response = await client.request({
            url: config.url,
            method: config.method ?? 'get',
            data: config.data,
            signal: config.signal,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
        });
        const resData = response.data as { data: T } | T;
        if (resData && typeof resData === 'object' && 'data' in resData) {
            return (resData as { data: T }).data;
        }
        return resData as T;
    } catch (err: unknown) {
        const anyErr = err as { response?: { data?: CalendarApiError } };
        if (anyErr.response?.data) {
            throw anyErr.response.data;
        }
        throw err;
    }
}

export async function fetchCalendarData(query: CalendarQuery, signal?: AbortSignal): Promise<CalendarPageData> {
    return requestJson<CalendarPageData>({
        url: wc.data.url({ query }),
        method: 'get',
        signal,
    });
}

export async function storeCalendarYear(
    payload: { year: number; copy_from_year?: number | null },
    signal?: AbortSignal,
): Promise<CalendarYear> {
    return requestJson<CalendarYear>({
        url: years.store.url(),
        method: 'post',
        data: payload,
        signal,
    });
}

export async function updateCalendarYear(
    year: number,
    payload: { revision: number; working_weekdays: number[] },
    signal?: AbortSignal,
): Promise<CalendarYear> {
    return requestJson<CalendarYear>({
        url: years.update.url({ year }),
        method: 'patch',
        data: payload,
        signal,
    });
}

export async function confirmCalendarYear(
    year: number,
    payload: { revision: number },
    signal?: AbortSignal,
): Promise<CalendarYear> {
    return requestJson<CalendarYear>({
        url: years.confirm.url({ year }),
        method: 'post',
        data: payload,
        signal,
    });
}

export async function storeCalendarEvent(
    payload: {
        title: string;
        kind: 'activity' | 'holiday' | 'working_day';
        start_date: string;
        end_date: string;
        all_day: boolean;
        start_time?: string | null;
        end_time?: string | null;
        location?: string | null;
        description?: string | null;
        calendar_revisions: Record<string, number>;
    },
    signal?: AbortSignal,
): Promise<CalendarEvent> {
    return requestJson<CalendarEvent>({
        url: events.store.url(),
        method: 'post',
        data: payload,
        signal,
    });
}

export async function updateCalendarEvent(
    eventId: string,
    payload: {
        revision: number;
        calendar_revisions: Record<string, number>;
        title: string;
        kind: 'activity' | 'holiday' | 'working_day';
        start_date: string;
        end_date: string;
        all_day: boolean;
        start_time?: string | null;
        end_time?: string | null;
        location?: string | null;
        description?: string | null;
    },
    signal?: AbortSignal,
): Promise<CalendarEvent> {
    return requestJson<CalendarEvent>({
        url: events.update.url({ event: eventId }),
        method: 'put',
        data: payload,
        signal,
    });
}

export async function destroyCalendarEvent(
    eventId: string,
    payload: {
        revision: number;
        calendar_revisions: Record<string, number>;
    },
    signal?: AbortSignal,
): Promise<{ id: string; calendar_revisions: Record<string, number> }> {
    return requestJson<{ id: string; calendar_revisions: Record<string, number> }>({
        url: events.destroy.url({ event: eventId }),
        method: 'delete',
        data: payload,
        signal,
    });
}

export async function previewHolidays(
    year: number,
    payload: { revision: number; include_collective_leave: boolean },
    signal?: AbortSignal,
): Promise<HolidayPreview> {
    return requestJson<HolidayPreview>({
        url: holidays.preview.url({ year }),
        method: 'post',
        data: payload,
        signal,
    });
}

export async function applyHolidays(
    year: number,
    payload: { preview_id: string; preview_token: string; selected_ids: string[] },
    signal?: AbortSignal,
): Promise<HolidayApplyResult> {
    return requestJson<HolidayApplyResult>({
        url: holidays.apply.url({ year }),
        method: 'post',
        data: payload,
        signal,
    });
}

export async function countCalendarDays(
    payload: { start_date: string; end_date: string; basis: 'calendar_days' | 'working_days' },
    signal?: AbortSignal,
): Promise<DayCount> {
    return requestJson<DayCount>({
        url: wc.count.url(),
        method: 'post',
        data: payload,
        signal,
    });
}
