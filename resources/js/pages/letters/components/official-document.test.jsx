import { Children, isValidElement } from 'react';
import { expect, it } from 'vite-plus/test';
import { LetterOfficialDocument } from './official-document';
import { DocxPreview } from '@/components/docx-preview';
import { DropdownMenu, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
const nodes = (node) => (!isValidElement(node) ? [] : [node, ...Children.toArray(node.props.children).flatMap(nodes)]);
const text = (node) =>
    !isValidElement(node)
        ? typeof node === 'string'
            ? node
            : ''
        : Children.toArray(node.props.children).map(text).join('');
it('offers one download dropdown with official Word and unavailable PDF, and previews without draft marks', () => {
    const tree = nodes(
        LetterOfficialDocument({
            letterId: 'letter',
            official: {
                id: 'version',
                version: 1,
                number: '445/01.01/PKM-KMJ/X/2026',
                issued_by: 'Admin',
                issued_at: '2026-10-26T12:00:00Z',
                resolved: {},
                max_bytes: 1000,
                font_report: {},
            },
        }),
    );
    expect(tree.filter((node) => node.type === DropdownMenu)).toHaveLength(1);
    expect(text(tree.find((node) => node.type === DropdownMenuTrigger))).toContain('Unduh');
    const items = tree.filter((node) => node.type === DropdownMenuItem);
    expect(items).toHaveLength(2);
    expect(items[0].props.render.type).toBe('a');
    expect(items[0].props.render.props.href).toBe('/letters/letter/versions/version/download');
    expect(items[1].props.disabled).toBe(true);
    expect(text(items[1])).toContain('belum tersedia');
    const preview = tree.find((node) => node.type === DocxPreview);
    expect(preview.props.draft).not.toBe(true);
    expect(preview.props.url).toBe('/letters/letter/versions/version/word');
});
