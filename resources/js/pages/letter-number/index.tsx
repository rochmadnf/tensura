import { Head, Link } from '@inertiajs/react';
import { columns } from './components/data-table/column';
import { DataTable } from './components/data-table';
import { SectionData } from '@/components/section-data';
import { dashboard } from '@/routes';

const pageName = 'Penomoran Surat';

export default function LetterNumberIndex() {
    return (
        <>
            <Head title={pageName}>
                <meta name="description" content="Halaman untuk membuat referensi nomor surat" />
            </Head>

            <div className="container mx-auto py-10">
                <SectionData renderButton={<Link href={dashboard().url} />} />
                <DataTable
                    columns={columns}
                    data={[
                        {
                            id: '728ed52f',
                            amount: 100,
                            status: 'pending',
                            email: 'm@example.com',
                        },
                        // ...
                    ]}
                />
            </div>
        </>
    );
}

LetterNumberIndex.layout = {
    pageName: pageName,
};
