export type DateParts = { year: number; month: number; day: number };

export function isValidDateParts({ year, month, day }: DateParts): boolean {
    const leapYear = year % 4 === 0 && (year % 100 !== 0 || year % 400 === 0);
    const monthLengths = [31, leapYear ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    return year >= 1 && year <= 9999 && month >= 1 && month <= 12 && day >= 1 && day <= monthLengths[month - 1];
}

export function parseIsoDate(value: string): DateParts | null {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
    if (!match) {
        return null;
    }
    const parts = { year: Number(match[1]), month: Number(match[2]), day: Number(match[3]) };
    return isValidDateParts(parts) ? parts : null;
}

export function parseDisplayDate(value: string): DateParts | null {
    const match = /^(\d{2})-(\d{2})-(\d{4})$/.exec(value);
    if (!match) {
        return null;
    }
    const parts = { year: Number(match[3]), month: Number(match[2]), day: Number(match[1]) };
    return isValidDateParts(parts) ? parts : null;
}

export function formatIsoDate({ year, month, day }: DateParts): string {
    return `${String(year).padStart(4, '0')}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

export function formatDisplayDate(value: string): string {
    const parts = parseIsoDate(value);
    return parts
        ? `${String(parts.day).padStart(2, '0')}-${String(parts.month).padStart(2, '0')}-${String(parts.year).padStart(4, '0')}`
        : value;
}

export function dateInputValue(text: string): string {
    const parts = parseDisplayDate(text);
    return parts ? formatIsoDate(parts) : text;
}

export function maskDateInput(text: string, previous = ''): string {
    if (parseIsoDate(text)) {
        return formatDisplayDate(text);
    }
    if (!/^[\d-]*$/.test(text)) {
        return text;
    }
    if (previous.endsWith('-') && text === previous.slice(0, -1)) {
        text = text.slice(0, -1);
    }
    const digits = text.replaceAll('-', '');
    if (digits.length > 8) {
        return text;
    }
    return (
        digits.slice(0, 2) +
        (digits.length >= 2 ? '-' + digits.slice(2, 4) : '') +
        (digits.length >= 4 ? '-' + digits.slice(4) : '')
    );
}

export function toLocalDate(value: string): Date | undefined {
    const parts = parseIsoDate(value);
    if (!parts) {
        return undefined;
    }
    const date = new Date(0);
    date.setFullYear(parts.year, parts.month - 1, parts.day);
    date.setHours(0, 0, 0, 0);
    return date;
}

export function fromLocalDate(date: Date): string {
    return formatIsoDate({ year: date.getFullYear(), month: date.getMonth() + 1, day: date.getDate() });
}
