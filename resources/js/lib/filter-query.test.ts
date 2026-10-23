import { describe, expect, it } from 'vite-plus/test';
import { parseFilterQuery, serializeFilterQuery } from './filter-query';

describe('filter query', () => {
    it.each([
        ['group:3,1|year:2034', { group: ['3', '1'], year: ['2034'] }],
        ['year:2034|group:1,2', { year: ['2034'], group: ['1', '2'] }],
        [' group : 3, 1,3,, |year: 2034 ', { group: ['3', '1'], year: ['2034'] }],
        ['group:1|year:2034|group:2,1', { group: ['1', '2'], year: ['2034'] }],
        ['', {}],
        ['group:,,|year:', {}],
        ['invalid|:3|bad-name:1|group:1:2|year:2034', { year: ['2034'] }],
        ['active:0', { active: ['0'] }],
        [['group:1'], {}],
        [null, {}],
    ])('parses %j into independent fields', (query, expected) => {
        expect(parseFilterQuery(query)).toEqual(expected);
    });

    it('serializes single and multiple selections into the shared format', () => {
        expect(serializeFilterQuery({ group: ['3', '1'], year: ['2034'] })).toBe('group:3,1|year:2034');
    });

    it('omits empty fields, trims values, and removes duplicates', () => {
        expect(serializeFilterQuery({ group: [' 3 ', '3', '', '1'], year: [] })).toBe('group:3,1');
        expect(serializeFilterQuery({ group: [], year: [] })).toBeNull();
    });

    it('ignores invalid field names and values containing delimiters', () => {
        expect(serializeFilterQuery({ 'bad-name': ['1'], group: ['1|year:2034', '1,2', '2:3', '4'] })).toBe('group:4');
    });

    it('round trips filters including property names inherited by JavaScript objects', () => {
        const query = 'group:3,1|constructor:test|year:2034';

        expect(serializeFilterQuery(parseFilterQuery(query))).toBe(query);
    });
});
