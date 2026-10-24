import { Head, Link } from '@inertiajs/react';
import type { PaginationMeta } from '@/components/data-table';
import { SectionData } from '@/components/section-data';
import ref from '@/routes/ref';
import { ReferenceGroupDataTable } from './components/data-table';
import type { ReferenceGroupColumns } from './components/data-table/column';
import { index as archiveIndex } from '@/routes/ref/archive';

const pageName = 'Daftar Referensi';
interface ReferenceIndexProps {
    resources: { data: ReferenceGroupColumns[]; meta: PaginationMeta };
    filters: { keyword: string };
}
export default function ReferenceIndex({ resources, filters }: ReferenceIndexProps) {
    return (
        <>
            <Head title={pageName}>
                <meta name="description" content="Kelola grup dan item referensi." />
            </Head>
            <div className="container mx-auto">
                <SectionData
                    renderButton={<Link href={ref.create().url} />}
                    textButton="Referensi Baru"
                    searchUrl={ref.index().url}
                    defaultKeyword={filters.keyword}
                    archiveUrl={archiveIndex().url}
                    dataTable={<ReferenceGroupDataTable data={resources.data} meta={resources.meta} />}
                />
            </div>
        </>
    );
}
ReferenceIndex.layout = { pageName };
