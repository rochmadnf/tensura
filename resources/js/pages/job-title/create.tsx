import { Head } from '@inertiajs/react';
import { JobTitleForm } from './components/job-title-form';
import type { JobTitleGroupOption } from '@/schemas/job-title';

const pageName = 'Tambah Jabatan';
export default function JobTitleCreate({ groups }: { groups: JobTitleGroupOption[] }) {
    return (
        <>
            <Head title={pageName}>
                <meta name="description" content="Halaman untuk menambahkan jabatan" />
            </Head>
            <div className="container mx-auto">
                <JobTitleForm groups={groups} />
            </div>
        </>
    );
}
JobTitleCreate.layout = { pageName };
