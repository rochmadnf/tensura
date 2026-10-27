import { renderToStaticMarkup } from 'react-dom/server';
import { describe, it, expect } from 'vite-plus/test';
import { FormActions } from './form-actions';

describe('Form actions', () => {
    it('shows icon and verb on a native submit button and a cancel button', () => {
        const html = renderToStaticMarkup(<FormActions cancelUrl="" onCancel={() => {}} processing={false} />);
        expect(html).toMatch(/<button[^>]*type="submit"/);
        expect(html).toMatch(/<button[^>]*type="button"/);
        expect(html).toContain('>Simpan<');
        expect(html).toContain('>Batal<');
        expect(html.match(/<svg\b/g)).toHaveLength(2);
    });
    it('disables both actions and shows the busy label while processing', () => {
        const html = renderToStaticMarkup(<FormActions cancelUrl="" onCancel={() => {}} processing />);
        expect(html).toContain('animate-spin');
        expect(html).toContain('>Menyimpan…<');
        const buttons = html.match(/<button\b[^>]*>/g);
        expect(buttons).toHaveLength(2);
        expect(buttons.every((button) => button.includes('disabled=""'))).toBe(true);
    });
});

describe('Compact form actions', () => {
    it('keeps a native submit button and accessible cancel with tooltips, without visible text', () => {
        const html = renderToStaticMarkup(
            <FormActions compact cancelUrl="" onCancel={() => {}} processing={false} />,
        );
        expect(html).toMatch(/<button[^>]*type="submit"[^>]*aria-label="Simpan"/);
        expect(html).toMatch(/<button[^>]*type="button"[^>]*aria-label="Batal"/);
        expect(html).not.toContain('>Simpan<');
        expect(html).not.toContain('>Batal<');
    });
    it('disables both compact actions while processing', () => {
        const html = renderToStaticMarkup(<FormActions compact cancelUrl="" onCancel={() => {}} processing />);
        expect(html).toContain('animate-spin');
        const buttons = html.match(/<button\b[^>]*>/g);
        expect(buttons).toHaveLength(2);
        expect(buttons.every((button) => button.includes('disabled=""'))).toBe(true);
    });
});