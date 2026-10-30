import * as React from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { CalendarDay, CalendarEvent } from '@/schemas/work-calendar';
import { formatFullDate } from '@/lib/calendar-date';
import { cn } from '@/lib/utils';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

interface DayViewProps {
    day: CalendarDay | undefined;
    events: CalendarEvent[];
    onSelectDay: (date: string, hour?: string) => void;
    onSelectEvent: (event: CalendarEvent) => void;
}

const HOURS = Array.from({ length: 24 }, (_, i) => `${String(i).padStart(2, '0')}:00`);

export function DayView({ day, events, onSelectDay, onSelectEvent }: DayViewProps) {
    if (!day) return null;

    const dateObj = new Date(day.date + 'T00:00:00');
    const allDayEvents = events.filter((e) => e.all_day && e.start_date <= day.date && e.end_date >= day.date);
    const timedEvents = events.filter((e) => !e.all_day && e.start_date <= day.date && e.end_date >= day.date);

    return (
        <div className="flex flex-col rounded-lg border bg-card shadow-xs">
            {/* Day Header */}
            <div className="flex items-center justify-between border-b bg-muted/40 p-4">
                <div>
                    <h3 className="text-base font-semibold">{formatFullDate(dateObj)}</h3>
                    <div className="mt-1 flex items-center gap-2">
                        {day.basis === 'holiday' && (
                            <Badge variant="destructive" size="sm">
                                Hari Libur
                            </Badge>
                        )}
                        {day.basis === 'working_day' && (
                            <Badge variant="success" size="sm">
                                Hari Kerja Pengganti
                            </Badge>
                        )}
                        {day.basis === 'unconfigured' && (
                            <Badge variant="outline" size="sm" className="text-warning">
                                Belum diatur
                            </Badge>
                        )}
                        {day.is_working !== null && day.basis === 'weekly' && (
                            <Badge variant={day.is_working ? 'secondary' : 'outline'} size="sm">
                                {day.is_working ? 'Hari Kerja Reguler' : 'Hari Libur Mingguan'}
                            </Badge>
                        )}
                    </div>
                </div>
                <Button type="button" size="sm" onClick={() => onSelectDay(day.date)}>
                    Tambah Kegiatan
                </Button>
            </div>

            {/* All-Day Events Section */}
            {allDayEvents.length > 0 && (
                <div className="border-b bg-muted/20 p-3">
                    <p className="mb-2 text-xs font-semibold text-muted-foreground">Sepanjang Hari:</p>
                    <div className="flex flex-wrap gap-2">
                        {allDayEvents.map((event) => (
                            <Button
                                key={event.id}
                                type="button"
                                variant="ghost"
                                className={cn(
                                    'h-auto rounded px-2.5 py-1 text-left text-xs font-medium shadow-2xs',
                                    event.kind === 'holiday' &&
                                        'border border-destructive/30 bg-destructive/15 text-destructive hover:bg-destructive/25',
                                    event.kind === 'working_day' &&
                                        'border border-success/30 bg-success/15 text-success hover:bg-success/25',
                                    event.kind === 'activity' &&
                                        'border border-border bg-secondary text-secondary-foreground hover:bg-secondary/80',
                                )}
                                onClick={() => onSelectEvent(event)}
                            >
                                {event.title}
                            </Button>
                        ))}
                    </div>
                </div>
            )}

            {/* Hourly Grid */}
            <div className="max-h-[600px] overflow-y-auto">
                <div className="divide-y">
                    {HOURS.map((hour) => {
                        const hourNum = parseInt(hour.slice(0, 2), 10);
                        const slotEvents = timedEvents.filter((e) => {
                            if (!e.start_time) return false;
                            const eventHour = parseInt(e.start_time.split(':')[0], 10);
                            return eventHour === hourNum;
                        });

                        return (
                            <div
                                key={hour}
                                className="group flex min-h-[52px] transition-colors hover:bg-accent/40"
                                onClick={() => onSelectDay(day.date, hour)}
                            >
                                <div className="w-16 flex-none border-r p-2 text-right font-mono text-xs text-muted-foreground">
                                    {hour}
                                </div>
                                <div className="flex flex-1 flex-col gap-1 p-1">
                                    {slotEvents.map((event) => (
                                        <TooltipProvider key={event.id}>
                                            <Tooltip>
                                                <TooltipTrigger
                                                    render={
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            className={cn(
                                                                'h-auto w-full justify-start rounded px-2 py-1 text-left text-xs font-normal shadow-2xs',
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
                                                    <div>
                                                        <span className="font-mono font-semibold opacity-80">
                                                            {event.start_time} - {event.end_time || 'Selesai'}:{' '}
                                                        </span>
                                                        <span className="font-medium">{event.title}</span>
                                                        {event.location && (
                                                            <span className="ml-2 text-muted-foreground">
                                                                ({event.location})
                                                            </span>
                                                        )}
                                                    </div>
                                                </TooltipTrigger>
                                                <TooltipContent side="top">
                                                    <p className="font-semibold">{event.title}</p>
                                                    {event.description && (
                                                        <p className="text-xs text-muted-foreground">
                                                            {event.description}
                                                        </p>
                                                    )}
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                    ))}
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}
