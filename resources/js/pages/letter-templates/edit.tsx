import { Head, Link } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import lt from '@/routes/lt';
import { LetterTemplateForm } from './components/letter-template-form';
import type { ClassificationOption, LetterTemplateResource } from '@/schemas/letter-template';

const pageName = 'Ubah Templat Surat';
export default function LetterTemplateEdit({
    classifications,
    resource,
}: {
    classifications: ClassificationOption[];
    resource: { data: LetterTemplateResource };
}) {
    return (
        <>
            <Head title={pageName}>
                <meta name="description" content="Halaman untuk mengubah data templat surat" />
            </Head>
            <div className="container mx-auto">
                <div className="flex flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>{resource.data.name}</CardTitle>
                            <CardDescription>
                                {resource.data.draft
                                    ? `Draft versi ${resource.data.draft.number}`
                                    : 'Tidak ada draft yang dapat diubah.'}
                                {' · '}
                                {resource.data.is_active ? 'Aktif' : 'Nonaktif'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex flex-col gap-3 text-sm">
                                <p>
                                    {resource.data.classification
                                        ? `${resource.data.classification.code} — ${resource.data.classification.name}`
                                        : 'Klasifikasi belum tersedia.'}
                                </p>
                                <p className="text-muted-foreground">
                                    Simpan identitas draft sebelum melengkapi konfigurasi, DOCX, dan publikasi.
                                </p>
                                <Link href={lt.index().url} className="text-primary underline underline-offset-4">
                                    Kembali ke daftar templat
                                </Link>
                            </div>
                        </CardContent>
                    </Card>
                    {resource.data.draft && (
                        <LetterTemplateForm
                            key={resource.data.draft.id}
                            classifications={classifications}
                            letterTemplate={resource.data}
                        />
                    )}
                </div>
            </div>
        </>
    );
}
LetterTemplateEdit.layout = { pageName };
