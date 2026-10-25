import { describe, expect, it } from 'vite-plus/test';
import { preserveSelectionOrder } from './test-form';

describe('DOCX repeated-row selection order', () => {
    it('retains B then A when the combobox reports choices in master order', () => {
        expect(preserveSelectionOrder(['B'], ['A', 'B'])).toEqual(['B', 'A']);
        expect(preserveSelectionOrder(['B', 'A'], ['A', 'B', 'C'])).toEqual(['B', 'A', 'C']);
    });

    it('preserves remaining selections after removal and appends a reselected choice', () => {
        expect(preserveSelectionOrder(['B', 'A', 'C'], ['A', 'C'])).toEqual(['A', 'C']);
        expect(preserveSelectionOrder(['A', 'C'], ['A', 'B', 'C'])).toEqual(['A', 'C', 'B']);
        expect(preserveSelectionOrder(['A', 'C'], [])).toEqual([]);
    });
});
