import { useEffect } from 'react';
import type { SubmitEventHandler } from 'react';
import { object, pipe, string, nonEmpty, maxLength, check } from 'valibot';
import { FormCard } from '@/components/form-card';
import { FormActions } from '@/components/form-actions';
import { FieldGroup, FieldDescription } from '@/components/ui/field';
import { gooeyToast } from '@/components/ui/goey-toaster';
import { defineRequest } from '@/lib/request';
import { useValibotForm } from '@/hooks/use-valibot-form';
import { update } from '@/routes/lt/system-fields';
import { CopyPlaceholder } from './copy-placeholder';
import { EmployeeDateFormat } from './employee-attribute-fields';
import { TemplateEditAction } from './edit-action';

const schema = defineRequest(
    object({
        letter_date_format: pipe(
            string(),
            nonEmpty('Format tanggal wajib diisi.'),
            maxLength(100),
            check(
                (value) => value.split('').every((character) => character.charCodeAt(0) >= 32),
                'Format tanggal tidak valid.',
            ),
        ),
    }),
);

export function SystemFieldsForm({
    templateId,
    versionId,
    format,
    editable,
    editing,
    disabled,
    onEdit,
    onClose,
    onProcessingChange,
}: {
    templateId: string;
    versionId: string;
    format: string;
    editable: boolean;
    editing: boolean;
    disabled: boolean;
    onEdit: () => void;
    onClose: () => void;
    onProcessingChange: (value: boolean) => void;
}) {
    const { form, submit } = useValibotForm(schema, { letter_date_format: format });
    useEffect(() => {
        onProcessingChange(form.processing);
    }, [form.processing, onProcessingChange]);
    const handleSubmit: SubmitEventHandler<HTMLFormElement> = (event) => {
        event.preventDefault();
        if (disabled || form.processing) return;
        const action = update({ letterTemplate: templateId, version: versionId });
        submit(action.method, action.url, {
            preserveScroll: true,
            onSuccess: () => {
                onClose();
                gooeyToast.success('Berhasil', { description: 'Format tanggal surat disimpan.' });
            },
            onError: () => gooeyToast.error('Galat', { description: 'Periksa format tanggal surat.' }),
        });
    };
    return (
        <FormCard
            sectioned
            title="Variabel Sistem"
            description="Salin token ini ke DOCX tanpa membuat pertanyaan formulir. Generate uji memakai nomor contoh XX.XX dan tidak mengambil nomor surat."
            action={
                editable && !editing ? (
                    <TemplateEditAction label="Ubah format tanggal surat" disabled={disabled} onClick={onEdit} />
                ) : undefined
            }
        >
            <FieldGroup>
                <CopyPlaceholder name="nomor_surat" />
                <CopyPlaceholder name="tanggal_surat" />
                {editing ? (
                    <form noValidate onSubmit={handleSubmit}>
                        <FieldGroup>
                            <EmployeeDateFormat
                                id="letter-date-format"
                                value={form.data.letter_date_format}
                                disabled={disabled || form.processing}
                                onChange={(value) => form.setData('letter_date_format', value ?? '')}
                                error={form.errors.letter_date_format}
                            />
                            <FormActions
                                cancelUrl=""
                                processing={form.processing}
                                disabled={disabled}
                                onCancel={() => {
                                    form.reset();
                                    form.clearErrors();
                                    onClose();
                                }}
                            />
                        </FieldGroup>
                    </form>
                ) : (
                    <FieldDescription>
                        Format tanggal surat: <code>{format}</code>. Perubahan format memerlukan validasi dan
                        pemeriksaan ulang.
                    </FieldDescription>
                )}
            </FieldGroup>
        </FormCard>
    );
}
