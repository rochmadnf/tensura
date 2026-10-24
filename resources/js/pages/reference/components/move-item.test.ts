import { describe, expect, it } from 'vite-plus/test';
import { moveItem } from './move-item';

describe('moveItem', () => {
    it.each([
        [2, 1, ['A', 'C', 'B']],
        [0, 1, ['B', 'A', 'C']],
        [2, 0, ['C', 'A', 'B']],
        [0, 2, ['B', 'C', 'A']],
        [0, -1, ['A', 'B', 'C']],
        [2, 3, ['A', 'B', 'C']],
        [1, 1, ['A', 'B', 'C']],
        [-1, 1, ['A', 'B', 'C']],
        [4, 1, ['A', 'B', 'C']],
        [0.5, 1, ['A', 'B', 'C']],
    ])('memindahkan indeks %i ke %i tanpa mengubah daftar asal', (from, to, expected) => {
        const original = Object.freeze(['A', 'B', 'C']);
        expect(moveItem(original, from, to)).toEqual(expected);
        expect(original).toEqual(['A', 'B', 'C']);
    });
    it('menangani daftar kosong dan satu item', () => {
        expect(moveItem([], 0, 1)).toEqual([]);
        expect(moveItem(['A'], 0, 1)).toEqual(['A']);
    });
});
