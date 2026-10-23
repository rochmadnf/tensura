import { Head } from '@inertiajs/react';
import type { EmployeeFormOptions, EmployeeResource } from '@/schemas/employee';
import { EmployeeForm } from './components/employee-form';

const pageName = 'Ubah Pegawai';

interface EmployeeEditProps extends EmployeeFormOptions {
    resource: { data: EmployeeResource };
}

export default function EmployeeEdit({ resource, ...options }: EmployeeEditProps) {
    return (
        <>
            <Head title={pageName}>
                <meta name="description" content="Halaman untuk mengubah data pegawai" />
            </Head>
            <div className="container mx-auto">
                <EmployeeForm {...options} employee={resource.data} />
            </div>
        </>
    );
}

EmployeeEdit.layout = { pageName };
