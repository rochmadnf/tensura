import { router } from '@inertiajs/react';

import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { HugeiconsIcon } from '@hugeicons/react';
import { ChevronLeftIcon, ChevronRightIcon, MoreHorizontalIcon } from '@hugeicons/core-free-icons';

export type PaginationMeta = {
    current_page: number;
    from: number | null;
    to: number | null;
    last_page: number;
    per_page: number;
    total: number;
    path: string;
};

const PER_PAGE_OPTIONS = [5, 10, 15, 20, 50, 100];

type PageItem = number | 'start-ellipsis' | 'end-ellipsis';

function getPageItems(current: number, last: number): PageItem[] {
    if (last <= 7) {
        return Array.from({ length: last }, (_, i) => i + 1);
    }

    const items: PageItem[] = [1];
    const start = Math.max(2, current - 1);
    const end = Math.min(last - 1, current + 1);

    if (start > 2) items.push('start-ellipsis');
    for (let page = start; page <= end; page++) items.push(page);
    if (end < last - 1) items.push('end-ellipsis');

    items.push(last);

    return items;
}

export function DataTableFooter({ meta }: { meta: PaginationMeta }) {
    const { current_page, last_page, per_page, total } = meta;

    function visit(params: { page?: number; per_page?: number }) {
        // pertahankan query lain (misal filter/search) yang sudah ada di URL
        const query = Object.fromEntries(new URLSearchParams(window.location.search));

        router.get(
            meta.path,
            { ...query, ...params },
            { preserveScroll: true, preserveState: true, only: ['resources'] },
        );
    }

    return (
        <div className="grid items-center gap-3 md:grid-cols-3">
            {/* Kiri: info total */}
            <p className="text-center text-sm text-muted-foreground md:text-left">
                Data <span className="font-medium text-foreground">{meta.from ?? 0}</span> -{' '}
                <span className="font-medium text-foreground">{meta.to ?? 0}</span>, Total{' '}
                <span className="font-medium text-foreground">{total}</span>
            </p>

            {/* Tengah: tombol pagination */}
            <nav aria-label="Pagination" className="flex items-center justify-center gap-1">
                <Button
                    variant="outline"
                    size="icon"
                    className="size-8"
                    aria-label="Halaman sebelumnya"
                    disabled={current_page <= 1}
                    onClick={() => visit({ page: current_page - 1 })}
                >
                    <HugeiconsIcon icon={ChevronLeftIcon} strokeWidth={2} />
                </Button>

                {getPageItems(current_page, last_page).map((item) =>
                    typeof item === 'number' ? (
                        <Button
                            key={item}
                            variant={item === current_page ? 'default' : 'outline'}
                            size="icon"
                            className="size-8"
                            aria-label={`Halaman ${item}`}
                            aria-current={item === current_page ? 'page' : undefined}
                            onClick={() => item !== current_page && visit({ page: item })}
                        >
                            {item}
                        </Button>
                    ) : (
                        <span
                            key={item}
                            aria-hidden
                            className="flex size-8 items-center justify-center text-muted-foreground"
                        >
                            <HugeiconsIcon icon={MoreHorizontalIcon} strokeWidth={2} />
                        </span>
                    ),
                )}

                <Button
                    variant="outline"
                    size="icon"
                    className="size-8"
                    aria-label="Halaman berikutnya"
                    disabled={current_page >= last_page}
                    onClick={() => visit({ page: current_page + 1 })}
                >
                    <HugeiconsIcon icon={ChevronRightIcon} strokeWidth={2} />
                </Button>
            </nav>

            {/* Kanan: data per halaman */}
            <div className="flex items-center justify-center gap-2 md:justify-end">
                <span className="text-sm text-muted-foreground">Per halaman</span>
                <Select
                    value={String(per_page)}
                    onValueChange={(value) => value && visit({ page: 1, per_page: Number(value) })}
                >
                    <SelectTrigger size="sm" className="w-18" aria-label="Data per halaman">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent align="end">
                        {PER_PAGE_OPTIONS.map((option) => (
                            <SelectItem key={option} value={String(option)}>
                                {option}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
        </div>
    );
}
