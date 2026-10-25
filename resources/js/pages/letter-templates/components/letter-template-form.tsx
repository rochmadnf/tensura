import type { LetterTemplateLifecycle } from '@/schemas/letter-template-lifecycle';
import { TemplateEditAction } from './edit-action';
import { OptionSelect } from '@/components/option-select';
import { FormCard } from '@/components/form-card';
import { FormActions } from '@/components/form-actions';
import { Field, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { gooeyToast } from '@/components/ui/goey-toaster';
import { Input } from '@/components/ui/input';
import { useValibotForm } from '@/hooks/use-valibot-form';
import lt from '@/routes/lt';
import {
    createLetterTemplateSchema,
    type ClassificationOption,
    type LetterTemplateResource,
} from '@/schemas/letter-template';
import type { SubmitEventHandler } from 'react';

export function LetterTemplateForm({
    classifications,
    letterTemplate,
    selectedVersion,
    disabled = false,
    onProcessingChange,
    editing = true,
    onEdit,
    onClose,
}: {
    classifications: ClassificationOption[];
    letterTemplate?: LetterTemplateResource;
    selectedVersion?: Pick<LetterTemplateLifecycle, 'id' | 'number' | 'status_label' | 'editable' | 'classification'>;
    disabled?: boolean;
    onProcessingChange?: (processing: boolean) => void;
    editing?: boolean;
    onEdit?: () => void;
    onClose?: () => void;
}) {
    const pageName = letterTemplate ? 'Ubah Templat Surat' : 'Tambah Templat Surat';
    const { form, submit, bind } = useValibotForm(createLetterTemplateSchema(classifications), {
        name: letterTemplate?.name ?? '',
        letter_number: selectedVersion?.classification.id ?? letterTemplate?.draft?.letter_number ?? '',
    });
    const canEdit = selectedVersion?.editable ?? !!letterTemplate?.draft;
    const busy = disabled || form.processing;
    const classificationItems = classifications.map((item) => ({ value: item.id, label: item.label }));

    const handleSubmit: SubmitEventHandler<HTMLFormElement> = (event) => {
        event.preventDefault();

        if (busy || (letterTemplate && !canEdit)) return;
        const action = letterTemplate ? lt.update(letterTemplate.id) : lt.store();
        const submitted = submit(action.method, action.url, {
            onStart: () => onProcessingChange?.(true),
            onFinish: () => onProcessingChange?.(false),
            onError: () => {
                gooeyToast.error('Galat', { description: 'Terdapat data yang tidak valid.' });
            },
            preserveScroll: true,
            onSuccess: () => {
                onClose?.();
                gooeyToast.success('Berhasil', {
                    description: (
                        <p>
                            <strong>{form.data.name}</strong> berhasil{' '}
                            {letterTemplate ? 'diubah' : 'disimpan sebagai draft'}.
                        </p>
                    ),
                });
            },
        });

        if (!submitted) {
            gooeyToast.error('Galat', { description: 'Terdapat data yang tidak valid.' });
        }
    };

    if (letterTemplate && (!editing || !canEdit)) {
        return (
            <FormCard
                title="Identitas Templat"
                description={`${selectedVersion ? `${selectedVersion.status_label} versi ${selectedVersion.number}` : letterTemplate.draft ? `Draft versi ${letterTemplate.draft.number}` : 'Tidak ada draft yang dapat diubah'} · ${letterTemplate.is_active ? 'Aktif' : 'Nonaktif'}`}
                action={
                    canEdit ? (
                        <TemplateEditAction label="identitas" disabled={disabled} onClick={() => onEdit?.()} />
                    ) : undefined
                }
            >
                <dl className="flex flex-col gap-3 text-sm">
                    <div>
                        <dt className="text-muted-foreground">Nama</dt>
                        <dd>{letterTemplate.name}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Klasifikasi</dt>
                        <dd>
                            {selectedVersion
                                ? `${selectedVersion.classification.code} — ${selectedVersion.classification.name}`
                                : (classifications.find((item) => item.id === letterTemplate.draft?.letter_number)
                                      ?.label ??
                                  (letterTemplate.classification
                                      ? `${letterTemplate.classification.code} — ${letterTemplate.classification.name}`
                                      : 'Belum tersedia'))}
                        </dd>
                    </div>
                </dl>
            </FormCard>
        );
    }

    return (
        <FormCard
            title={pageName}
            description={
                letterTemplate
                    ? 'Ubah identitas draft. Perubahan membatalkan bukti pemeriksaan sebelumnya.'
                    : 'Simpan nama dan klasifikasi untuk membuat draft awal.'
            }
            cancelUrl={letterTemplate ? undefined : lt.index().url}
            disabled={busy}
        >
            <form noValidate onSubmit={handleSubmit}>
                <FieldGroup>
                    <Field data-invalid={!!form.errors.name}>
                        <FieldLabel htmlFor="name">Nama Templat</FieldLabel>
                        <Input
                            type="text"
                            placeholder="Surat Tugas Perjalanan"
                            minLength={3}
                            maxLength={100}
                            autoFocus
                            disabled={busy}
                            {...bind('name')}
                        />
                        {form.errors.name && <FieldError>{form.errors.name}</FieldError>}
                    </Field>
                    <Field data-invalid={!!form.errors.letter_number} data-disabled={busy}>
                        <FieldLabel htmlFor="letter_number">Klasifikasi</FieldLabel>
                        <OptionSelect
                            id="letter_number"
                            name="letter_number"
                            items={classificationItems}
                            value={form.data.letter_number}
                            placeholder="Pilih klasifikasi"
                            resetLabel="Reset klasifikasi"
                            aria-invalid={!!form.errors.letter_number}
                            disabled={busy}
                            onValueChange={(value) => {
                                form.setData('letter_number', value);
                                form.clearErrors('letter_number');
                            }}
                        />
                        {form.errors.letter_number && <FieldError>{form.errors.letter_number}</FieldError>}
                    </Field>
                    <FormActions
                        cancelUrl={lt.index().url}
                        onCancel={letterTemplate ? onClose : undefined}
                        processing={form.processing}
                        disabled={disabled}
                    />
                </FieldGroup>
            </form>
        </FormCard>
    );
}
