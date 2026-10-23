import { defineRequest } from '@/lib/request';
import {
    check,
    email,
    literal,
    maxLength,
    nonEmpty,
    object,
    pipe,
    string,
    toNumber,
    transform,
    trim,
    union,
} from 'valibot';

export type EmployeeOption = { id: number; label: string };
export interface EmployeeFormOptions {
    jobTitles: { id: string; label: string }[];
    contractStatuses: EmployeeOption[];
    statuses: EmployeeOption[];
    today: string;
}

export interface EmployeeResource {
    id: string;
    nik: string;
    nip: string | null;
    name: { first: string | null; raw: string; full: string; last: string | null };
    job_title: { id: string | null; name: string | null };
    rank: string | null;
    grade: string | null;
    email: string | null;
    join_date: string;
    join_date_input: string;
    tenure_years: number;
    contract_status: { id: number | null; label: string | null };
    status: { id: number | null; label: string | null };
}

export const employeeFieldLabels = {
    nik: 'NIK',
    nip: 'NIP',
    name: 'Nama',
    front_title: 'Gelar Depan',
    back_title: 'Gelar Belakang',
    job_title_id: 'Jabatan',
    rank: 'Pangkat',
    grade: 'Golongan',
    join_date: 'Tanggal Bergabung',
    email: 'Email',
    contract_status: 'Status Kontrak',
    status: 'Status Pegawai',
};

const nullableText = (limit: number) =>
    pipe(
        string(),
        trim(),
        maxLength(limit),
        transform((value) => value || null),
    );
const enumSelection = (options: EmployeeOption[]) =>
    pipe(
        string(),
        nonEmpty(),
        check((value) => options.some((option) => String(option.id) === value), ':attribute yang dipilih tidak valid.'),
        toNumber(),
    );

export function createEmployeeSchema({ jobTitles, contractStatuses, statuses, today }: EmployeeFormOptions) {
    return defineRequest(
        object({
            nik: pipe(
                string(),
                trim(),
                nonEmpty(),
                check((value) => /^[0-9]{16}$/.test(value), ':attribute harus terdiri dari 16 digit angka.'),
            ),
            nip: pipe(
                string(),
                trim(),
                check(
                    (value) => value === '' || /^[0-9]{18}$/.test(value),
                    ':attribute harus terdiri dari 18 digit angka.',
                ),
                transform((value) => value || null),
            ),
            name: pipe(string(), trim(), nonEmpty(), maxLength(100)),
            front_title: nullableText(50),
            back_title: nullableText(50),
            job_title_id: pipe(
                string(),
                nonEmpty(),
                check(
                    (value) => jobTitles.some((option) => option.id === value),
                    ':attribute yang dipilih tidak valid.',
                ),
            ),
            rank: nullableText(50),
            grade: nullableText(50),
            join_date: pipe(
                string(),
                nonEmpty(),
                check((value) => {
                    const date = new Date(`${value}T00:00:00Z`);
                    return (
                        /^\d{4}-\d{2}-\d{2}$/.test(value) &&
                        !Number.isNaN(date.valueOf()) &&
                        date.toISOString().slice(0, 10) === value
                    );
                }, ':attribute harus berupa tanggal yang valid.'),
                check((value) => value <= today, ':attribute maksimal tanggal hari ini.'),
            ),
            email: pipe(
                string(),
                trim(),
                maxLength(100),
                union([literal(''), pipe(string(), email())], ':attribute harus berupa email yang valid.'),
                transform((value) => value || null),
            ),
            contract_status: enumSelection(contractStatuses),
            status: enumSelection(statuses),
        }),
        employeeFieldLabels,
    );
}
