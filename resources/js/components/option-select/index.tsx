import {
    Combobox,
    ComboboxChip,
    ComboboxChips,
    ComboboxChipsInput,
    ComboboxClear,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxList,
    ComboboxValue,
    useComboboxAnchor,
} from '@/components/ui/combobox';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldLabel } from '@/components/ui/field';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { AriaAttributes } from 'react';

type SelectOption = { value: string; label: string };

type OptionSelectCommonProps = Pick<AriaAttributes, 'aria-invalid' | 'aria-describedby'> & {
    id: string;
    name: string;
    items: SelectOption[];
    placeholder: string;
    resetLabel?: string;
    disabled?: boolean;
};

export type OptionSelectProps = OptionSelectCommonProps &
    (
        | { multiple?: false; value: string; onValueChange: (value: string) => void }
        | { multiple: true; value: string[]; onValueChange: (value: string[]) => void }
    );

export function OptionSelect(props: OptionSelectProps) {
    if (props.multiple) {
        return <MultipleOptionSelect {...props} />;
    }

    return <SingleOptionSelect {...props} />;
}

function SingleOptionSelect({
    id,
    name,
    items,
    value,
    onValueChange,
    placeholder,
    resetLabel = 'Reset pilihan',
    disabled = false,
    'aria-invalid': ariaInvalid,
    'aria-describedby': ariaDescribedBy,
}: OptionSelectCommonProps & { value: string; onValueChange: (value: string) => void }) {
    const ariaProps = { 'aria-invalid': ariaInvalid, 'aria-describedby': ariaDescribedBy };
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

function MultipleOptionSelect({
    id,
    name,
    items,
    value,
    onValueChange,
    placeholder,
    resetLabel = 'Reset pilihan',
    disabled = false,
    'aria-invalid': ariaInvalid,
    'aria-describedby': ariaDescribedBy,
}: OptionSelectCommonProps & { value: string[]; onValueChange: (value: string[]) => void }) {
    const anchor = useComboboxAnchor();
    const selectedItems = items.filter((item) => value.includes(item.value));

    if (items.length <= 5) {
        return (
            <div
                id={id}
                role="group"
                aria-label={placeholder}
                aria-invalid={ariaInvalid}
                aria-describedby={ariaDescribedBy}
                className="flex flex-col gap-3"
            >
                {items.map((item) => (
                    <Field key={item.value} orientation="horizontal" data-disabled={disabled}>
                        <Checkbox
                            id={`${id}-${item.value}`}
                            name={name}
                            value={item.value}
                            checked={value.includes(item.value)}
                            disabled={disabled}
                            onCheckedChange={(checked) =>
                                onValueChange(
                                    checked
                                        ? [...value, item.value]
                                        : value.filter((selected) => selected !== item.value),
                                )
                            }
                        />
                        <FieldLabel htmlFor={`${id}-${item.value}`}>{item.label}</FieldLabel>
                    </Field>
                ))}
            </div>
        );
    }

    return (
        <Combobox
            multiple
            name={name}
            items={items}
            value={selectedItems}
            itemToStringLabel={(item) => item.label}
            itemToStringValue={(item) => item.value}
            isItemEqualToValue={(item, selectedItem) => item.value === selectedItem.value}
            disabled={disabled}
            onValueChange={(selected) => onValueChange(selected.map((item) => item.value))}
        >
            <ComboboxChips ref={anchor}>
                <ComboboxValue>
                    {selectedItems.map((item) => (
                        <ComboboxChip
                            key={item.value}
                            removeLabel={`Hapus ${item.label}`}
                            className="h-auto max-w-full whitespace-normal"
                        >
                            {item.label}
                        </ComboboxChip>
                    ))}
                </ComboboxValue>
                <ComboboxChipsInput
                    id={id}
                    placeholder={placeholder}
                    disabled={disabled}
                    aria-invalid={ariaInvalid}
                    aria-describedby={ariaDescribedBy}
                />
                <ComboboxClear
                    keepMounted
                    tabIndex={0}
                    aria-label={resetLabel}
                    title={resetLabel}
                    disabled={disabled}
                />
            </ComboboxChips>
            <ComboboxContent anchor={anchor}>
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
