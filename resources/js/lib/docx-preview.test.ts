import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { fetchPreviewDocx, previewElement } from './docx-preview';

const contentType = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
const controller = () => new AbortController();
afterEach(() => vi.unstubAllGlobals());

describe('authenticated DOCX preview loading', () => {
    it('retains renderer nodes across iframe realms without recreating or dropping their children', () => {
        const node = { nodeType: 1, childNodes: ['text'] } as unknown as Node;
        const adoptNode = vi.fn().mockReturnValue(node);
        const document = { adoptNode } as unknown as Document;
        expect(previewElement(document, node)).toBe(node);
        expect(adoptNode).toHaveBeenCalledWith(node);
    });
    it('does not attach active content or link navigation from the renderer', () => {
        const node = { setAttribute: vi.fn(), appendChild: vi.fn(), style: {} };
        const createElement = vi.fn().mockReturnValue(node);
        const document = {
            createElement,
            createTextNode: vi.fn().mockReturnValue(''),
        } as unknown as Document;
        previewElement(document, {
            tagName: 'a',
            href: 'https://example.com',
            onclick: () => {},
            innerHTML: '<script/>',
            className: 'link',
        });
        expect(node).not.toHaveProperty('href');
        expect(node).not.toHaveProperty('onclick');
        expect(node).not.toHaveProperty('innerHTML');
        previewElement(document, { tagName: 'iframe', src: 'https://example.com' });
        expect(createElement).toHaveBeenCalledTimes(1);
    });
    it('loads the private document without an Inertia request and keeps its bytes intact', async () => {
        const bytes = new Uint8Array([0x50, 0x4b, 3, 4]);
        const fetch = vi.fn().mockResolvedValue(new Response(bytes, { headers: { 'Content-Type': contentType } }));
        vi.stubGlobal('fetch', fetch);
        const abort = controller();
        expect(await fetchPreviewDocx('/private.docx?test_token=token', 100, abort.signal)).toEqual(bytes);
        expect(fetch).toHaveBeenCalledWith(
            '/private.docx?test_token=token',
            expect.objectContaining({ credentials: 'same-origin', signal: abort.signal }),
        );
    });
    it.each([401, 404, 422])('rejects an unavailable or stale result (%i)', async (status) => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response('', { status })));
        await expect(fetchPreviewDocx('/result', 100, controller().signal)).rejects.toThrow('hasil uji terbaru');
    });
    it('rejects a login page instead of feeding HTML to the document renderer', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue(new Response('<html>Login</html>', { headers: { 'Content-Type': 'text/html' } })),
        );
        await expect(fetchPreviewDocx('/result', 100, controller().signal)).rejects.toThrow('sesi login');
    });
    it('enforces the limit on streamed bytes even without a content length', async () => {
        const cancel = vi.fn();
        const body = new ReadableStream({
            start(stream) {
                stream.enqueue(new Uint8Array(101));
            },
            cancel,
        });
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue(new Response(body, { headers: { 'Content-Type': contentType } })),
        );
        await expect(fetchPreviewDocx('/result', 100, controller().signal)).rejects.toThrow('batas preview');
        expect(cancel).toHaveBeenCalledOnce();
    });
    it('rejects a response that claims to be Word but is not a package', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue(new Response('not a zip', { headers: { 'Content-Type': contentType } })),
        );
        await expect(fetchPreviewDocx('/result', 100, controller().signal)).rejects.toThrow('tidak dapat dibaca');
    });
    it('passes cancellation through so closing the viewer can stop its request', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn((_url, options) => Promise.reject(options.signal.reason)),
        );
        const abort = controller();
        abort.abort(new DOMException('Closed', 'AbortError'));
        await expect(fetchPreviewDocx('/result', 100, abort.signal)).rejects.toMatchObject({ name: 'AbortError' });
    });
});
