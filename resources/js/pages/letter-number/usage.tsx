import { Head, Link } from '@inertiajs/react';
import { FormCard } from '@/components/form-card';
import { ResourceDataTable } from '@/components/resource-data-table';
import { createDataTableColumnHelper, type PaginationMeta } from '@/components/data-table';
import ln from '@/routes/ln';
import { show as letterShow } from '@/routes/ltr';

type Usage = {
    id: string;
    letter_id: string;
    subject: string;
    number: string;
    mode: string;
    claimed_at: string;
    active: boolean;
    status: string;
};
const helper = createDataTableColumnHelper<Usage>();
const columns = helper.columns([
    helper.accessor('number', {
        header: 'Nomor',
        cell: ({ row, cell }) => (
            <Link href={letterShow(row.original.letter_id).url} className="text-primary underline underline-offset-4">
                {cell.getValue()}
            </Link>
        ),
    }),
    helper.accessor('subject', { header: 'Perihal' }),
    helper.accessor('mode', { header: 'Mode' }),
    helper.accessor('status', { header: 'Status surat' }),
    helper.accessor('active', { header: 'Klaim', cell: ({ cell }) => (cell.getValue() ? 'Aktif' : 'Dilepas') }),
    helper.accessor('claimed_at', {
        header: 'Waktu klaim',
        cell: ({ cell }) => new Date(cell.getValue()).toLocaleString('id-ID'),
    }),
]);
export default function LetterNumberUsage({
    classification,
    period,
    resources,
}: {
    classification: { id: string; code: string; name: string };
    period: { id: string; year: number; month: number };
    resources: { data: Usage[]; meta: PaginationMeta };
}) {
    return (
        <>
            <Head title="Pemakaian Nomor Surat" />
            <Link
                href={ln.show(classification.id).url}
                className="self-start text-sm text-primary underline underline-offset-4"
            >
                Kembali ke penomoran surat
            </Link>
            <FormCard
                sectioned
                title="Pemakaian Nomor Surat"
                description={`${classification.code} · ${classification.name} · ${String(period.month).padStart(2, '0')}/${period.year}`}
            >
                <ResourceDataTable
                    data={resources.data}
                    meta={resources.meta}
                    columns={columns}
                    url={ln.periods.usage({ letterNumber: classification.id, period: period.id }).url}
                    getRowId={(row) => row.id}
                    emptyMessage="Belum ada surat yang memakai nomor pada periode ini."
                />
            </FormCard>
        </>
    );
}
LetterNumberUsage.layout = { pageName: 'Pemakaian Nomor Surat' };
