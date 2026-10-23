import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { HugeiconsIcon } from '@hugeicons/react';
import { ChevronFirstIcon, ChevronLastIcon, ChevronLeftIcon, ChevronRightIcon } from '@hugeicons/core-free-icons';

export type PaginationMeta = {
    current_page: number;
    from: number | null;
    to: number | null;
    last_page: number;
    per_page: number;
    total: number;
};

export type PaginationParams = { page?: number; per_page?: number };

const PER_PAGE_OPTIONS = [5, 10, 15, 20, 50, 100];

export interface DataTableFooterProps {
    meta: PaginationMeta;
    onPaginationChange: (params: PaginationParams) => void;
    pageSizeOptions?: readonly number[];
    disabled?: boolean;
}

export function DataTableFooter({
    meta,
    onPaginationChange,
    pageSizeOptions = PER_PAGE_OPTIONS,
    disabled = false,
}: DataTableFooterProps) {
    const { current_page, last_page, per_page, total } = meta;

    const isFirst = current_page <= 1;
    const isLast = current_page >= last_page;

    const pageItems = Array.from({ length: last_page }, (_, index) => ({
        label: `Halaman ${index + 1}`,
        value: String(index + 1),
    }));
    const pageSizeItems = pageSizeOptions.map((option) => ({ label: String(option), value: String(option) }));

    return (
        <div className="grid items-center gap-3 md:grid-cols-3">
            <p className="text-center text-sm text-muted-foreground md:text-left">
                Menampilkan data <span className="font-medium text-foreground">{meta.from ?? 0}</span> -{' '}
                <span className="font-medium text-foreground">{meta.to ?? 0}</span>, dari total{' '}
                <span className="font-medium text-foreground">{total}</span> data
            </p>

            <nav aria-label="Pagination" className="flex items-center justify-center gap-1">
                <Button
                    variant="outline"
                    size="icon"
                    className="size-8"
                    aria-label="Halaman pertama"
                    disabled={disabled || isFirst}
                    onClick={() => onPaginationChange({ page: 1 })}
                >
                    <HugeiconsIcon icon={ChevronFirstIcon} className="stroke-2" />
                </Button>

                <Button
                    variant="outline"
                    size="icon"
                    className="size-8"
                    aria-label="Halaman sebelumnya"
                    disabled={disabled || isFirst}
                    onClick={() => onPaginationChange({ page: current_page - 1 })}
                >
                    <HugeiconsIcon icon={ChevronLeftIcon} className="stroke-2" />
                </Button>

                <Select
                    items={pageItems}
                    value={String(current_page)}
                    disabled={disabled || last_page <= 1}
                    onValueChange={(value) => value && onPaginationChange({ page: Number(value) })}
                >
                    <SelectTrigger size="sm" className="w-34" aria-label="Pilih halaman">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            {pageItems.map((item) => (
                                <SelectItem key={item.value} value={item.value}>
                                    {item.label}
                                </SelectItem>
                            ))}
                        </SelectGroup>
                    </SelectContent>
                </Select>

                <Button
                    variant="outline"
                    size="icon"
                    className="size-8"
                    aria-label="Halaman berikutnya"
                    disabled={disabled || isLast}
                    onClick={() => onPaginationChange({ page: current_page + 1 })}
                >
                    <HugeiconsIcon icon={ChevronRightIcon} className="stroke-2" />
                </Button>

                <Button
                    variant="outline"
                    size="icon"
                    className="size-8"
                    aria-label="Halaman terakhir"
                    disabled={disabled || isLast}
                    onClick={() => onPaginationChange({ page: last_page })}
                >
                    <HugeiconsIcon icon={ChevronLastIcon} className="stroke-2" />
                </Button>
            </nav>

            <div className="flex items-center justify-center gap-2 md:justify-end">
                <span className="text-sm text-muted-foreground">Per halaman</span>
                <Select
                    items={pageSizeItems}
                    value={String(per_page)}
                    disabled={disabled}
                    onValueChange={(value) => value && onPaginationChange({ page: 1, per_page: Number(value) })}
                >
                    <SelectTrigger size="sm" className="w-18" aria-label="Data per halaman">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent align="end">
                        <SelectGroup>
                            {pageSizeItems.map((item) => (
                                <SelectItem key={item.value} value={item.value}>
                                    {item.label}
                                </SelectItem>
                            ))}
                        </SelectGroup>
                    </SelectContent>
                </Select>
            </div>
        </div>
    );
}
