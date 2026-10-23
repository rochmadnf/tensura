import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Combobox } from '@base-ui/react/combobox';
import { ArrowDown01Icon, Tick02Icon, XIcon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import type { AriaAttributes } from 'react';

type SelectOption = { value: string; label: string };

interface OptionSelectProps extends Pick<AriaAttributes, 'aria-invalid' | 'aria-describedby'> {
    id: string;
    name: string;
    items: SelectOption[];
    value: string;
    onValueChange: (value: string) => void;
    placeholder: string;
    resetLabel?: string;
    disabled?: boolean;
}

export function OptionSelect({
    id,
    name,
    items,
    value,
    onValueChange,
    placeholder,
    resetLabel = 'Reset pilihan',
    disabled = false,
    ...ariaProps
}: OptionSelectProps) {
    if (items.length <= 5) {
        return (
            <Select
                name={name}
                items={items}
                value={value || null}
                disabled={disabled}
                onValueChange={(selectedValue) => onValueChange(selectedValue ?? '')}
            >
                <SelectTrigger id={id} className="w-full" {...ariaProps}>
                    <SelectValue placeholder={placeholder} />
                </SelectTrigger>
                <SelectContent>
                    <SelectGroup>
                        {items.map((item) => (
                            <SelectItem key={item.value} value={item.value}>
                                {item.label}
                            </SelectItem>
                        ))}
                    </SelectGroup>
                </SelectContent>
            </Select>
        );
    }

    return (
        <Combobox.Root
            name={name}
            items={items}
            value={items.find((item) => item.value === value) ?? null}
            isItemEqualToValue={(item, selectedItem) => item.value === selectedItem.value}
            disabled={disabled}
            onValueChange={(item) => onValueChange(item?.value ?? '')}
        >
            <div className="flex min-w-0 gap-2">
                <Combobox.InputGroup className="flex min-w-0 flex-1 gap-2">
                    <Combobox.Input id={id} placeholder={placeholder} render={<Input />} {...ariaProps} />
                    <Combobox.Trigger
                        aria-label="Tampilkan pilihan"
                        render={<Button type="button" variant="outline" size="icon" />}
                    >
                        <HugeiconsIcon icon={ArrowDown01Icon} strokeWidth={2} aria-hidden="true" />
                    </Combobox.Trigger>
                </Combobox.InputGroup>
                <Combobox.Clear
                    keepMounted
                    tabIndex={0}
                    aria-label={resetLabel}
                    render={<Button type="button" variant="outline" />}
                >
                    <HugeiconsIcon icon={XIcon} strokeWidth={2} data-icon="inline-start" aria-hidden="true" />
                    Reset
                </Combobox.Clear>
            </div>
            <Combobox.Portal>
                <Combobox.Positioner sideOffset={6} align="start" className="isolate z-50">
                    <Combobox.Popup className="max-h-(--available-height) w-(--anchor-width) max-w-(--available-width) overflow-hidden rounded-md bg-popover text-popover-foreground shadow-md ring-1 ring-foreground/10">
                        <Combobox.Empty className="px-2 text-sm text-muted-foreground">
                            Pilihan tidak ditemukan.
                        </Combobox.Empty>
                        <Combobox.List className="max-h-64 scroll-py-1 overflow-y-auto overscroll-contain p-1">
                            {(item: SelectOption) => (
                                <Combobox.Item
                                    key={item.value}
                                    value={item}
                                    className="flex w-full cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none data-highlighted:bg-accent data-highlighted:text-accent-foreground"
                                >
                                    <span className="min-w-0 flex-1">{item.label}</span>
                                    <Combobox.ItemIndicator>
                                        <HugeiconsIcon
                                            icon={Tick02Icon}
                                            strokeWidth={2}
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                    </Combobox.ItemIndicator>
                                </Combobox.Item>
                            )}
                        </Combobox.List>
                    </Combobox.Popup>
                </Combobox.Positioner>
            </Combobox.Portal>
        </Combobox.Root>
    );
}
