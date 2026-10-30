import * as React from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { CalendarDay, CalendarEvent } from '@/schemas/work-calendar';
import { INDONESIAN_WEEKDAYS } from '@/lib/calendar-date';
import { cn } from '@/lib/utils';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

interface WeekViewProps {
    days: CalendarDay[];
    events: CalendarEvent[];
    onSelectDay: (date: string, hour?: string) => void;
    onSelectEvent: (event: CalendarEvent) => void;
}

const HOURS = Array.from({ length: 24 }, (_, i) => `${String(i).padStart(2, '0')}:00`);

export function WeekView({ days, events, onSelectDay, onSelectEvent }: WeekViewProps) {
    // Only display the first 7 days if days has more (e.g. 42 from month)
    const weekDays = React.useMemo(() => days.slice(0, 7), [days]);

    // All day events spanning this week
    const allDayEvents = React.useMemo(() => {
        return events.filter((e) => e.all_day);
    }, [events]);

    // Timed events
    const timedEvents = React.useMemo(() => {
        return events.filter((e) => !e.all_day);
    }, [events]);

    return (
        <div className="flex flex-col rounded-lg border bg-card shadow-xs">
            {/* Header Columns */}
            <div className="grid grid-cols-[60px_repeat(7,1fr)] border-b bg-muted/40 text-center text-xs font-semibold">
                <div className="border-r py-2.5 text-muted-foreground">Waktu</div>
                {weekDays.map((day) => {
                    const dayNumber = Number(day.date.split('-')[2]);
                    const weekdayInfo = INDONESIAN_WEEKDAYS.find((w) => w.id === day.weekday);
                    const isWeekend = day.weekday === 6 || day.weekday === 7;

                    return (
                        <div
                            key={day.date}
                            className={cn('border-r p-2 last:border-r-0', day.is_today && 'bg-primary/5')}
                        >
                            <div className="text-muted-foreground">{weekdayInfo?.short}</div>
                            <Button
                                type="button"
                                variant="ghost"
                                className={cn(
                                    'mx-auto mt-0.5 size-7 rounded-md p-0 text-sm font-semibold',
                                    day.is_today &&
                                        'bg-primary font-bold text-primary-foreground hover:bg-primary/90 hover:text-primary-foreground',
                                    !day.is_today && isWeekend && 'text-destructive',
                                )}
                                onClick={() => onSelectDay(day.date)}
                            >
                                {dayNumber}
                            </Button>
                            <div className="mt-1">
                                {day.basis === 'holiday' && (
                                    <Badge variant="destructive" size="sm">
                                        Libur
                                    </Badge>
                                )}
                                {day.basis === 'working_day' && (
                                    <Badge variant="success" size="sm">
                                        Pengganti
                                    </Badge>
                                )}
                            </div>
                        </div>
                    );
                })}
            </div>

            {/* All-Day Section if any */}
            {allDayEvents.length > 0 && (
                <div className="grid grid-cols-[60px_repeat(7,1fr)] border-b bg-muted/20 text-xs">
                    <div className="flex items-center justify-center border-r p-2 font-medium text-muted-foreground">
                        Semua Hari
                    </div>
                    {weekDays.map((day) => {
                        const dayAllDay = allDayEvents.filter(
                            (e) => e.start_date <= day.date && e.end_date >= day.date,
                        );
                        return (
                            <div
                                key={`allday-${day.date}`}
                                className="flex flex-col gap-1 border-r p-1.5 last:border-r-0"
                            >
                                {dayAllDay.map((event) => (
                                    <Button
                                        key={event.id}
                                        type="button"
                                        variant="ghost"
                                        className={cn(
                                            'h-auto w-full justify-start truncate rounded px-1.5 py-0.5 text-left text-xs font-normal shadow-2xs',
                                            event.kind === 'holiday' &&
                                                'border border-destructive/30 bg-destructive/15 text-destructive hover:bg-destructive/25',
                                            event.kind === 'working_day' &&
                                                'border border-success/30 bg-success/15 text-success hover:bg-success/25',
                                            event.kind === 'activity' &&
                                                'border border-border bg-secondary text-secondary-foreground hover:bg-secondary/80',
                                        )}
                                        onClick={() => onSelectEvent(event)}
                                    >
                                        <span className="truncate">{event.title}</span>
                                    </Button>
                                ))}
                            </div>
                        );
                    })}
                </div>
            )}

            {/* Time Grid (00:00 - 23:00) */}
            <div className="max-h-[600px] overflow-y-auto">
                <div className="grid grid-cols-[60px_repeat(7,1fr)] divide-y">
                    {HOURS.map((hour) => {
                        const hourNum = parseInt(hour.slice(0, 2), 10);
                        return (
                            <div key={hour} className="contents">
                                <div className="border-r p-2 text-right font-mono text-xs text-muted-foreground">
                                    {hour}
                                </div>
                                {weekDays.map((day) => {
                                    // Find events starting in this hour
                                    const slotEvents = timedEvents.filter((e) => {
                                        if (e.start_date !== day.date || !e.start_time) return false;
                                        const eventHour = parseInt(e.start_time.split(':')[0], 10);
                                        return eventHour === hourNum;
                                    });

                                    return (
                                        <div
                                            key={`${day.date}-${hour}`}
                                            className={cn(
                                                'group relative min-h-[48px] border-r p-1 transition-colors last:border-r-0 hover:bg-accent/40',
                                                day.is_today && 'bg-primary/2',
                                            )}
                                            onClick={() => onSelectDay(day.date, hour)}
                                        >
                                            {slotEvents.map((event) => (
                                                <TooltipProvider key={event.id}>
                                                    <Tooltip>
                                                        <TooltipTrigger
                                                            render={
                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    className={cn(
                                                                        'h-auto w-full justify-start rounded px-1.5 py-0.5 text-left text-xs font-normal shadow-2xs',
                                                                        event.kind === 'holiday' &&
                                                                            'border border-destructive/30 bg-destructive/15 text-destructive hover:bg-destructive/25',
                                                                        event.kind === 'working_day' &&
                                                                            'border border-success/30 bg-success/15 text-success hover:bg-success/25',
                                                                        event.kind === 'activity' &&
                                                                            'border border-border bg-secondary text-secondary-foreground hover:bg-secondary/80',
                                                                    )}
                                                                    onClick={(e) => {
                                                                        e.stopPropagation();
                                                                        onSelectEvent(event);
                                                                    }}
                                                                />
                                                            }
                                                        >
                                                            <div className="truncate text-[11px]">
                                                                <span className="font-mono font-semibold opacity-80">
                                                                    {event.start_time}{' '}
                                                                </span>
                                                                {event.title}
                                                            </div>
                                                        </TooltipTrigger>
                                                        <TooltipContent side="top">
                                                            <p className="font-semibold">{event.title}</p>
                                                            <p className="text-xs">
                                                                {event.start_time} - {event.end_time || 'Selesai'}
                                                            </p>
                                                            {event.location && (
                                                                <p className="text-xs text-muted-foreground">
                                                                    {event.location}
                                                                </p>
                                                            )}
                                                        </TooltipContent>
                                                    </Tooltip>
                                                </TooltipProvider>
                                            ))}
                                        </div>
                                    );
                                })}
                            </div>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}
