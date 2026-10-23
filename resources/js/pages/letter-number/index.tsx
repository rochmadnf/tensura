import { Head, Link } from '@inertiajs/react';
import { LetterNumberDataTable } from './components/data-table';
import type { LetterNumberColumns } from './components/data-table/column';
import type { PaginationMeta } from '@/components/data-table';
import { SectionData } from '@/components/section-data';
import ln from '@/routes/ln';

const pageName = 'Penomoran Surat';

interface LetterNumberIndexProps {
    resources: { data: LetterNumberColumns[]; meta: PaginationMeta };
    filters: { keyword: string };
}

export default function LetterNumberIndex({ resources, filters }: LetterNumberIndexProps) {
    return (
        <>
            <Head title={pageName}>
                <meta name="description" content="Halaman untuk membuat referensi nomor surat" />
            </Head>

            <div className="container mx-auto">
                <SectionData
                    renderButton={<Link href={ln.create().url} />}
                    textButton="Nomor Baru"
                    searchUrl={ln.index().url}
                    defaultKeyword={filters.keyword}
                    dataTable={<LetterNumberDataTable data={resources.data} meta={resources.meta} />}
                />
            </div>
        </>
    );
}

LetterNumberIndex.layout = {
    pageName: pageName,
};
