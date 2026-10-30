export function parseDateString(str: string): Date {
    const [year, month, day] = str.split('-').map(Number);
    return new Date(year, month - 1, day);
}

export function toDateString(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

export function formatMonthYear(date: Date): string {
    return date.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
}

export function formatFullDate(date: Date): string {
    return date.toLocaleDateString('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

export function formatShortDate(date: Date): string {
    return date.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

export function addDays(date: Date, amount: number): Date {
    const result = new Date(date.getFullYear(), date.getMonth(), date.getDate() + amount);
    return result;
}

export function addMonths(date: Date, amount: number): Date {
    const result = new Date(date.getFullYear(), date.getMonth() + amount, 1);
    return result;
}

export const INDONESIAN_WEEKDAYS: { id: number; name: string; short: string }[] = [
    { id: 1, name: 'Senin', short: 'Sen' },
    { id: 2, name: 'Selasa', short: 'Sel' },
    { id: 3, name: 'Rabu', short: 'Rab' },
    { id: 4, name: 'Kamis', short: 'Kam' },
    { id: 5, name: 'Jumat', short: 'Jum' },
    { id: 6, name: 'Sabtu', short: 'Sab' },
    { id: 7, name: 'Minggu', short: 'Min' },
];
