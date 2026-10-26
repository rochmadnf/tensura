import { parseIsoDate } from '@/lib/date-input';
import { defineRequest } from '@/lib/request';
import {
    check,
    email,
    forward,
    length,
    literal,
    maxLength,
    nonEmpty,
    object,
    partialCheck,
    pipe,
    string,
    toNumber,
    transform,
    trim,
    union,
} from 'valibot';

export type EmployeeOption = { id: number; label: string };
export type EmployeeJobTitleOption = { id: string; label: string; group: number | null };
export interface EmployeeFormOptions {
    jobTitles: EmployeeJobTitleOption[];
    genders: EmployeeOption[];
    strStatuses: EmployeeOption[];
    contractStatuses: EmployeeOption[];
    statuses: EmployeeOption[];
    today: string;
}

type NullableOption = { id: number | null; label: string | null };
export interface EmployeeResource {
    id: string;
    nik: string;
    nip: string | null;
    name: { first: string | null; raw: string; full: string; last: string | null };
    gender: { id: number; label: string };
    job_title: { id: string | null; name: string | null; group: NullableOption };
    rank: string | null;
    grade: string | null;
    email: string | null;
    join_date: string | null;
    join_date_input: string | null;
    tenure_years: number | null;
    str: {
        number: string;
        status: NullableOption;
        published_date: string | null;
        published_date_input: string | null;
    } | null;
    sip: {
        number: string;
        published_date: string | null;
        published_date_input: string | null;
        expired_date: string | null;
        expired_date_input: string | null;
    } | null;
    contract_status: NullableOption;
    status: NullableOption;
}

export const employeeFieldLabels = {
    nik: 'NIK',
    nip: 'NIP',
    name: 'Nama',
    gender: 'Jenis Kelamin',
    front_title: 'Gelar Depan',
    back_title: 'Gelar Belakang',
    job_title_id: 'Jabatan',
    rank: 'Pangkat',
    grade: 'Golongan',
    join_date: 'Tanggal Bergabung',
    email: 'Email',
    contract_status: 'Status Kontrak',
    status: 'Status Pegawai',
    str_number: 'Nomor STR',
    str_status: 'Status STR',
    str_published_date: 'Tanggal Terbit STR',
    sip_number: 'Nomor SIP',
    sip_published_date: 'Tanggal Terbit SIP',
    sip_expired_date: 'Tanggal Berakhir SIP',
};

const nullableText = (limit: number) =>
    pipe(
        string(),
        trim(),
        maxLength(limit),
        transform((value) => (value === '' ? null : value)),
    );
const enumSelection = (options: EmployeeOption[]) =>
    pipe(
        string(),
        nonEmpty(),
        check((value) => options.some((option) => String(option.id) === value), ':attribute yang dipilih tidak valid.'),
        toNumber(),
    );
const optionalDate = (today?: string) =>
    pipe(
        string(),
        check((value) => value === '' || parseIsoDate(value) !== null, ':attribute harus berupa tanggal yang valid.'),
        check(
            (value) => value === '' || today === undefined || value <= today,
            ':attribute maksimal tanggal hari ini.',
        ),
        transform((value) => (value === '' ? null : value)),
    );

export function createEmployeeSchema({ genders, strStatuses, contractStatuses, statuses, today }: EmployeeFormOptions) {
    return defineRequest(
        pipe(
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
                    transform((value) => (value === '' ? null : value)),
                ),
                name: pipe(string(), trim(), nonEmpty(), maxLength(100)),
                gender: enumSelection(genders),
                front_title: nullableText(50),
                back_title: nullableText(50),
                job_title_id: pipe(
                    string(),
                    nonEmpty(),
                    check(
                        (value) => /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(value),
                        ':attribute yang dipilih tidak valid.',
                    ),
                ),
                rank: nullableText(50),
                grade: nullableText(50),
                join_date: pipe(
                    string(),
                    nonEmpty(),
                    check((value) => parseIsoDate(value) !== null, ':attribute harus berupa tanggal yang valid.'),
                    check((value) => value <= today, ':attribute maksimal tanggal hari ini.'),
                ),
                email: pipe(
                    string(),
                    trim(),
                    maxLength(100),
                    union([literal(''), pipe(string(), email())], ':attribute harus berupa email yang valid.'),
                    transform((value) => (value === '' ? null : value)),
                ),
                contract_status: enumSelection(contractStatuses),
                status: enumSelection(statuses),
                str_number: pipe(
                    string(),
                    trim(),
                    union([literal(''), pipe(string(), length(16))], ':attribute harus terdiri dari 16 karakter.'),
                    transform((value) => (value === '' ? null : value)),
                ),
                str_status: pipe(
                    string(),
                    check(
                        (value) => value === '' || strStatuses.some((option) => String(option.id) === value),
                        ':attribute yang dipilih tidak valid.',
                    ),
                    transform((value) => (value === '' ? null : Number(value))),
                ),
                str_published_date: optionalDate(today),
                sip_number: nullableText(30),
                sip_published_date: optionalDate(today),
                sip_expired_date: optionalDate(),
            }),
            forward(
                partialCheck(
                    [['str_number'], ['str_status'], ['str_published_date']],
                    (data) =>
                        data.str_number !== null || (data.str_status === null && data.str_published_date === null),
                    'Nomor STR wajib diisi bersama seluruh data STR.',
                ),
                ['str_number'],
            ),
            forward(
                partialCheck(
                    [['str_number'], ['str_status'], ['str_published_date']],
                    (data) =>
                        data.str_status !== null || (data.str_number === null && data.str_published_date === null),
                    'Status STR wajib diisi bersama seluruh data STR.',
                ),
                ['str_status'],
            ),
            forward(
                partialCheck(
                    [['str_number'], ['str_status'], ['str_published_date']],
                    (data) =>
                        data.str_published_date !== null || (data.str_number === null && data.str_status === null),
                    'Tanggal Terbit STR wajib diisi bersama seluruh data STR.',
                ),
                ['str_published_date'],
            ),
            forward(
                partialCheck(
                    [['sip_number'], ['sip_published_date'], ['sip_expired_date']],
                    (data) =>
                        data.sip_number !== null ||
                        (data.sip_published_date === null && data.sip_expired_date === null),
                    'Nomor SIP wajib diisi bersama seluruh data SIP.',
                ),
                ['sip_number'],
            ),
            forward(
                partialCheck(
                    [['sip_number'], ['sip_published_date'], ['sip_expired_date']],
                    (data) =>
                        data.sip_published_date !== null ||
                        (data.sip_number === null && data.sip_expired_date === null),
                    'Tanggal Terbit SIP wajib diisi bersama seluruh data SIP.',
                ),
                ['sip_published_date'],
            ),
            forward(
                partialCheck(
                    [['sip_number'], ['sip_published_date'], ['sip_expired_date']],
                    (data) =>
                        data.sip_expired_date !== null ||
                        (data.sip_number === null && data.sip_published_date === null),
                    'Tanggal Berakhir SIP wajib diisi bersama seluruh data SIP.',
                ),
                ['sip_expired_date'],
            ),
            forward(
                partialCheck(
                    [['sip_published_date'], ['sip_expired_date']],
                    (data) =>
                        data.sip_published_date === null ||
                        data.sip_expired_date === null ||
                        data.sip_expired_date > data.sip_published_date,
                    'Tanggal Berakhir SIP harus setelah Tanggal Terbit SIP.',
                ),
                ['sip_expired_date'],
            ),
        ),
        employeeFieldLabels,
    );
}
