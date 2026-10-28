import { SystemFieldsForm } from './components/system-fields-form';
import { LetterTemplateLifecycleCard } from './components/lifecycle-card';
import { LetterTemplateVersionTimeline } from './components/version-timeline';
import type { LetterTemplateLifecycle } from '@/schemas/letter-template-lifecycle';
import { LetterTemplateTestForm } from './components/test-form';
import type { LetterTemplateTest } from '@/schemas/letter-template-test';
import { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import lt from '@/routes/lt';
import exchange from '@/routes/lt/exchange';
import { Button } from '@/components/ui/button';
import { LetterTemplateConfigurationForm } from './components/configuration-form';
import type { ConfigurationOptions, LetterTemplateConfiguration } from '@/schemas/letter-template-configuration';
import { LetterTemplateDocumentForm } from './components/document-form';
import type { LetterTemplateDocument, LetterTemplateDocumentVersion } from '@/schemas/letter-template-document';
import { LetterTemplateForm } from './components/letter-template-form';
import type { ClassificationOption, LetterTemplateResource } from '@/schemas/letter-template';

const pageName = 'Ubah Templat Surat';
export default function LetterTemplateEdit({
    audits,
    classifications,
    resource,
    configuration,
    configurationOptions,
    document,
    documentUploadLimitKb,
    documentVersions,
    test,
    lifecycle,
}: {
    audits: import('@/schemas/letter-template-lifecycle').LetterTemplateAuditPage;
    lifecycle?: { data: LetterTemplateLifecycle } | null;
    classifications: ClassificationOption[];
    resource: { data: LetterTemplateResource };
    configuration: { data: LetterTemplateConfiguration } | null;
    configurationOptions: ConfigurationOptions;
    document: { data: LetterTemplateDocument } | null;
    documentUploadLimitKb: number;
    documentVersions: LetterTemplateDocumentVersion[];
    test?: { data: LetterTemplateTest } | null;
}) {
    const [isSaving, setIsSaving] = useState(false);
    const [activeCard, setActiveCard] = useState<string | null>(null);
    return (
        <>
            <Head title={pageName}>
                <meta name="description" content="Halaman untuk mengubah data templat surat" />
            </Head>
            <div className="container mx-auto flex min-w-0 flex-col gap-6">
                <Link href={lt.index().url} className="self-start text-sm text-primary underline underline-offset-4">
                    Kembali ke daftar templat
                </Link>
                {document?.data.source && lifecycle && (
                    <Button
                        variant="outline"
                        nativeButton={false}
                        disabled={isSaving || activeCard !== null}
                        render={
                            <Link
                                href={
                                    exchange.exportPage({
                                        letterTemplate: resource.data.id,
                                        version: lifecycle.data.id,
                                    }).url
                                }
                            />
                        }
                        className="self-start"
                    >
                        Ekspor Versi {lifecycle.data.number}
                    </Button>
                )}
                <LetterTemplateForm
                    key={`identity-${lifecycle?.data.id ?? resource.data.id}-${activeCard === 'identity'}`}
                    classifications={classifications}
                    editing={activeCard === 'identity'}
                    onEdit={() => setActiveCard('identity')}
                    onClose={() => setActiveCard(null)}
                    letterTemplate={resource.data}
                    selectedVersion={lifecycle?.data}
                    disabled={isSaving || (activeCard !== null && activeCard !== 'identity')}
                    onProcessingChange={setIsSaving}
                />
                {configuration && (
                    <LetterTemplateConfigurationForm
                        key={`configuration-${configuration.data.id}`}
                        templateId={resource.data.id}
                        configuration={configuration.data}
                        activeEditor={activeCard}
                        onEdit={setActiveCard}
                        onClose={() => setActiveCard(null)}
                        options={configurationOptions}
                        disabled={isSaving}
                        onProcessingChange={setIsSaving}
                    />
                )}
                {document && (
                    <SystemFieldsForm
                        key={`system-${document.data.id}-${document.data.letter_date_format}-${activeCard === 'system'}`}
                        templateId={resource.data.id}
                        versionId={document.data.id}
                        format={document.data.letter_date_format ?? 'd-m-Y'}
                        editable={configuration?.data.editable ?? false}
                        editing={activeCard === 'system'}
                        disabled={isSaving || (activeCard !== null && activeCard !== 'system')}
                        onEdit={() => setActiveCard('system')}
                        onClose={() => setActiveCard(null)}
                        onProcessingChange={setIsSaving}
                    />
                )}
                {document && (
                    <LetterTemplateDocumentForm
                        key={`document-${document.data.id}-${activeCard === 'document'}`}
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
                {test && (
                    <LetterTemplateTestForm
                        key={`test-${test.data.id}-${test.data.validation_token}-${test.data.can_generate}`}
                        templateId={resource.data.id}
                        test={test.data}
                        readOnly={lifecycle ? !lifecycle.data.editable : false}
                        disabled={isSaving || activeCard !== null}
                        onProcessingChange={setIsSaving}
                    />
                )}
                {lifecycle && (
                    <>
                        <LetterTemplateLifecycleCard
                            key={`lifecycle-${lifecycle.data.id}`}
                            templateId={resource.data.id}
                            lifecycle={lifecycle.data}
                            testToken={test?.data.test_token ?? null}
                            isActive={resource.data.is_active}
                            disabled={isSaving || (activeCard !== null && activeCard !== 'lifecycle')}
                            onOpen={() => setActiveCard('lifecycle')}
                            onClose={() => setActiveCard(null)}
                            onProcessingChange={setIsSaving}
                        />
                        <LetterTemplateVersionTimeline
                            key={`timeline-${resource.data.id}`}
                            templateId={resource.data.id}
                            lifecycle={lifecycle.data}
                            initialAudits={audits}
                            disabled={isSaving || activeCard !== null}
                        />
                    </>
                )}
            </div>
        </>
    );
}
LetterTemplateEdit.layout = { pageName };
