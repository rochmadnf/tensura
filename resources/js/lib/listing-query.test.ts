import { describe, expect, it } from 'vite-plus/test';
import { buildListingQuery } from './listing-query';

describe('listing query', () => {
    it('resets pagination when applying filters and retains the keyword and page size', () => {
        expect(buildListingQuery('?keyword=Jabatan&per_page=20&page=3', { groups: ['1', '2'], page: null })).toEqual({
            keyword: 'Jabatan',
            per_page: '20',
            'groups[0]': '1',
            'groups[1]': '2',
        });
    });

    it('retains every selected group when changing the search or pagination', () => {
        const search = '?groups%5B%5D=1&groups%5B%5D=2&keyword=Lama&page=3';

        expect(buildListingQuery(search, { keyword: 'Baru', page: null })).toEqual({
            'groups[0]': '1',
            'groups[1]': '2',
            keyword: 'Baru',
        });
        expect(buildListingQuery(search, { page: 2, per_page: 20 })).toEqual({
            'groups[0]': '1',
            'groups[1]': '2',
            keyword: 'Lama',
            page: '2',
            per_page: '20',
        });
    });

    it('replaces the complete old group filter and preserves unrelated parameters', () => {
        expect(buildListingQuery('?groups%5B0%5D=1&groups%5B1%5D=2&per_page=10', { groups: ['4'] })).toEqual({
            'groups[0]': '4',
            per_page: '10',
        });
    });

    it('removes all group parameters when resetting and removes an empty search explicitly', () => {
        expect(
            buildListingQuery('?groups%5B0%5D=1&groups%5B1%5D=2&keyword=Dokter&per_page=10', {
                groups: [],
                keyword: null,
            }),
        ).toEqual({ per_page: '10' });
    });
});
