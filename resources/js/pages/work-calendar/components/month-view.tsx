import * as React from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { CalendarDay, CalendarEvent } from '@/schemas/work-calendar';
import { INDONESIAN_WEEKDAYS } from '@/lib/calendar-date';
import { cn } from '@/lib/utils';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

interface MonthViewProps {
    days: CalendarDay[];
    events: CalendarEvent[];
    onSelectDay: (date: string) => void;
    onSelectEvent: (event: CalendarEvent) => void;
}

export function MonthView({ days, events, onSelectDay, onSelectEvent }: MonthViewProps) {
    const eventsByDate = React.useMemo(() => {
        const map = new Map<string, CalendarEvent[]>();
        for (const day of days) {
            const dayEvents = events.filter((e) => e.start_date <= day.date && e.end_date >= day.date);
            map.set(day.date, dayEvents);
        }
        return map;
    }, [days, events]);

    return (
        <div className="flex flex-col rounded-lg border bg-card shadow-xs">
            {/* Weekday headers */}
            <div className="grid grid-cols-7 border-b bg-muted/40 text-center text-xs font-semibold text-muted-foreground">
                {INDONESIAN_WEEKDAYS.map((w) => (
                    <div key={w.id} className="py-2.5">
                        <span className="hidden sm:inline">{w.name}</span>
                        <span className="sm:hidden">{w.short}</span>
                    </div>
                ))}
            </div>

            {/* 42-day grid */}
            <div className="grid grid-cols-7 divide-x divide-y border-b text-sm">
                {days.map((day) => {
                    const dayEvents = eventsByDate.get(day.date) ?? [];
                    const dayNumber = Number(day.date.split('-')[2]);
                    const isWeekend = day.weekday === 6 || day.weekday === 7;

                    return (
                        <div
                            key={day.date}
                            className={cn(
                                'group relative flex min-h-[110px] flex-col p-1.5 transition-colors sm:min-h-[125px]',
                                !day.in_selected_month && 'bg-muted/15 text-muted-foreground/60',
                                day.in_selected_month && !day.is_working && 'bg-muted/30',
                                day.is_working === null && 'border-dashed bg-warning/5',
                                day.is_today && 'bg-primary/5',
                            )}
                        >
                            {/* Day Header */}
                            <div className="mb-1 flex items-center justify-between gap-1">
                                <div>
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
                                    {day.basis === 'unconfigured' && (
                                        <Badge variant="outline" size="sm" className="text-warning">
                                            Belum diatur
                                        </Badge>
                                    )}
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    className={cn(
                                        'size-7 rounded-md p-0 text-xs font-semibold',
                                        day.is_today &&
                                            'bg-primary font-bold text-primary-foreground hover:bg-primary/90 hover:text-primary-foreground',
                                        !day.is_today && isWeekend && 'text-destructive',
                                    )}
                                    onClick={() => onSelectDay(day.date)}
                                    aria-label={`Tambah kegiatan pada tanggal ${day.date}`}
                                >
                                    {dayNumber}
                                </Button>
                            </div>

                            {/* Events list */}
                            <div className="flex flex-1 flex-col gap-1 overflow-y-auto">
                                {dayEvents.slice(0, 3).map((event) => {
                                    const isHoliday = event.kind === 'holiday';
                                    const isWorkingDay = event.kind === 'working_day';

                                    return (
                                        <TooltipProvider key={event.id}>
                                            <Tooltip>
                                                <TooltipTrigger
                                                    render={
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            className={cn(
                                                                'h-auto w-full justify-start rounded px-1.5 py-0.5 text-left text-xs leading-tight font-normal shadow-2xs',
                                                                isHoliday &&
                                                                    'border border-destructive/30 bg-destructive/15 text-destructive hover:bg-destructive/25',
                                                                isWorkingDay &&
                                                                    'border border-success/30 bg-success/15 text-success hover:bg-success/25',
                                                                !isHoliday &&
                                                                    !isWorkingDay &&
                                                                    'border border-border bg-secondary text-secondary-foreground hover:bg-secondary/80',
                                                            )}
                                                            onClick={(e) => {
                                                                e.stopPropagation();
                                                                onSelectEvent(event);
                                                            }}
                                                        />
                                                    }
                                                >
                                                    <span className="truncate">
                                                        {!event.all_day && event.start_time && (
                                                            <span className="font-mono text-[10px] font-semibold opacity-80">
                                                                {event.start_time}{' '}
                                                            </span>
                                                        )}
                                                        {event.title}
                                                    </span>
                                                </TooltipTrigger>
                                                <TooltipContent side="top">
                                                    <p className="font-semibold">{event.title}</p>
                                                    {!event.all_day && event.start_time && (
                                                        <p className="text-xs">
                                                            {event.start_time} - {event.end_time || 'Selesai'}
                                                        </p>
                                                    )}
                                                    {event.location && (
                                                        <p className="text-xs text-muted-foreground">
                                                            {event.location}
                                                        </p>
                                                    )}
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                    );
                                })}
                                {dayEvents.length > 3 && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        className="h-auto p-0 text-left text-[11px] font-medium text-muted-foreground hover:text-foreground"
                                        onClick={() => onSelectDay(day.date)}
                                    >
                                        +{dayEvents.length - 3} lainnya
                                    </Button>
                                )}
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
