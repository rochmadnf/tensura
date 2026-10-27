import { describe, expect, it } from 'vite-plus/test';
import {
    createLetterSchema,
    letterInitialValues,
    preserveLetterSelectionOrder,
    type LetterConfiguration,
} from './letter';

const template = '550e8400-e29b-41d4-a716-446655440000';
const variable = '550e8400-e29b-41d4-a716-446655440001';
const configuration: LetterConfiguration = {
    version: template,
    number: 1,
    date_format: 'd-m-Y',
    classification: '445',
    variables: [{ id: variable, name: 'nama', label: 'Nama', type: 1, required: true, multiple: false, options: [] }],
    signers: [],
};

describe('letter draft submission', () => {
    it('builds defaults from server date and validates required dynamic fields', () => {
        const initial = letterInitialValues(configuration, template, '2026-10-26');
        expect(initial.number_year).toBe('2026');
        expect(initial.number_month).toBe('10');
        expect(createLetterSchema(configuration).validate({ ...initial, subject: 'Surat' }).success).toBe(false);
        expect(
            createLetterSchema(configuration).validate({
                ...initial,
                subject: ' Surat ',
                values: { [variable]: 'Budi' },
            }),
        ).toMatchObject({ success: true, data: { subject: 'Surat', revision: 1, numbering_mode: 1 } });
    });
    it('requires valid manual positions within the documented bounds', () => {
        const initial = {
            ...letterInitialValues(configuration, template, '2026-10-26'),
            subject: 'Surat',
            values: { [variable]: 'Budi' },
            numbering_mode: '2',
        };
        const schema = createLetterSchema(configuration);
        expect(schema.validate(initial).success).toBe(false);
        expect(schema.validate({ ...initial, page_number: '23', sequence_number: '44' })).toMatchObject({
            success: true,
            data: { page_number: 23, sequence_number: 44 },
        });
        expect(schema.validate({ ...initial, page_number: '1', sequence_number: '100' }).success).toBe(false);
    });
    it('requires configured signers and multiple selections while preserving selection order', () => {
        const multiple = {
            ...configuration,
            variables: [{ ...configuration.variables[0], type: 4, multiple: true }],
            signers: [{ id: 'slot', label: 'Penandatangan', options: [] }],
        };
        const initial = { ...letterInitialValues(multiple, template, '2026-10-26'), subject: 'Surat' };
        expect(createLetterSchema(multiple).validate(initial).success).toBe(false);
        expect(
            createLetterSchema(multiple).validate({
                ...initial,
                values: { [variable]: ['b', 'a'] },
                signers: { slot: 'a' },
            }),
        ).toMatchObject({ success: true, data: { values: { [variable]: ['b', 'a'] } } });
        expect(preserveLetterSelectionOrder(['b'], ['a', 'b'])).toEqual(['b', 'a']);
        expect(preserveLetterSelectionOrder(['b', 'a'], ['a', 'c'])).toEqual(['a', 'c']);
    });
});
