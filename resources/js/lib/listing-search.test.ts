import { describe, expect, it } from 'vite-plus/test';
import { ListingSearch } from './listing-search';

describe('listing search requests', () => {
    it('deduplicates applied and pending keywords and records only successful results', () => {
        const state = new ListingSearch('initial');
        expect(state.begin('initial')).toBeNull();
        const token = state.begin('search')!;
        expect(state.applied).toBe('initial');
        expect(state.begin('search')).toBeNull();
        state.succeed(token, 'search');
        state.finish(token);
        expect(state.begin('search')).toBeNull();
    });

    it.each(['failed', 'cancelled'])('allows the same keyword to retry after a %s request', () => {
        const state = new ListingSearch('');
        const token = state.begin('search')!;
        state.finish(token);
        expect(state.applied).toBe('');
        expect(state.begin('search')).toBeGreaterThan(token);
    });

    it('ignores success and finish from an older request after a newer keyword starts', () => {
        const state = new ListingSearch('');
        const older = state.begin('older')!;
        const newer = state.begin('newer')!;
        state.succeed(older, 'older');
        state.finish(older);
        expect(state.applied).toBe('');
        expect(state.pending?.token).toBe(newer);
        state.succeed(newer, 'newer');
        state.finish(newer);
        expect(state.applied).toBe('newer');
    });

    it('lets a reset supersede a pending search even if the applied keyword is already empty', () => {
        const state = new ListingSearch('');
        const token = state.begin('search')!;
        const reset = state.begin('')!;
        expect(reset).toBeGreaterThan(token);
        state.succeed(token, 'search');
        state.finish(token);
        expect(state.pending?.token).toBe(reset);
        state.succeed(reset, '');
        state.finish(reset);
        expect(state.begin('')).toBeNull();
    });

    it('restores the history keyword and ignores requests completed after history navigation', () => {
        const state = new ListingSearch('old');
        const token = state.begin('search')!;
        state.navigate('restored');
        state.succeed(token, 'search');
        state.finish(token);
        expect(state.applied).toBe('restored');
        expect(state.pending).toBeNull();
        expect(state.begin('restored')).toBeNull();
    });
});
