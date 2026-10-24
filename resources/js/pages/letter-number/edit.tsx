import { Head } from '@inertiajs/react';
import { LetterNumberForm } from './components/letter-number-form';
import type { LetterNumberColumns } from './components/data-table/column';

const pageName = 'Ubah Penomoran Surat';
export default function LetterNumberEdit({ resource }: { resource: { data: LetterNumberColumns } }) {
    return (
        <>
            <Head title={pageName}>
                <meta name="description" content="Halaman untuk mengubah penomoran surat" />
            </Head>
            <div className="container mx-auto">
                <LetterNumberForm letterNumber={resource.data} />
            </div>
        </>
    );
}
LetterNumberEdit.layout = { pageName };
