import { useValibotForm } from '@/hooks/use-valibot-form';
import { FormActions } from '@/components/form-actions';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { gooeyToast } from '@/components/ui/goey-toaster';
import {
    letterTemplateAttributeItemSchema,
    type EmployeeAttributeOption,
    type EmployeeAttributeSetting,
} from '@/schemas/letter-template-configuration';
import variables from '@/routes/lt/configuration/variables';
import slots from '@/routes/lt/configuration/slots';
import { EmployeeDateFormat } from './employee-attribute-fields';
import { CopyPlaceholder } from './copy-placeholder';
import { EditConfigurationItem } from './configuration-item-actions';

const attributeLabel = (attribute: EmployeeAttributeOption) =>
    attribute.id === 'name' ? 'Nama beserta gelar' : attribute.id === 'raw_name' ? 'Nama tanpa gelar' : attribute.label;

type AttributeTableProps = {
    templateId: string;
    versionId: string;
    ownerId: string;
    kind: 'variable' | 'slot';
    prefix: string;
    attributes: EmployeeAttributeOption[];
    settings: EmployeeAttributeSetting[];
    editable: boolean;
    activeEditor: string | null;
    disabled: boolean;
    onEdit: (key: string) => void;
    onClose: () => void;
    onProcessingChange: (value: boolean) => void;
};

function AttributeRowEditor({
    attribute,
    setting,
    ...props
}: AttributeTableProps & { attribute: EmployeeAttributeOption; setting: EmployeeAttributeSetting }) {
    const { form, submit } = useValibotForm(letterTemplateAttributeItemSchema, {
        default_text: setting.default_text,
        date_format: attribute.type === 'date' ? setting.date_format : null,
    });
    const busy = props.disabled || form.processing;
    const errors: Record<string, string | undefined> = form.errors;
    const id = `attribute-form-${props.ownerId}-${attribute.id}`;
    const error = () => gooeyToast.error('Galat', { description: 'Periksa teks alternatif dan format atribut.' });
    return (
        <TableRow>
            <TableCell>{attributeLabel(attribute)}</TableCell>
            <TableCell>
                <CopyPlaceholder name={`${props.prefix}_${attribute.suffix}`} />
            </TableCell>
            <TableCell className="min-w-48">
                <Field data-invalid={!!errors.default_text}>
                    <FieldLabel className="sr-only" htmlFor={`${id}-fallback`}>
                        Teks Alternatif {attributeLabel(attribute)}
                    </FieldLabel>
                    <Input
                        id={`${id}-fallback`}
                        form={id}
                        autoFocus
                        disabled={busy}
                        maxLength={10000}
                        value={form.data.default_text ?? ''}
                        placeholder="Kosong"
                        aria-invalid={!!errors.default_text}
                        onChange={(event) => form.setData('default_text', event.target.value || null)}
                    />
                    <FieldError>{errors.default_text}</FieldError>
                </Field>
            </TableCell>
            <TableCell className="min-w-64 whitespace-normal">
                {attribute.type === 'date' ? (
                    <EmployeeDateFormat
                        id={`${id}-format`}
                        disabled={busy}
                        value={form.data.date_format}
                        onChange={(value) => form.setData('date_format', value)}
                        error={errors.date_format}
                    />
                ) : (
                    '—'
                )}
            </TableCell>
            <TableCell className="w-24">
                <form
                    id={id}
                    noValidate
                    onSubmit={(event) => {
                        event.preventDefault();
                        if (busy) return;
                        const action = (props.kind === 'variable' ? variables : slots).attribute({
                            letterTemplate: props.templateId,
                            version: props.versionId,
                            item: props.ownerId,
                            attribute: attribute.id,
                        });
                        if (
                            !submit(action.method, action.url, {
                                preserveScroll: true,
                                showProgress: false,
                                onStart: () => props.onProcessingChange(true),
                                onFinish: () => props.onProcessingChange(false),
                                onError: error,
                                onSuccess: () => {
                                    props.onClose();
                                    gooeyToast.success('Berhasil', {
                                        description: 'Pengaturan atribut disimpan. Validasi ulang DOCX.',
                                    });
                                },
                            })
                        )
                            error();
                    }}
                >
                    <FormActions
                        cancelUrl=""
                        compact
                        onCancel={props.onClose}
                        processing={form.processing}
                        disabled={props.disabled}
                        fullWidth={false}
                    />
                    <FieldError>{errors.configuration}</FieldError>
                </form>
            </TableCell>
        </TableRow>
    );
}

export function ConfigurationAttributeTable(props: AttributeTableProps) {
    const ownerKey = `attribute:${props.kind}:${props.ownerId}:`;
    return (
        <details
            className="text-sm"
            onClick={(event) => {
                const summary = event.currentTarget.querySelector('summary');
                if (
                    props.activeEditor?.startsWith(ownerKey) &&
                    summary?.contains(event.target as Node) &&
                    event.currentTarget.open
                )
                    event.preventDefault();
            }}
        >
            <summary className="cursor-pointer py-2 font-medium">Atribut Pegawai ({props.attributes.length})</summary>
            <p className="my-3 text-sm text-muted-foreground">
                Semua variabel mengambil data dari pegawai yang sama. Pilih nama beserta gelar atau nama tanpa gelar
                sesuai kebutuhan surat. Teks alternatif digunakan jika atribut kosong.
            </p>
            <Table>
                <TableHeader>
                    <TableRow>
                        {['Atribut', 'Nama Variabel', 'Teks Alternatif', 'Format tanggal', 'Aksi']
                            .filter((label) => props.editable || label !== 'Aksi')
                            .map((label) => (
                                <TableHead key={label}>{label}</TableHead>
                            ))}
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {props.attributes.map((attribute) => {
                        const setting = props.settings.find((item) => item.source_attribute === attribute.id) ?? {
                            source_attribute: attribute.id,
                            default_text: null,
                            date_format: attribute.type === 'date' ? 'd-m-Y' : null,
                        };
                        const editorId = `${ownerKey}${attribute.id}`;
                        if (props.activeEditor === editorId)
                            return (
                                <AttributeRowEditor
                                    key={`edit-${attribute.id}`}
                                    {...props}
                                    attribute={attribute}
                                    setting={setting}
                                />
                            );
                        return (
                            <TableRow key={attribute.id}>
                                <TableCell>{attributeLabel(attribute)}</TableCell>
                                <TableCell>
                                    <CopyPlaceholder name={`${props.prefix}_${attribute.suffix}`} />
                                </TableCell>
                                <TableCell className="max-w-80 whitespace-pre-wrap">
                                    {setting.default_text || 'Kosong'}
                                </TableCell>
                                <TableCell>
                                    {attribute.type === 'date' ? setting.date_format || 'd-m-Y' : '—'}
                                </TableCell>
                                {props.editable && (
                                    <TableCell data-focus={editorId}>
                                        <EditConfigurationItem
                                            label={`teks alternatif ${attributeLabel(attribute)}`}
                                            disabled={props.disabled || props.activeEditor !== null}
                                            onClick={() => props.onEdit(editorId)}
                                        />
                                    </TableCell>
                                )}
                            </TableRow>
                        );
                    })}
                </TableBody>
            </Table>
        </details>
    );
}
