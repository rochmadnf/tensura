import { describe, expect, it } from 'vite-plus/test';
import { LetterNumberPeriodSchema } from './letter-number-period';

const empty = { year: '2026', month: '10', mode: 'empty', last_page_number: null, last_sequence_number: null };
describe('letter period submission', () => {
    it('sends a typed empty period without inventing a last-used number', () => {
        expect(LetterNumberPeriodSchema.validate(empty)).toMatchObject({
            success: true,
            data: { year: 2026, month: 10, last_page_number: null, last_sequence_number: null },
        });
    });
    it('requires both last-used numbers only for custom initialization', () => {
        const result = LetterNumberPeriodSchema.validate({ ...empty, mode: 'custom' });
        expect(result.success).toBe(false);
        expect(Object.keys(result.errors)).toEqual(
            expect.arrayContaining(['last_page_number', 'last_sequence_number']),
        );
        expect(LetterNumberPeriodSchema.validate({ ...empty, mode: 'legacy' }).success).toBe(true);
        expect(
            LetterNumberPeriodSchema.validate({
                ...empty,
                mode: 'custom',
                last_page_number: '23',
                last_sequence_number: '44',
            }),
        ).toMatchObject({ success: true, data: { last_page_number: 23, last_sequence_number: 44 } });
    });
    it.each([
        { month: '0' },
        { month: '13' },
        { year: '0' },
        { year: '10000' },
        { mode: 'automatic' },
        { mode: 'custom', last_page_number: '0', last_sequence_number: '1' },
        { mode: 'custom', last_page_number: '1', last_sequence_number: '100' },
    ])('rejects values outside the period contract: %o', (override) => {
        expect(LetterNumberPeriodSchema.validate({ ...empty, ...override }).success).toBe(false);
    });
});
