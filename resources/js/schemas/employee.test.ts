import { describe, expect, it } from 'vite-plus/test';
import { createEmployeeSchema, type EmployeeFormOptions } from './employee';

const options: EmployeeFormOptions = {
    jobTitles: [{ id: '00000000-0000-4000-8000-000000000001', label: 'Dokter' }],
    contractStatuses: [{ id: 1, label: 'PNS' }],
    statuses: [{ id: 1, label: 'Aktif' }],
    today: '2026-10-24',
};
const schema = createEmployeeSchema(options);
const validData = {
    nik: '0000000000000001',
    nip: '000000000000000001',
    name: 'Siti',
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
});
