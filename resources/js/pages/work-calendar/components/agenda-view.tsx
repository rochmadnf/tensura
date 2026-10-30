import * as React from 'react';
import { Badge } from '@/components/ui/badge';
import { CalendarEvent } from '@/schemas/work-calendar';
import { formatFullDate, parseDateString } from '@/lib/calendar-date';
import { TableActionButton } from '@/components/table-action-button';
import { Edit04Icon, Delete02Icon, Location01Icon, Clock01Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';

interface AgendaViewProps {
    events: CalendarEvent[];
    onSelectEvent: (event: CalendarEvent) => void;
    onDeleteEvent: (event: CalendarEvent) => void;
}

export function AgendaView({ events, onSelectEvent, onDeleteEvent }: AgendaViewProps) {
    // Group events by date
    const grouped = React.useMemo(() => {
        const map = new Map<string, CalendarEvent[]>();
        // Sort events chronologically
        const sorted = [...events].sort(
            (a, b) =>
                a.start_date.localeCompare(b.start_date) || (a.start_time ?? '').localeCompare(b.start_time ?? ''),
        );
        for (const ev of sorted) {
            const list = map.get(ev.start_date) ?? [];
            list.push(ev);
            map.set(ev.start_date, list);
        }
        return Array.from(map.entries());
    }, [events]);

    if (events.length === 0) {
        return (
            <div className="flex flex-col items-center justify-center rounded-lg border bg-card p-12 text-center">
                <p className="text-base font-medium">Tidak ada kegiatan pada periode ini</p>
                <p className="mt-1 text-sm text-muted-foreground">
                    Gunakan tombol &quot;Tambah Kegiatan&quot; untuk membuat kegiatan baru.
                </p>
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-6">
            {grouped.map(([dateStr, dayEvents]) => {
                const dateObj = parseDateString(dateStr);

                return (
                    <div key={dateStr} className="flex flex-col gap-3">
                        <div className="sticky top-0 z-10 border-b bg-background/95 py-2 font-semibold backdrop-blur-xs">
                            <span className="text-base">{formatFullDate(dateObj)}</span>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {dayEvents.map((event) => (
                                <div
                                    key={event.id}
                                    className="flex flex-col justify-between rounded-lg border bg-card p-4 shadow-xs"
                                >
                                    <div>
                                        <div className="flex items-start justify-between gap-2">
                                            <Badge
                                                variant={
                                                    event.kind === 'holiday'
                                                        ? 'destructive'
                                                        : event.kind === 'working_day'
                                                          ? 'success'
                                                          : 'secondary'
                                                }
                                                size="sm"
                                            >
                                                {event.kind === 'holiday'
                                                    ? 'Hari Libur'
                                                    : event.kind === 'working_day'
                                                      ? 'Hari Kerja Pengganti'
                                                      : 'Kegiatan'}
                                            </Badge>
                                            <div className="flex items-center gap-1">
                                                <TableActionButton
                                                    label={`Ubah ${event.title}`}
                                                    tooltip={`Ubah ${event.title}`}
                                                    variant="warning"
                                                    icon={Edit04Icon}
                                                    onClick={() => onSelectEvent(event)}
                                                />
                                                <TableActionButton
                                                    label={`Hapus ${event.title}`}
                                                    tooltip={`Hapus ${event.title}`}
                                                    variant="destructive"
                                                    icon={Delete02Icon}
                                                    onClick={() => onDeleteEvent(event)}
                                                />
                                            </div>
                                        </div>

                                        <h4 className="mt-2 leading-snug font-semibold">{event.title}</h4>

                                        <div className="mt-2 flex flex-col gap-1 text-xs text-muted-foreground">
                                            <div className="flex items-center gap-1.5">
                                                <HugeiconsIcon
                                                    icon={Clock01Icon}
                                                    strokeWidth={2}
                                                    className="size-3.5"
                                                />
                                                <span>
                                                    {event.all_day
                                                        ? 'Sepanjang hari'
                                                        : `${event.start_time || '—'} s/d ${event.end_time || 'Selesai'}`}
                                                </span>
                                            </div>
                                            {event.location && (
                                                <div className="flex items-center gap-1.5">
                                                    <HugeiconsIcon
                                                        icon={Location01Icon}
                                                        strokeWidth={2}
                                                        className="size-3.5"
                                                    />
                                                    <span>{event.location}</span>
                                                </div>
                                            )}
                                        </div>

                                        {event.description && (
                                            <p className="mt-2 line-clamp-2 text-xs text-muted-foreground">
                                                {event.description}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
