import { useState, type ReactNode } from 'react';
import { HugeiconsIcon } from '@hugeicons/react';
import { FilterHorizontalIcon } from '@hugeicons/core-free-icons';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

export interface DataFilterDialogProps<T> {
    value: T;
    emptyValue: T;
    onApply: (value: T) => void;
    activeCount: number;
    title?: string;
    disabled?: boolean;
    children: (props: { value: T; onValueChange: (value: T) => void; disabled: boolean }) => ReactNode;
}

export function DataFilterDialog<T>({
    value,
    emptyValue,
    onApply,
    activeCount,
    title = 'Filter Data',
    disabled = false,
    children,
}: DataFilterDialogProps<T>) {
    const [open, setOpen] = useState(false);
    const [draft, setDraft] = useState(value);

    return (
        <Dialog
            open={open}
            onOpenChange={(nextOpen) => {
                if (nextOpen) {
                    setDraft(value);
                }
                setOpen(nextOpen);
            }}
        >
            <DialogTrigger render={<Button type="button" variant="outline" disabled={disabled} />}>
                <HugeiconsIcon icon={FilterHorizontalIcon} strokeWidth={2} data-icon="inline-start" />
                Filter{activeCount > 0 ? ` (${activeCount})` : ''}
            </DialogTrigger>
            <DialogContent className="flex max-h-[calc(100dvh-2rem)] flex-col">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>Pilih filter, lalu klik Terapkan untuk memperbarui data.</DialogDescription>
                </DialogHeader>
                <form
                    className="flex min-h-0 flex-col gap-6"
                    onSubmit={(event) => {
                        event.preventDefault();
                        if (!disabled) {
                            onApply(draft);
                            setOpen(false);
                        }
                    }}
                >
                    <div className="min-h-0 overflow-y-auto p-1">
                        {children({ value: draft, onValueChange: setDraft, disabled })}
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={disabled}
                            onClick={() => setDraft(emptyValue)}
                        >
                            Reset
                        </Button>
                        <Button type="button" variant="secondary" onClick={() => setOpen(false)}>
                            Batal
                        </Button>
                        <Button type="submit" disabled={disabled}>
                            Terapkan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
