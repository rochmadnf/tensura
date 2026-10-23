import { Head, Link } from '@inertiajs/react';
import { JobTitleDataTable } from './components/data-table';
import type { JobTitleColumns } from './components/data-table/column';
import type { PaginationMeta } from '@/components/data-table';
import { SectionData } from '@/components/section-data';
import jt from '@/routes/jt';

const pageName = 'Jabatan';

interface JobTitleIndexProps {
    resources: { data: JobTitleColumns[]; meta: PaginationMeta };
    filters: { keyword: string };
}

export default function JobTitleIndex({ resources, filters }: JobTitleIndexProps) {
    return (
        <>
            <Head title={pageName}>
                <meta name="description" content="Halaman untuk mengelola data jabatan" />
            </Head>

            <div className="container mx-auto">
                <SectionData
                    renderButton={<Link href={jt.create().url} />}
                    textButton="Jabatan Baru"
                    searchUrl={jt.index().url}
                    defaultKeyword={filters.keyword}
                    dataTable={<JobTitleDataTable data={resources.data} meta={resources.meta} />}
                />
            </div>
        </>
    );
}

JobTitleIndex.layout = {
    pageName: pageName,
};
