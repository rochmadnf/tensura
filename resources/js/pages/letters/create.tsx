import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { FormCard } from '@/components/form-card';
import { OptionSelect } from '@/components/option-select';
import { Button } from '@/components/ui/button';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import type { LetterConfiguration } from '@/schemas/letter';
import { create, index } from '@/routes/ltr';
import { LetterForm } from './components/letter-form';

export default function LetterCreate({
    selectedTemplate,
    configuration,
    today,
}: {
    selectedTemplate: { id: string; name: string } | null;
    configuration: LetterConfiguration | null;
    today: string;
}) {
    const [selected, setSelected] = useState('');
    const [processing, setProcessing] = useState(false);
    return (
        <>
            <Head title="Surat Baru">
                <meta name="description" content="Buat draft surat dari templat aktif" />
            </Head>
            <div className="container mx-auto flex min-w-0 flex-col gap-6">
                <Link href={index().url} className="self-start text-sm text-primary underline underline-offset-4">
                    Kembali ke daftar surat
                </Link>
                {configuration && selectedTemplate ? (
                    <LetterForm
                        configuration={configuration}
                        templateId={selectedTemplate.id}
                        templateName={selectedTemplate.name}
                        today={today}
                    />
                ) : (
                    <FormCard
                        sectioned
                        title="Pilih Templat Surat"
                        description="Hanya templat aktif dengan versi terbit yang dapat digunakan untuk membuat draft baru."
                    >
                        <FieldGroup>
                            <Field>
                                <FieldLabel htmlFor="letter-template">Templat</FieldLabel>
                                <OptionSelect
                                    id="letter-template"
                                    name="template"
                                    items={[]}
                                    remote={{ source: 'letter-templates' }}
                                    value={selected}
                                    onValueChange={setSelected}
                                    disabled={processing}
                                    placeholder="Cari templat aktif"
                                />
                            </Field>
                            <Button
                                type="button"
                                className="self-start"
                                disabled={!selected || processing}
                                onClick={() =>
                                    router.get(
                                        create({ query: { template: selected } }).url,
                                        {},
                                        { onStart: () => setProcessing(true), onFinish: () => setProcessing(false) },
                                    )
                                }
                            >
                                {processing ? 'Membuka…' : 'Gunakan templat'}
                            </Button>
                        </FieldGroup>
                    </FormCard>
                )}
            </div>
        </>
    );
}
LetterCreate.layout = { pageName: 'Surat Baru' };
