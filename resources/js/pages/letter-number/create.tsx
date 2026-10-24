import { Head } from '@inertiajs/react';
import { LetterNumberForm } from './components/letter-number-form';

const pageName = 'Tambah Penomoran Surat';
export default function LetterNumberCreate() {
    return (
        <>
            <Head title={pageName}>
                <meta name="description" content="Halaman untuk menambahkan penomoran surat baru" />
            </Head>
            <div className="container mx-auto">
                <LetterNumberForm />
            </div>
        </>
    );
}
LetterNumberCreate.layout = { pageName };
