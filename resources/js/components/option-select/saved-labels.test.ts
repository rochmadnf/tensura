import { describe, expect, it } from 'vite-plus/test';
import { resolveSelectedOptions } from './use-remote-options';

describe('saved option labels', () => {
    const saved = [{ value: 'a', label: 'Nama tersimpan' }];
    const latest = [
        { value: 'a', label: 'Nama terbaru' },
        { value: 'b', label: 'Pilihan baru' },
    ];
    it('keeps frozen labels for letter drafts while resolving new selections and preserving order', () => {
        expect(resolveSelectedOptions(['b', 'a'], saved, latest, true)).toEqual([latest[1], saved[0]]);
    });
    it('retains the existing latest-label behavior for ordinary master forms', () => {
        expect(resolveSelectedOptions(['a'], saved, latest)).toEqual([latest[0]]);
    });
});
