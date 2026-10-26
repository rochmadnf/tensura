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

export type PreviewFont = {
    family: string;
    style: string;
    bold: boolean;
    italic: boolean;
    browser_url: string | null;
    available: boolean;
};

export async function loadPreviewFonts(
    fonts: PreviewFont[],
    document: Document,
    signal: AbortSignal,
): Promise<string[]> {
    const warnings: string[] = [];
    document.fonts.clear();
    for (const font of fonts) {
        if (!font.browser_url) {
            warnings.push(
                `${font.family} (${font.style}): ${font.available ? 'tersedia di server, belum dimuat ke browser' : 'belum tersedia'}. Viewer memakai font komputer/browser atau fallback.`,
            );
            continue;
        }
        try {
            const url = new URL(font.browser_url, window.location.href);
            if (url.origin !== window.location.origin) throw new Error('Sumber font tidak sah.');
            const response = await fetch(url.href, {
                signal,
                credentials: 'same-origin',
                headers: { Accept: 'font/ttf, font/otf' },
            });
            if (
                !response.ok ||
                response.redirected ||
                !response.headers.get('Content-Type')?.startsWith('font/') ||
                !response.body
            )
                throw new Error('Font belum dapat dimuat.');
            const reader = response.body.getReader();
            const chunks: Uint8Array[] = [];
            let size = 0;
            try {
                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;
                    size += value.byteLength;
                    if (size > 10 * 1024 * 1024) {
                        await reader.cancel();
                        throw new Error('Ukuran font terlalu besar.');
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
            const face = new FontFace(JSON.stringify(font.family), bytes, {
                weight: font.bold ? '700' : '400',
                style: font.italic ? 'italic' : 'normal',
            });
            await face.load();
            if (signal.aborted) return warnings;
            document.fonts.add(face);
        } catch {
            if (signal.aborted) return warnings;
            warnings.push(
                `${font.family} (${font.style}) gagal dimuat ke browser. Viewer memakai font komputer/browser atau fallback.`,
            );
        }
    }
    return warnings;
}
