import { afterEach, expect, it, vi } from 'vite-plus/test';
import { fetchPreviewPdf } from './pdf-preview';
afterEach(() => vi.unstubAllGlobals());
it('fetches a same-origin authenticated PDF and returns its exact bytes', async () => {
    const bytes = '%PDF-1.7\nbody\n%%EOF';
    const request = vi.fn().mockResolvedValue(new Response(bytes, { headers: { 'Content-Type': 'application/pdf' } }));
    vi.stubGlobal('fetch', request);
    const signal = new AbortController().signal;
    expect(new TextDecoder().decode(await fetchPreviewPdf('/private/pdf', 100, signal))).toBe(bytes);
    expect(request).toHaveBeenCalledWith('/private/pdf', {
        signal,
        credentials: 'same-origin',
        headers: { Accept: 'application/pdf' },
    });
});
it('refuses login HTML and non-PDF bytes', async () => {
    vi.stubGlobal(
        'fetch',
        vi
            .fn()
            .mockResolvedValueOnce(new Response('login', { headers: { 'Content-Type': 'text/html' } }))
            .mockResolvedValueOnce(new Response('bad', { headers: { 'Content-Type': 'application/pdf' } })),
    );
    await expect(fetchPreviewPdf('/private/pdf', 100, new AbortController().signal)).rejects.toThrow('sesi login');
    await expect(fetchPreviewPdf('/private/pdf', 100, new AbortController().signal)).rejects.toThrow('bukan PDF');
});
it('bounds the streamed body and rejects invalid limits before fetching', async () => {
    const request = vi
        .fn()
        .mockResolvedValue(new Response('%PDF-' + 'x'.repeat(100), { headers: { 'Content-Type': 'application/pdf' } }));
    vi.stubGlobal('fetch', request);
    await expect(fetchPreviewPdf('/private/pdf', 0, new AbortController().signal)).rejects.toThrow('Batas PDF');
    expect(request).not.toHaveBeenCalled();
    await expect(fetchPreviewPdf('/private/pdf', 10, new AbortController().signal)).rejects.toThrow('melewati batas');
});
it('forwards cancellation instead of accepting an interrupted file', async () => {
    const request = vi.fn().mockRejectedValue(new DOMException('Aborted', 'AbortError'));
    vi.stubGlobal('fetch', request);
    const controller = new AbortController();
    controller.abort();
    await expect(fetchPreviewPdf('/private/pdf', 100, controller.signal)).rejects.toHaveProperty('name', 'AbortError');
});
