import {
    check,
    forward,
    integer,
    maxValue,
    minValue,
    nullable,
    object,
    picklist,
    pipe,
    string,
    toNumber,
} from 'valibot';
import { defineRequest } from '@/lib/request';

export const LetterNumberPeriodSchema = defineRequest(
    pipe(
        object({
            year: pipe(string(), toNumber(), integer(), minValue(1), maxValue(9999)),
            month: pipe(string(), toNumber(), integer(), minValue(1), maxValue(12)),
            mode: picklist(['legacy', 'empty', 'custom']),
            last_page_number: nullable(pipe(string(), toNumber(), integer(), minValue(1), maxValue(2147483647))),
            last_sequence_number: nullable(pipe(string(), toNumber(), integer(), minValue(1), maxValue(99))),
        }),
        forward(
            check((input) => input.mode !== 'custom' || input.last_page_number !== null, ':attribute wajib diisi.'),
            ['last_page_number'],
        ),
        forward(
            check((input) => input.mode !== 'custom' || input.last_sequence_number !== null, ':attribute wajib diisi.'),
            ['last_sequence_number'],
        ),
    ),
    {
        year: 'Tahun',
        month: 'Bulan',
        mode: 'Sumber angka awal',
        last_page_number: 'Halaman terakhir',
        last_sequence_number: 'Nomor urut terakhir',
    },
);

export type LetterNumberPeriod = {
    id: string;
    year: number;
    month: number;
    label: string;
    last_position: string | null;
    next_position: string | null;
};
