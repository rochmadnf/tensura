import { Download04Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import lt from '@/routes/lt';
import { LetterTemplateConfigurationForm } from './components/configuration-form';
import type { ConfigurationOptions, LetterTemplateConfiguration } from '@/schemas/letter-template-configuration';
import { LetterTemplateDocumentForm } from './components/document-form';
import type { LetterTemplateDocument, LetterTemplateDocumentVersion } from '@/schemas/letter-template-document';
import { Button } from '@/components/ui/button';
import { download as exampleDownload } from '@/routes/lt/example';
import { LetterTemplateForm } from './components/letter-template-form';
import type { ClassificationOption, LetterTemplateResource } from '@/schemas/letter-template';

const pageName = 'Ubah Templat Surat';
export default function LetterTemplateEdit({
    classifications,
    resource,
    configuration,
    configurationOptions,
    document,
    documentUploadLimitKb,
    documentVersions,
}: {
    classifications: ClassificationOption[];
    resource: { data: LetterTemplateResource };
    configuration: { data: LetterTemplateConfiguration } | null;
    configurationOptions: ConfigurationOptions;
    document: { data: LetterTemplateDocument } | null;
    documentUploadLimitKb: number;
    documentVersions: LetterTemplateDocumentVersion[];
}) {
    const [isSaving, setIsSaving] = useState(false);
    const [activeCard, setActiveCard] = useState<'identity' | 'configuration' | 'document' | null>(null);
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
                                    Lengkapi variabel dan penandatangan sebelum menyiapkan DOCX untuk publikasi.
                                </p>
                                {document && (
                                    <Button
                                        nativeButton={false}
                                        variant="outline"
                                        size="sm"
                                        className="self-start"
                                        disabled={isSaving}
                                        render={
                                            <a
                                                href={
                                                    exampleDownload({
                                                        letterTemplate: resource.data.id,
                                                        version: document.data.id,
                                                    }).url
                                                }
                                            />
                                        }
                                    >
                                        <HugeiconsIcon
                                            icon={Download04Icon}
                                            data-icon="inline-start"
                                            aria-hidden="true"
                                        />
                                        Unduh DOCX Contoh
                                    </Button>
                                )}
                                <Link href={lt.index().url} className="text-primary underline underline-offset-4">
                                    Kembali ke daftar templat
                                </Link>
                            </div>
                        </CardContent>
                    </Card>
                    {resource.data.draft && (
                        <LetterTemplateForm
                            key={`${resource.data.draft.id}-${activeCard === 'identity'}`}
                            classifications={classifications}
                            editing={activeCard === 'identity'}
                            onEdit={() => setActiveCard('identity')}
                            onClose={() => setActiveCard(null)}
                            letterTemplate={resource.data}
                            disabled={isSaving || (activeCard !== null && activeCard !== 'identity')}
                            onProcessingChange={setIsSaving}
                        />
                    )}
                    {configuration && (
                        <LetterTemplateConfigurationForm
                            key={`${configuration.data.id}-${activeCard === 'configuration'}`}
                            templateId={resource.data.id}
                            configuration={configuration.data}
                            editing={activeCard === 'configuration'}
                            onEdit={() => setActiveCard('configuration')}
                            onClose={() => setActiveCard(null)}
                            options={configurationOptions}
                            disabled={isSaving || (activeCard !== null && activeCard !== 'configuration')}
                            onProcessingChange={setIsSaving}
                        />
                    )}
                    {document && (
                        <LetterTemplateDocumentForm
                            key={`${document.data.id}-${activeCard === 'document'}`}
                            templateId={resource.data.id}
                            document={document.data}
                            editing={activeCard === 'document'}
                            onEdit={() => setActiveCard('document')}
                            onClose={() => setActiveCard(null)}
                            editable={configuration?.data.editable ?? false}
                            maxKb={documentUploadLimitKb}
                            versions={documentVersions}
                            disabled={isSaving || (activeCard !== null && activeCard !== 'document')}
                            onProcessingChange={setIsSaving}
                        />
                    )}
                </div>
            </div>
        </>
    );
}
LetterTemplateEdit.layout = { pageName };
