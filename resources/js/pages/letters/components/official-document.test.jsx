import { Children, isValidElement } from 'react';
import { beforeEach, expect, it, vi } from 'vite-plus/test';
import { LetterOfficialDocument } from './official-document';
import { PdfPreview } from '@/components/pdf-preview';
import { DropdownMenu, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
const conversion = vi.hoisted(() => ({ operation: null, working: false, generate: vi.fn(), error: '' }));
vi.mock('@/hooks/use-letter-pdf', () => ({ useLetterPdf: () => conversion }));
const nodes = (node) => (!isValidElement(node) ? [] : [node, ...Children.toArray(node.props.children).flatMap(nodes)]);
const text = (node) =>
    !isValidElement(node)
        ? typeof node === 'string'
            ? node
            : ''
        : Children.toArray(node.props.children).map(text).join('');
function render(artifact = null) {
    return nodes(
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
            pdf: { source_id: 'word', artifact, operation: null, reason: null, max_bytes: 1000 },
        }),
    );
}
beforeEach(() => {
    conversion.generate.mockReset();
    conversion.working = false;
});
it('offers one download dropdown and requests PDF only when its menu item is selected', () => {
    const tree = render();
    expect(tree.filter((node) => node.type === DropdownMenu)).toHaveLength(1);
    expect(text(tree.find((node) => node.type === DropdownMenuTrigger))).toContain('Unduh');
    const items = tree.filter((node) => node.type === DropdownMenuItem);
    expect(items).toHaveLength(2);
    expect(items[0].props.render.props.href).toBe('/letters/letter/versions/version/download');
    expect(conversion.generate).not.toHaveBeenCalled();
    items[1].props.onClick();
    expect(conversion.generate).toHaveBeenCalledOnce();
});
it('keeps Word available while PDF is being converted', () => {
    conversion.working = true;
    const items = render().filter((node) => node.type === DropdownMenuItem);
    expect(items[0].props.render.props.href).toContain('/download');
    expect(items[0].props.disabled).not.toBe(true);
    expect(items[1].props.disabled).toBe(true);
});
it('downloads and views stored PDF without requesting another conversion', () => {
    const tree = render({ id: 'pdf' });
    const items = tree.filter((node) => node.type === DropdownMenuItem);
    expect(items[1].props.render.props.href).toBe('/letters/letter/pdfs/pdf/download');
    expect(tree.find((node) => node.type === PdfPreview).props.url).toBe('/letters/letter/pdfs/pdf/file');
    expect(conversion.generate).not.toHaveBeenCalled();
});
