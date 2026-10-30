/**
 * Mengonversi input angka / mata uang Indonesia atau internasional ke bentuk kanonis desimal backend.
 * Bentuk kanonis: desimal dengan titik '.', tanpa pemisah ribuan, tanda minus di depan (bila negatif).
 */
export function toCanonicalNumber(input: string | null | undefined): string | null {
    if (input === null || input === undefined) return null;
    const trimmed = input.trim();
    if (!trimmed) return null;

    // Bersihkan prefix Rupiah dan spasi
    let cleaned = trimmed.replace(/^(?:rp\.?|idr)\s*/i, '').replace(/\s+/g, '');
    if (!cleaned) return null;

    const isNegative = cleaned.startsWith('-');
    if (isNegative) {
        cleaned = cleaned.slice(1);
    }

    const hasComma = cleaned.includes(',');
    const hasDot = cleaned.includes('.');

    let canonical = cleaned;

    if (hasComma && hasDot) {
        // Cth: 1.250.000,50 -> 1250000.50
        canonical = cleaned.replace(/\./g, '').replace(',', '.');
    } else if (hasComma) {
        // Cth: 12,5 atau 1.250,50 (tanpa dot) -> 12.5
        canonical = cleaned.replace(',', '.');
    } else if (hasDot) {
        const dotParts = cleaned.split('.');
        if (dotParts.length > 2) {
            // Cth: 1.250.000 -> ribuan berulang tanpa desimal
            canonical = cleaned.replace(/\./g, '');
        } else if (dotParts.length === 2) {
            // Cth: 1.250 vs 12.5 vs 1250.50
            const lastPart = dotParts[1]!;
            if (lastPart.length === 3 && dotParts[0]!.length <= 3) {
                // Ambigu antara ribuan (1.000) atau desimal 3 digit (1.000).
                // Jika tepat 3 digit dan bagian kiri <= 3, perlakukan sebagai ribuan hanya jika bilangan bulat:
                // Namun untuk input desimal kanonis biasa (12.50 atau 1250000.50), tetap pertahankan dot
                // Bila user mengetik titik ribuan tanpa koma, cth: 1.000.000 sudah ditangani di atas.
                canonical = cleaned;
            } else {
                canonical = cleaned;
            }
        }
    }

    if (isNegative && canonical !== '0' && canonical !== '0.00') {
        canonical = `-${canonical}`;
    }

    return canonical;
}

/**
 * Format string kanonis ke tampilan nominal Indonesia (Rp1.250.000,00 atau Rp1.250.000).
 */
export function formatDisplayCurrency(canonical: string | null | undefined, isInteger = false): string {
    if (!canonical) return '—';
    const num = Number(canonical);
    if (Number.isNaN(num)) return canonical;

    const isNegative = canonical.startsWith('-');
    const absStr = isNegative ? canonical.slice(1) : canonical;
    const [intPart, decPart] = absStr.split('.');

    const formattedInt = (intPart || '0').replace(/\B(?=(\d{3})+(?!\d))/g, '.');

    if (isInteger || !decPart) {
        return `${isNegative ? 'Minus ' : ''}Rp${formattedInt}`;
    }

    return `${isNegative ? 'Minus ' : ''}Rp${formattedInt},${decPart.padEnd(2, '0').slice(0, 2)}`;
}
