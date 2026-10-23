import { Head } from '@inertiajs/react';
import type { EmployeeFormOptions } from '@/schemas/employee';
import { EmployeeForm } from './components/employee-form';

const pageName = 'Tambah Pegawai';

interface EmployeeCreateProps extends EmployeeFormOptions {}

export default function EmployeeCreate(props: EmployeeCreateProps) {
    return (
        <>
            <Head title={pageName}>
                <meta name="description" content="Halaman untuk menambahkan pegawai" />
            </Head>
            <div className="container mx-auto">
                <EmployeeForm {...props} />
            </div>
        </>
    );
}

EmployeeCreate.layout = { pageName };
