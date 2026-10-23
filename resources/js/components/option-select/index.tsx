import {
    Combobox,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxList,
} from '@/components/ui/combobox';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
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
        <Combobox
            name={name}
            items={items}
            value={items.find((item) => item.value === value) ?? null}
            isItemEqualToValue={(item, selectedItem) => item.value === selectedItem.value}
            disabled={disabled}
            onValueChange={(item) => onValueChange(item?.value ?? '')}
        >
            <ComboboxInput
                id={id}
                placeholder={placeholder}
                disabled={disabled}
                showClear
                clearButtonProps={{
                    keepMounted: true,
                    tabIndex: 0,
                    'aria-label': resetLabel,
                    title: resetLabel,
                }}
                {...ariaProps}
            />
            <ComboboxContent>
                <ComboboxEmpty>Pilihan tidak ditemukan.</ComboboxEmpty>
                <ComboboxList>
                    {(item: SelectOption) => (
                        <ComboboxItem key={item.value} value={item}>
                            {item.label}
                        </ComboboxItem>
                    )}
                </ComboboxList>
            </ComboboxContent>
        </Combobox>
    );
}
