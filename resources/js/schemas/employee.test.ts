import { describe, expect, it } from 'vite-plus/test';
import { createEmployeeSchema, type EmployeeFormOptions } from './employee';

const options: EmployeeFormOptions = {
    jobTitles: [{ id: '00000000-0000-4000-8000-000000000001', label: 'Dokter', group: 1 }],
    genders: [
        { id: 1, label: 'Laki-laki' },
        { id: 2, label: 'Perempuan' },
    ],
    strStatuses: [
        { id: 1, label: 'Seumur Hidup' },
        { id: 2, label: 'Definitif' },
    ],
    contractStatuses: [{ id: 1, label: 'PNS' }],
    statuses: [{ id: 1, label: 'Aktif' }],
    today: '2026-10-24',
};
const schema = createEmployeeSchema(options);
const validData = {
    nik: '0000000000000001',
    nip: '000000000000000001',
    name: 'Siti',
    gender: '2',
    str_number: '',
    str_status: '',
    str_published_date: '',
    sip_number: '',
    sip_published_date: '',
    sip_expired_date: '',
    front_title: '',
    back_title: '',
    job_title_id: options.jobTitles[0].id,
    rank: '',
    grade: '',
    email: '',
    join_date: '2026-10-24',
    contract_status: '1',
    status: '1',
};

describe('employee form validation', () => {
    it('keeps leading zeros, converts enums, and normalizes optional fields', () => {
        const result = schema.validate({ ...validData, nip: '', name: ' Siti ' });
        expect(result.success).toBe(true);
        expect(result.data).toMatchObject({
            nik: '0000000000000001',
            nip: null,
            name: 'Siti',
            front_title: null,
            back_title: null,
            rank: null,
            grade: null,
            email: null,
            contract_status: 1,
            status: 1,
        });
    });

    it.each([
        ['nik', '123'],
        ['nik', 'A'.repeat(16)],
        ['nip', '1'.repeat(19)],
        ['name', ' '],
        ['name', 'A'.repeat(101)],
        ['front_title', 'A'.repeat(51)],
        ['back_title', 'A'.repeat(51)],
        ['rank', 'A'.repeat(51)],
        ['grade', 'A'.repeat(51)],
        ['job_title_id', ''],
        ['job_title_id', 'unknown'],
        ['email', 'invalid'],
        ['contract_status', ''],
        ['contract_status', '99'],
        ['status', ''],
        ['status', '99'],
        ['gender', ''],
        ['gender', '0'],
        ['gender', '3'],
        ['str_number', '123'],
        ['str_status', '99'],
        ['str_published_date', '24-10-2026'],
        ['str_published_date', '2026-10-25'],
        ['sip_number', 'A'.repeat(31)],
        ['sip_published_date', '2026-02-30'],
        ['sip_published_date', '2026-10-25'],
        ['sip_expired_date', '24-'],
        ['join_date', ''],
        ['join_date', '2026-02-30'],
        ['join_date', '2026-10-25'],
        ['join_date', '24-10-2026'],
    ])('rejects %s = %s with an Indonesian field message', (field, value) => {
        const result = schema.validate({ ...validData, [field]: value });
        if (result.success) {
            throw new Error('Data tidak valid seharusnya ditolak.');
        }
        expect(result.errors[field]?.[0]).toBeTruthy();
        expect(result.errors[field]?.[0]).not.toContain(':attribute');
    });

    it('accepts leap dates and valid optional identity and email', () => {
        expect(schema.validate({ ...validData, join_date: '2024-02-29', email: 'siti@example.test' }).success).toBe(
            true,
        );
        expect(schema.validate({ ...validData, join_date: '2025-02-29' }).success).toBe(false);
    });

    it.each(['1', '2'])('accepts gender %s and keeps optional registrations null', (gender) => {
        const result = schema.validate({ ...validData, gender });
        expect(result.success).toBe(true);
        expect(result.data).toMatchObject({
            gender: Number(gender),
            str_number: null,
            str_status: null,
            str_published_date: null,
            sip_number: null,
            sip_published_date: null,
            sip_expired_date: null,
        });
    });

    const registrations = {
        str_number: 'STR0000000000001',
        str_status: '1',
        str_published_date: '2024-02-29',
        sip_number: 'SIP/001',
        sip_published_date: '2024-02-29',
        sip_expired_date: '2029-02-28',
    };
    it('accepts complete STR/SIP groups and future SIP expiry', () => {
        const result = schema.validate({ ...validData, ...registrations });
        expect(result.success).toBe(true);
        expect(result.data).toMatchObject({ ...registrations, str_status: 1 });
    });

    for (const fields of [
        ['str_number', 'str_status', 'str_published_date'],
        ['sip_number', 'sip_published_date', 'sip_expired_date'],
    ] as const) {
        it.each([1, 2, 3, 4, 5, 6])(`rejects incomplete ${fields[0]} group combination %i`, (mask) => {
            const partial = Object.fromEntries(
                fields.map((field, index) => [field, mask & (1 << index) ? registrations[field] : '']),
            );
            const result = schema.validate({ ...validData, ...partial });
            expect(result.success).toBe(false);
            if (result.success) {
                throw new Error('Kelompok yang tidak lengkap seharusnya ditolak.');
            }
            for (const field of fields) {
                if (partial[field] === '') {
                    expect(result.errors[field]?.[0]).toContain('wajib diisi bersama seluruh data');
                }
            }
        });
    }

    it.each(['2024-02-28', '2024-02-29'])('rejects SIP expiry %s before or on the publication date', (expiry) => {
        const result = schema.validate({ ...validData, ...registrations, sip_expired_date: expiry });
        if (result.success) {
            throw new Error('Tanggal berakhir seharusnya ditolak.');
        }
        expect(result.errors.sip_expired_date?.[0]).toBe('Tanggal Berakhir SIP harus setelah Tanggal Terbit SIP.');
    });

    it.each(['str_published_date', 'sip_published_date', 'sip_expired_date'] as const)(
        'rejects partial optional %s instead of converting it to null',
        (field) => {
            const result = schema.validate({ ...validData, ...registrations, [field]: '24-10-' });
            expect(result.success).toBe(false);
            if (result.success) {
                throw new Error('Tanggal parsial seharusnya ditolak.');
            }
            expect(result.errors[field]?.[0]).toContain('harus berupa tanggal yang valid');
            expect(Object.values(result.errors).flat().join(' ')).not.toContain(':attribute');
        },
    );
});
