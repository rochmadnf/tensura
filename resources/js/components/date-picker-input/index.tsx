import { Calendar } from '@/components/ui/calendar';
import { InputGroup, InputGroupAddon, InputGroupButton, InputGroupInput } from '@/components/ui/input-group';
import { Popover, PopoverContent, PopoverTitle, PopoverTrigger } from '@/components/ui/popover';
import { dateInputValue, formatDisplayDate, fromLocalDate, maskDateInput, toLocalDate } from '@/lib/date-input';
import { Calendar01Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { useState, type AriaAttributes } from 'react';
import { id as indonesian } from 'react-day-picker/locale';

export type DatePickerInputProps = Pick<AriaAttributes, 'aria-invalid' | 'aria-describedby'> & {
    id: string;
    name: string;
    value: string;
    onValueChange: (value: string) => void;
    placeholder?: string;
    disabled?: boolean;
    min?: string;
    max?: string;
};

export function DatePickerInput({
    id,
    name,
    value,
    onValueChange,
    placeholder = 'hh-bb-tttt',
    disabled = false,
    min,
    max,
    ...ariaProps
}: DatePickerInputProps) {
    const [open, setOpen] = useState(false);
    const [input, setInput] = useState({ source: value, text: formatDisplayDate(value) });
    if (input.source !== value) {
        setInput({ source: value, text: formatDisplayDate(value) });
    }
    const selected = toLocalDate(value);
    const minimum = min ? toLocalDate(min) : undefined;
    const maximum = max ? toLocalDate(max) : undefined;
    const reference = maximum ?? selected ?? new Date();
    const startYear = Math.min(minimum?.getFullYear() ?? 1900, selected?.getFullYear() ?? 1900);
    const endYear = Math.max(maximum?.getFullYear() ?? reference.getFullYear() + 100, selected?.getFullYear() ?? 1900);

    return (
        <Popover open={open && !disabled} onOpenChange={setOpen}>
            <InputGroup data-disabled={disabled}>
                <InputGroupInput
                    id={id}
                    name={name}
                    type="text"
                    inputMode="numeric"
                    autoComplete="off"
                    placeholder={placeholder}
                    value={input.text}
                    disabled={disabled}
                    {...ariaProps}
                    onChange={(event) => {
                        const control = event.currentTarget;
                        const position = control.selectionStart ?? control.value.length;
                        const atEnd = position === control.value.length;
                        const digitCount = control.value.slice(0, position).replace(/\D/g, '').length;
                        const text = maskDateInput(control.value, input.text);
                        const source = dateInputValue(text);
                        setInput({ source, text });
                        onValueChange(source);
                        if (!atEnd && /^[\d-]*$/.test(text)) {
                            requestAnimationFrame(() => {
                                let caret = 0;
                                let digits = 0;
                                while (caret < text.length && digits < digitCount) {
                                    if (/\d/.test(text[caret])) {
                                        digits++;
                                    }
                                    caret++;
                                }
                                control.setSelectionRange(caret, caret);
                            });
                        }
                    }}
                />
                <InputGroupAddon align="inline-end">
                    <PopoverTrigger
                        render={<InputGroupButton size="icon-xs" disabled={disabled} aria-label="Pilih tanggal" />}
                    >
                        <HugeiconsIcon icon={Calendar01Icon} />
                    </PopoverTrigger>
                </InputGroupAddon>
            </InputGroup>
            <PopoverContent align="end" className="w-auto p-0">
                <PopoverTitle className="sr-only">Pilih tanggal</PopoverTitle>
                <Calendar
                    key={open ? value : 'closed'}
                    mode="single"
                    required
                    locale={indonesian}
                    captionLayout="dropdown"
                    selected={selected}
                    defaultMonth={selected ?? maximum ?? minimum}
                    today={maximum}
                    startMonth={toLocalDate(`${String(startYear).padStart(4, '0')}-01-01`)}
                    endMonth={toLocalDate(`${String(Math.min(endYear, 9999)).padStart(4, '0')}-12-31`)}
                    disabled={(date) =>
                        (minimum !== undefined && date < minimum) || (maximum !== undefined && date > maximum)
                    }
                    onSelect={(date) => {
                        const source = fromLocalDate(date);
                        setInput({ source, text: formatDisplayDate(source) });
                        onValueChange(source);
                        setOpen(false);
                    }}
                />
            </PopoverContent>
        </Popover>
    );
}
