import { renderToStaticMarkup } from 'react-dom/server';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { useIsMobile } from './use-mobile';

function Viewport() {
    return <span>{useIsMobile() ? 'mobile' : 'desktop'}</span>;
}

afterEach(() => vi.unstubAllGlobals());

describe('mobile viewport initialization', () => {
    it.each([true, false])('uses the viewport on the first render when mobile=%s', (matches) => {
        const matchMedia = vi.fn(() => ({ matches }));
        vi.stubGlobal('window', { matchMedia });
        expect(renderToStaticMarkup(<Viewport />)).toBe(matches ? '<span>mobile</span>' : '<span>desktop</span>');
        expect(matchMedia).toHaveBeenCalledWith('(max-width: 767px)');
    });

    it('renders safely without a browser window', () => {
        vi.stubGlobal('window', undefined);
        expect(renderToStaticMarkup(<Viewport />)).toBe('<span>desktop</span>');
    });
});
