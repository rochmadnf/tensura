import { Children, isValidElement } from 'react';
import { describe, expect, it } from 'vite-plus/test';
import { DocumentFonts, DocumentFontWarnings } from './document-fonts';

const all = (node, predicate) =>
    !isValidElement(node)
        ? []
        : [
              ...(predicate(node) ? [node] : []),
              ...Children.toArray(node.props.children).flatMap((child) => all(child, predicate)),
          ];
const text = (node) =>
    !isValidElement(node)
        ? typeof node === 'string' || typeof node === 'number'
            ? String(node)
            : ''
        : Children.toArray(node.props.children).map(text).join('');
const entry = {
    family: 'Example',
    style: 'Regular',
    bold: false,
    italic: false,
    available: false,
    browser_url: null,
    hash: null,
    fallback: 'Arial',
};

describe('document font warning contract', () => {
    it('keeps missing fonts informational and offers an upload for the missing family', () => {
        const tree = DocumentFontWarnings({
            report: { available: true, entries: [entry], warning: null, fallback: 'Arial' },
        });
        expect(text(tree)).toContain('hanya menjadi peringatan');
        expect(text(tree)).toContain('Fallback PDF: Arial');
        expect(all(tree, (node) => node.type === DocumentFonts)[0].props.uploadFor).toBe('Example');
        expect(text(tree)).toContain('Belum tersedia');
    });
    it('distinguishes server availability from browser availability', () => {
        const report = { available: true, entries: [{ ...entry, available: true }], warning: null, fallback: 'Arial' };
        expect(text(DocumentFontWarnings({ report }))).toContain('hanya tersedia di server');
        report.entries[0].browser_url = '/private-font';
        expect(text(DocumentFontWarnings({ report }))).not.toContain('hanya tersedia di server');
    });
    it('keeps inventory failures and inspection limitations visible without turning them into document errors', () => {
        const tree = DocumentFontWarnings({
            report: {
                available: false,
                entries: [entry],
                warning: 'Inventory sedang tidak tersedia.',
                fallback: null,
                limitations: ['Periksa font simbol.'],
            },
        });
        expect(text(tree)).toContain('Belum diperiksa');
        expect(text(tree)).toContain('Inventory sedang tidak tersedia.');
        expect(text(tree)).toContain('Periksa font simbol.');
        expect(text(DocumentFontWarnings({ report: null }))).toContain('Jalankan validasi DOCX');
    });
});
