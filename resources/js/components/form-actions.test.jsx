import { renderToStaticMarkup } from 'react-dom/server';
import { describe, it, expect } from 'vite-plus/test';
import { FormActions } from './form-actions';

describe('Compact form actions', () => {
    it('keeps a native submit button and accessible cancel with tooltips', () => {
        const html = renderToStaticMarkup(<FormActions cancelUrl="" onCancel={() => {}} processing={false} />);
        expect(html).toMatch(/<button[^>]*type="submit"[^>]*aria-label="Simpan"/);
        expect(html).toMatch(/<button[^>]*type="button"[^>]*aria-label="Batal"/);
        expect(html).not.toContain('>Simpan<');
        expect(html).not.toContain('>Batal<');
    });
    it('disables both actions while processing', () => {
        const html = renderToStaticMarkup(<FormActions cancelUrl="" onCancel={() => {}} processing />);
        expect(html).toContain('animate-spin');
        const buttons = html.match(/<button\b[^>]*>/g);
        expect(buttons).toHaveLength(2);
        expect(buttons.every((button) => button.includes('disabled=""'))).toBe(true);
    });
});
