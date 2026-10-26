export async function fetchPreviewPdf(url: string, maxBytes: number, signal: AbortSignal): Promise<ArrayBuffer> {
    if (!Number.isSafeInteger(maxBytes) || maxBytes <= 0) throw new Error('Batas PDF tidak valid. Muat ulang halaman.');
    const response = await fetch(url, { signal, credentials: 'same-origin', headers: { Accept: 'application/pdf' } });
    if (
        !response.ok ||
        response.redirected ||
        !response.headers.get('Content-Type')?.includes('application/pdf') ||
        !response.body
    ) {
        throw new Error('PDF tidak dapat dibuka. Periksa sesi login atau buat hasil terbaru.');
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
                throw new Error('PDF melewati batas viewer.');
            }
            chunks.push(value);
        }
    } finally {
        reader.releaseLock();
    }
    const buffer = new ArrayBuffer(size);
    const bytes = new Uint8Array(buffer);
    let offset = 0;
    for (const chunk of chunks) {
        bytes.set(chunk, offset);
        offset += chunk.byteLength;
    }
    if (new TextDecoder().decode(bytes.subarray(0, 5)) !== '%PDF-') throw new Error('Berkas bukan PDF yang valid.');
    return buffer;
}
