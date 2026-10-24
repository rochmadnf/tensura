import { Head } from '@inertiajs/react';
import { JobTitleForm } from './components/job-title-form';
import type { JobTitleGroupOption } from '@/schemas/job-title';
import type { JobTitleColumns } from './components/data-table/column';

const pageName = 'Ubah Jabatan';
export default function JobTitleEdit({
    groups,
    resource,
}: {
    groups: JobTitleGroupOption[];
    resource: { data: JobTitleColumns };
}) {
    return (
        <>
            <Head title={pageName}>
                <meta name="description" content="Halaman untuk mengubah data jabatan" />
            </Head>
            <div className="container mx-auto">
                <JobTitleForm groups={groups} jobTitle={resource.data} />
            </div>
        </>
    );
}
JobTitleEdit.layout = { pageName };
