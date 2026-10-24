import { Head } from '@inertiajs/react';
import { ReferenceGroupForm } from './components/reference-group-form';

const pageName = 'Tambah Daftar Referensi';
export default function ReferenceCreate() {
    return (
        <>
            <Head title={pageName}>
                <meta name="description" content="Tambahkan grup referensi baru." />
            </Head>
            <div className="container mx-auto">
                <ReferenceGroupForm />
            </div>
        </>
    );
}
ReferenceCreate.layout = { pageName };
