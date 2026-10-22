import { Head, Link, usePage } from '@inertiajs/react';
import { columns } from './components/data-table/column';
import { DataTable } from './components/data-table';
import { SectionData } from '@/components/section-data';
import ln from '@/routes/ln';

const pageName = 'Penomoran Surat';

export default function LetterNumberIndex() {
    const { resources } = usePage().props;

    return (
        <>
            <Head title={pageName}>
                <meta name="description" content="Halaman untuk membuat referensi nomor surat" />
            </Head>

            <div className="container mx-auto">
                <SectionData
                    renderButton={<Link href={ln.create().url} />}
                    textButton="Nomor Baru"
                    dataTable={<DataTable columns={columns} data={resources.data} meta={resources.meta} />}
                />
            </div>
        </>
    );
}

LetterNumberIndex.layout = {
    pageName: pageName,
};
