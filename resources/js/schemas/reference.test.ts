import { describe, expect, it } from 'vite-plus/test';
import { referenceGroupSchema, referenceItemSchema } from './reference';

describe('validasi grup referensi', () => {
    it('merapikan teks, menormalkan deskripsi kosong, dan membuang field sistem', () => {
        const result = referenceGroupSchema.validate({ name: ' Agama ', description: ' ', id: 99, sort_order: 9 });
        expect(result.success).toBe(true);
        expect(result.data).toEqual({ name: 'Agama', description: null });
        expect(referenceGroupSchema.validate({ name: 'ABC', description: ' Keterangan ' }).data).toEqual({
            name: 'ABC',
            description: 'Keterangan',
        });
    });
    it('menerima batas panjang nama dan deskripsi', () => {
        expect(referenceGroupSchema.validate({ name: 'A'.repeat(100), description: 'B'.repeat(255) }).success).toBe(
            true,
        );
    });
    it.each([
        ['name', ' ', 'Nama Grup wajib diisi.'],
        ['name', 'AB', 'Nama Grup minimal 3 karakter.'],
        ['name', 'A'.repeat(101), 'Nama Grup maksimal 100 karakter.'],
        ['name', 123, 'Nama Grup harus berupa teks.'],
        ['description', 'A'.repeat(256), 'Deskripsi maksimal 255 karakter.'],
        ['description', [], 'Deskripsi harus berupa teks.'],
    ])('menolak %s dengan pesan Indonesia yang sama dengan backend', (field, value, message) => {
        const result = referenceGroupSchema.validate({ name: 'Agama', description: '', [field]: value });
        expect(result.success).toBe(false);
        if (!result.success) expect(result.errors[field]?.[0]).toBe(message);
    });
});

describe('validasi item referensi', () => {
    it('merapikan konten dan hanya mengirim field yang boleh ditulis', () => {
        expect(referenceItemSchema.validate({ content: ' A ', sort_order: 9, reference_group_id: 1 }).data).toEqual({
            content: 'A',
        });
        expect(referenceItemSchema.validate({ content: 'A'.repeat(255) }).success).toBe(true);
    });
    it.each([
        [' ', 'Konten Item wajib diisi.'],
        ['A'.repeat(256), 'Konten Item maksimal 255 karakter.'],
        [123, 'Konten Item harus berupa teks.'],
    ])('menolak konten invalid dengan pesan backend', (content, message) => {
        const result = referenceItemSchema.validate({ content });
        expect(result.success).toBe(false);
        if (!result.success) expect(result.errors.content?.[0]).toBe(message);
    });
});
