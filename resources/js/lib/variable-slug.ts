/**
 * Menghasilkan nama variabel atau kode opsi yang sah: [a-z][a-z0-9_]* (maksimal 100 karakter).
 */
export function slugifyVariable(label: string): string {
    const slug = label
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '');

    if (!slug) return '';
    // Pastikan diawali huruf [a-z]
    const validStart = /^[a-z]/.test(slug) ? slug : `v_${slug}`;
    return validStart.slice(0, 100);
}
