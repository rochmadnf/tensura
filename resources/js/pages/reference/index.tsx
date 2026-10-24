import { Head, Link } from '@inertiajs/react';
import type { PaginationMeta } from '@/components/data-table';
import { SectionData } from '@/components/section-data';
import ref from '@/routes/ref';
import { ReferenceGroupDataTable } from './components/data-table';
import type { ReferenceGroupColumns } from './components/data-table/column';
import { Button } from '@/components/ui/button';
import { index as archiveIndex } from '@/routes/ref/archive';
import { HugeiconsIcon } from '@hugeicons/react';
import { Archive01Icon } from '@hugeicons/core-free-icons';

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
                    filterAction={
                        <Button variant="outline" nativeButton={false} render={<Link href={archiveIndex()} />}>
                            <HugeiconsIcon icon={Archive01Icon} strokeWidth={2} />
                            Arsip
                        </Button>
                    }
                    dataTable={<ReferenceGroupDataTable data={resources.data} meta={resources.meta} />}
                />
            </div>
        </>
    );
}
ReferenceIndex.layout = { pageName };
