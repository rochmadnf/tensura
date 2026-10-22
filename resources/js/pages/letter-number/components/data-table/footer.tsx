import { router } from '@inertiajs/react';

import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { HugeiconsIcon } from '@hugeicons/react';
import { ChevronFirstIcon, ChevronLastIcon, ChevronLeftIcon, ChevronRightIcon } from '@hugeicons/core-free-icons';

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

export function DataTableFooter({ meta }: { meta: PaginationMeta }) {
    const { current_page, last_page, per_page, total } = meta;

    const isFirst = current_page <= 1;
    const isLast = current_page >= last_page;

    function visit(params: { page?: number; per_page?: number }) {
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
                Menampilkan data <span className="font-medium text-foreground">{meta.from ?? 0}</span> -{' '}
                <span className="font-medium text-foreground">{meta.to ?? 0}</span>, dari total{' '}
                <span className="font-medium text-foreground">{total}</span> data
            </p>

            {/* Tengah: First, Previous, Select Halaman, Next, Last */}
            <nav aria-label="Pagination" className="flex items-center justify-center gap-1">
                <Button
                    variant="outline"
                    size="icon"
                    className="size-8"
                    aria-label="Halaman pertama"
                    disabled={isFirst}
                    onClick={() => visit({ page: 1 })}
                >
                    <HugeiconsIcon icon={ChevronFirstIcon} className="stroke-2" />
                </Button>

                <Button
                    variant="outline"
                    size="icon"
                    className="size-8"
                    aria-label="Halaman sebelumnya"
                    disabled={isFirst}
                    onClick={() => visit({ page: current_page - 1 })}
                >
                    <HugeiconsIcon icon={ChevronLeftIcon} className="stroke-2" />
                </Button>

                <Select value={String(current_page)} onValueChange={(value) => value && visit({ page: Number(value) })}>
                    <SelectTrigger size="sm" className="w-34" aria-label="Pilih halaman">
                        <SelectValue>{(value) => `Halaman ${value}`}</SelectValue>
                    </SelectTrigger>
                    <SelectContent>
                        {Array.from({ length: last_page }, (_, i) => i + 1).map((page) => (
                            <SelectItem key={page} value={String(page)}>
                                Halaman {page}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                <Button
                    variant="outline"
                    size="icon"
                    className="size-8"
                    aria-label="Halaman berikutnya"
                    disabled={isLast}
                    onClick={() => visit({ page: current_page + 1 })}
                >
                    <HugeiconsIcon icon={ChevronLastIcon} className="stroke-2" />
                </Button>

                <Button
                    variant="outline"
                    size="icon"
                    className="size-8"
                    aria-label="Halaman terakhir"
                    disabled={isLast}
                    onClick={() => visit({ page: last_page })}
                >
                    <HugeiconsIcon icon={ChevronRightIcon} className="stroke-2" />
                </Button>
            </nav>

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
