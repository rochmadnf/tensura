import { App } from '@inertiajs/react';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vite-plus/test';
import About from './index';

function render(releases, version = '0.2.0') {
    return renderToStaticMarkup(
        <App
            initialComponent={About}
            initialPage={{
                component: 'about/index',
                props: { app: { name: 'Tensura', version }, page: { id: 'about' }, releases },
                url: '/about',
                version: null,
                clearHistory: false,
                encryptHistory: false,
            }}
        />,
    );
}

describe('Markdown release notes', () => {
    it('renders the server-formatted content and keeps the identity of each release', () => {
        const markup = render([
            {
                version: '0.2.0',
                content_html: '<p>Pusat <strong>notifikasi</strong>.</p><ul><li>Suara antar-tab.</li></ul>',
            },
            { version: '0.1.0', content_html: '<p>Rilis awal.</p>' },
        ]);

        expect(markup).toContain('Versi berjalan');
        expect(markup).toContain('<h3>v0.2.0</h3>');
        expect(markup).toContain('<h3>v0.1.0</h3>');
        expect(markup.indexOf('<h3>v0.2.0</h3>')).toBeLessThan(markup.indexOf('<h3>v0.1.0</h3>'));
        expect(markup).toContain('<p>Pusat <strong>notifikasi</strong>.</p><ul><li>Suara antar-tab.</li></ul>');
        expect(markup).toContain('<p>Rilis awal.</p>');
        expect(markup).not.toContain('&lt;p&gt;');
    });

    it('keeps the current application version visible when release notes are unavailable', () => {
        const markup = render([], '0.3.0');

        expect(markup).toContain('v0.3.0');
        expect(markup).toContain('Catatan rilis belum tersedia.');
        expect(markup).not.toContain('<h3>');
    });
});
