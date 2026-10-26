import type { HElement } from 'docx-preview';

export const DOCX_PREVIEW_FRAME = `<!doctype html><html lang="id"><head>
<meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="default-src 'none'; style-src 'unsafe-inline'; img-src data: blob:; font-src data: blob:; base-uri 'none'; form-action 'none'">
<style>html{color-scheme:light}body{margin:0}main>.docx-wrapper{padding:16px}section.docx{flex-shrink:0}
@media print{body{zoom:1!important}.docx-wrapper{padding:0!important;background:none!important}section.docx{margin:0!important;box-shadow:none!important}}</style>
</head><body><main aria-label="Isi dokumen"></main></body></html>`;

export function previewElement(document: Document, element: HElement | Node | string): Node {
    if (typeof element === 'string') return document.createTextNode(element);
    if ('nodeType' in element) return document.adoptNode(element as Node);
    const { ns, tagName, className, style, children, ...properties } = element;
    if (tagName === '#fragment') {
        const fragment = document.createDocumentFragment();
        children?.forEach((child) => fragment.appendChild(previewElement(document, child)));
        return fragment;
    }
    if (tagName === '#comment') return document.createComment(typeof children?.[0] === 'string' ? children[0] : '');
    if (/^(script|iframe|object|embed|link|meta|base|form|input)$/i.test(tagName)) return document.createTextNode('');
    const node = ns ? document.createElementNS(ns, tagName) : document.createElement(tagName);
    if (className) node.setAttribute('class', className);
    if (typeof style === 'string') node.setAttribute('style', style);
    else if (style) Object.assign((node as HTMLElement).style, style);
    for (const [key, value] of Object.entries(properties)) {
        if (value !== undefined && !/^(on|innerHTML|outerHTML|srcdoc|href|src|contentEditable)/i.test(key))
            Reflect.set(node, key, value);
    }
    children?.forEach((child) => node.appendChild(previewElement(document, child)));
    return node;
}

export async function fetchPreviewDocx(url: string, maxBytes: number, signal: AbortSignal): Promise<Uint8Array> {
    const response = await fetch(url, {
        signal,
        credentials: 'same-origin',
        headers: { Accept: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' },
    });
    if (!response.ok || response.redirected) {
        throw new Error('Hasil uji tidak dapat dibuka. Muat ulang halaman atau generate hasil uji terbaru.');
    }
    if (
        !response.headers
            .get('Content-Type')
            ?.includes('application/vnd.openxmlformats-officedocument.wordprocessingml.document')
    ) {
        throw new Error('Respons bukan dokumen Word. Muat ulang halaman dan periksa sesi login.');
    }
    if (!response.body || !Number.isSafeInteger(maxBytes) || maxBytes <= 0) {
        throw new Error('Batas atau isi dokumen tidak tersedia. Muat ulang halaman.');
    }
    const reader = response.body.getReader();
    const chunks: Uint8Array[] = [];
    let size = 0;
    try {
        while (true) {
            const { done, value } = await reader.read();
            if (done) break;
            size += value.byteLength;
            if (size > maxBytes) {
                await reader.cancel();
                throw new Error('Dokumen melebihi batas preview. Unduh hasil uji untuk memeriksanya.');
            }
            chunks.push(value);
        }
    } finally {
        reader.releaseLock();
    }
    const bytes = new Uint8Array(size);
    let offset = 0;
    for (const chunk of chunks) {
        bytes.set(chunk, offset);
        offset += chunk.byteLength;
    }
    if (bytes[0] !== 0x50 || bytes[1] !== 0x4b) {
        throw new Error('Dokumen Word tidak dapat dibaca. Generate ulang hasil uji.');
    }
    return bytes;
}

export async function renderPreviewDocx(bytes: Uint8Array, document: Document): Promise<HTMLElement[]> {
    const { renderAsync } = await import('docx-preview');
    const container = document.querySelector('main')!;
    container.replaceChildren();
    await renderAsync(bytes, container as HTMLElement, undefined, {
        useBase64URL: true,
        renderAltChunks: false,
        renderComments: false,
        ignoreLastRenderedPageBreak: false,
        hideWrapperOnPrint: true,
        h: (element) => previewElement(document, element),
    });
    for (const link of container.querySelectorAll('a')) {
        link.removeAttribute('href');
        link.removeAttribute('target');
    }
    await document.fonts.ready;
    await Promise.all(Array.from(document.images, (image) => image.decode().catch(() => undefined)));
    return Array.from(container.querySelectorAll<HTMLElement>('section.docx'));
}
