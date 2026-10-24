import { describe, expect, it } from 'vite-plus/test';
import {
    dateInputValue,
    formatDisplayDate,
    fromLocalDate,
    maskDateInput,
    parseDisplayDate,
    parseIsoDate,
    toLocalDate,
} from './date-input';

describe('date input', () => {
    it('formats ISO dates and parses Indonesian display dates without losing leading zeros', () => {
        expect(formatDisplayDate('2026-01-04')).toBe('04-01-2026');
        expect(parseDisplayDate('04-01-2026')).toEqual({ year: 2026, month: 1, day: 4 });
        expect(dateInputValue('04-01-2026')).toBe('2026-01-04');
    });
    it.each(['2024-02-29', '2000-02-29', '2026-10-24', '0001-01-01'])('accepts the real date %s', (value) => {
        expect(parseIsoDate(value)).not.toBeNull();
        expect(dateInputValue(formatDisplayDate(value))).toBe(value);
    });
    it.each([
        '2025-02-29',
        '1900-02-29',
        '2026-02-30',
        '2026-04-31',
        '2026-00-01',
        '2026-13-01',
        '2026-01-00',
        '0000-01-01',
        '2026-1-01',
        '24-10-2026',
    ])('rejects the invalid ISO date %s', (value) => {
        expect(parseIsoDate(value)).toBeNull();
    });
    it.each(['', '2', '24-', '24-10-', '31-02-2026', '29-02-2025', 'bukan tanggal'])(
        'preserves empty, partial or invalid text %s',
        (text) => {
            expect(dateInputValue(text)).toBe(text);
            expect(formatDisplayDate(text)).toBe(text);
        },
    );
    it('adds separators and lets backspace remove a trailing separator with its previous digit', () => {
        expect(maskDateInput('2')).toBe('2');
        expect(maskDateInput('24')).toBe('24-');
        expect(maskDateInput('2410')).toBe('24-10-');
        expect(maskDateInput('24102026')).toBe('24-10-2026');
        expect(maskDateInput('24', '24-')).toBe('2');
        expect(maskDateInput('24-10', '24-10-')).toBe('24-1');
        expect(maskDateInput('')).toBe('');
        expect(maskDateInput('abc')).toBe('abc');
        expect(maskDateInput('2026-10-24')).toBe('24-10-2026');
        expect(maskDateInput('31022026')).toBe('31-02-2026');
    });
    it('constructs calendar dates from local parts including years below 100', () => {
        for (const value of ['2026-10-24', '2024-02-29', '0001-01-01']) {
            const date = toLocalDate(value)!;
            expect(fromLocalDate(date)).toBe(value);
            expect(date.getHours()).toBe(0);
        }
        expect(toLocalDate('31-02-2026')).toBeUndefined();
    });
});
