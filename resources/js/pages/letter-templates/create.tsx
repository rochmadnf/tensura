import { Head } from '@inertiajs/react';
import { LetterTemplateForm } from './components/letter-template-form';
import type { ClassificationOption } from '@/schemas/letter-template';

const pageName = 'Tambah Templat Surat';
export default function LetterTemplateCreate({ classifications }: { classifications: ClassificationOption[] }) {
    return (
        <>
            <Head title={pageName}>
                <meta name="description" content="Halaman untuk menambahkan templat surat" />
            </Head>
            <div className="container mx-auto">
                <LetterTemplateForm classifications={classifications} />
            </div>
        </>
    );
}
LetterTemplateCreate.layout = { pageName };
