import { defineRequest } from '@/lib/request';
import { check, maxLength, minLength, nonEmpty, object, pipe, string, toNumber, trim } from 'valibot';

export type JobTitleGroupOption = { id: number; label: string };

export function createJobTitleSchema(groups: JobTitleGroupOption[]) {
    return defineRequest(
        object({
            name: pipe(string(), trim(), nonEmpty(), minLength(3), maxLength(100)),
            group: pipe(
                string(),
                nonEmpty(),
                check(
                    (value) => groups.some((group) => String(group.id) === value),
                    ':attribute yang dipilih tidak valid.',
                ),
                toNumber(),
            ),
        }),
        { name: 'Nama Jabatan', group: 'Kelompok Jabatan' },
    );
}
