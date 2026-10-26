import { Head, Link } from '@inertiajs/react';
import { FormCard } from '@/components/form-card';
import { ResourceDataTable } from '@/components/resource-data-table';
import { createDataTableColumnHelper, type PaginationMeta } from '@/components/data-table';
import { FieldDescription } from '@/components/ui/field';
import { Badge } from '@/components/ui/badge';
import ln from '@/routes/ln';
import type { LetterNumberPeriod } from '@/schemas/letter-number-period';
import type { LetterNumberColumns } from './components/data-table/column';
import { PeriodForm } from './components/period-form';

const helper = createDataTableColumnHelper<LetterNumberPeriod>();
const columns = helper.columns([
    helper.accessor('label', { header: 'Periode' }),
    helper.accessor('last_position', {
        header: 'Posisi urutan otomatis',
        cell: ({ cell }) => <span className="tabular-nums">{cell.getValue() ?? 'Belum digunakan'}</span>,
    }),
    helper.accessor('next_position', {
        header: 'Perkiraan berikutnya',
        cell: ({ cell }) => <span className="tabular-nums">{cell.getValue() ?? 'Batas nomor tercapai'}</span>,
    }),
]);

const pageName = 'Detail Penomoran Surat';
export default function LetterNumberShow({
    resource,
    resources,
    periodDefaults,
}: {
    resource: { data: LetterNumberColumns };
    resources: { data: LetterNumberPeriod[]; meta: PaginationMeta };
    periodDefaults: { year: number; month: number };
}) {
    const record = resource.data;
    return (
        <>
            <Head title={pageName}>
                <meta name="description" content="Identitas klasifikasi dan urutan nomor surat per bulan dan tahun" />
            </Head>
            <div className="container mx-auto flex min-w-0 flex-col gap-6">
                <Link href={ln.index().url} className="self-start text-sm text-primary underline underline-offset-4">
                    Kembali ke penomoran surat
                </Link>
                <FormCard
                    sectioned
                    title={record.name}
                    description={`Kode klasifikasi ${record.code}`}
                    action={
                        <Link
                            href={ln.edit(record.id).url}
                            className="text-sm text-primary underline underline-offset-4"
                        >
                            Ubah identitas
                        </Link>
                    }
                >
                    <Badge variant={record.needs_initialization ? 'outline' : 'secondary'}>
                        {record.needs_initialization ? 'Angka lama belum dipindahkan' : 'Penomoran per periode'}
                    </Badge>
                </FormCard>
                <FormCard
                    sectioned
                    title="Urutan per Periode"
                    description="Setiap bulan dan tahun memiliki urutan sendiri. Perkiraan belum memesan nomor resmi."
                >
                    <div className="flex min-w-0 flex-col gap-4">
                        <ResourceDataTable
                            data={resources.data}
                            meta={resources.meta}
                            columns={columns}
                            url={ln.show(record.id).url}
                            getRowId={(row) => row.id}
                            emptyMessage="Belum ada periode. Siapkan periode pertama di bawah."
                        />
                        <FieldDescription>
                            Daftar pemakaian surat akan tersedia setelah fitur penerbitan selesai. Posisi tersimpan
                            tidak dapat diedit di tahap ini.
                        </FieldDescription>
                    </div>
                </FormCard>
                <PeriodForm
                    key={record.needs_initialization ? 'legacy' : 'period'}
                    letterNumber={record}
                    defaults={periodDefaults}
                />
            </div>
        </>
    );
}
LetterNumberShow.layout = { pageName };
