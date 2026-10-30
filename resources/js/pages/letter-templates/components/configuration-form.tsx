import { Fragment, useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { ArrowUp01Icon, ArrowDown01Icon } from '@hugeicons/core-free-icons';
import { FormCard } from '@/components/form-card';
import { CardAction } from '@/components/ui/card';
import { FieldError } from '@/components/ui/field';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { TableActionButton } from '@/components/table-action-button';
import { gooeyToast } from '@/components/ui/goey-toaster';
import variables from '@/routes/lt/configuration/variables';
import slots from '@/routes/lt/configuration/slots';
import type {
    ConfigurationOptions,
    ConfigurationVariable,
    LetterTemplateConfiguration,
} from '@/schemas/letter-template-configuration';
import { CopyPlaceholder } from './copy-placeholder';
import { AddConfigurationItem, DeleteConfigurationItem, EditConfigurationItem } from './configuration-item-actions';
import { ConfigurationVariableRow } from './configuration-variable-row';
import { ConfigurationSlotRow } from './configuration-slot-row';
import { ConfigurationAttributeTable } from './configuration-attribute-table';
import { RepeatedTableGuide } from './repeated-table-guide';

const newVariable = (): ConfigurationVariable => ({
    id: null,
    name: '',
    label: '',
    type: 1,
    is_required: false,
    option_source: null,
    reference_group: null,
    is_multiple: false,
    default_text: null,
    employee_attributes: [],
    columns: [],
    radio: null,
    numeric: null,
    date_range: null,
});

export function LetterTemplateConfigurationForm({
    templateId,
    configuration,
    options,
    disabled = false,
    onProcessingChange,
    activeEditor,
    onEdit,
    onClose,
}: {
    templateId: string;
    configuration: LetterTemplateConfiguration;
    options: ConfigurationOptions;
    disabled?: boolean;
    onProcessingChange: (processing: boolean) => void;
    activeEditor: string | null;
    onEdit: (key: string) => void;
    onClose: () => void;
}) {
    const [errors, setErrors] = useState<string[]>([]);
    const [focusRevision, setFocusRevision] = useState(0);
    const root = useRef<HTMLDivElement>(null);
    const focusKey = useRef<string | null>(null);
    const running = useRef(false);
    const blocked = disabled || activeEditor !== null || !configuration.editable;
    const args = { letterTemplate: templateId, version: configuration.id };
    useEffect(() => {
        if (focusRevision > 0 && !disabled && activeEditor === null && focusKey.current) {
            const group = root.current?.querySelector<HTMLElement>(`[data-focus="${focusKey.current}"]`);
            const target =
                group?.querySelector<HTMLButtonElement>('button[aria-label^="Edit"]:not(:disabled)') ??
                group?.querySelector<HTMLButtonElement>('button:not(:disabled)');
            target?.focus({ preventScroll: true });
        }
    }, [focusRevision, disabled, activeEditor]);
    function edit(key: string) {
        focusKey.current = key;
        setErrors([]);
        onEdit(key);
    }
    function close() {
        onClose();
        setFocusRevision((value) => value + 1);
    }
    function mutate(
        action: { url: string; method: 'delete' | 'patch' },
        data: { direction?: string },
        after?: () => void,
        focus?: string,
    ) {
        if (blocked || running.current) return;
        running.current = true;
        setErrors([]);
        router.visit(action.url, {
            method: action.method,
            data,
            preserveScroll: true,
            preserveState: true,
            showProgress: false,
            onStart: () => onProcessingChange(true),
            onFinish: () => {
                running.current = false;
                onProcessingChange(false);
            },
            onError: (errors) => {
                setErrors(Object.values(errors).flat());
                gooeyToast.error('Galat', {
                    description: Object.values(errors).flat().join(' ') || 'Konfigurasi belum berhasil diperbarui.',
                });
            },
            onSuccess: () => {
                after?.();
                focusKey.current = focus ?? focusKey.current;
                setFocusRevision((value) => value + 1);
                gooeyToast.success('Berhasil', { description: 'Konfigurasi diperbarui. Validasi ulang DOCX.' });
            },
        });
    }
    const attributes = (
        kind: 'variable' | 'slot',
        id: string,
        prefix: string,
        settings: ConfigurationVariable['employee_attributes'],
    ) => (
        <ConfigurationAttributeTable
            templateId={templateId}
            versionId={configuration.id}
            ownerId={id}
            kind={kind}
            prefix={prefix}
            attributes={options.employeeAttributes}
            settings={settings}
            editable={configuration.editable}
            activeEditor={activeEditor}
            disabled={disabled}
            onEdit={edit}
            onClose={close}
            onProcessingChange={onProcessingChange}
        />
    );
    return (
        <div
            ref={root}
            className="flex min-w-0 flex-col gap-6"
            onKeyDown={(event) => {
                if (
                    event.key === 'Escape' &&
                    !disabled &&
                    activeEditor &&
                    /^(variable|slot|attribute):/.test(activeEditor)
                ) {
                    event.preventDefault();
                    close();
                }
            }}
        >
            <FormCard
                title="Konfigurasi Formulir Surat"
                step={2}
                description="Atur pertanyaan yang akan diisi pada formulir surat. Urutan baris menjadi urutan isian. Simpan setiap item setelah mengedit."
                action={
                    configuration.editable ? (
                        <CardAction
                            className="col-span-full col-start-1 row-span-1 row-start-3 justify-self-start sm:col-span-1 sm:col-start-2 sm:row-span-2 sm:row-start-1 sm:justify-self-end"
                            data-focus="variable:new"
                        >
                            <AddConfigurationItem
                                label="Pertanyaan"
                                disabled={blocked}
                                onClick={() => edit('variable:new')}
                            />
                        </CardAction>
                    ) : undefined
                }
            >
                {errors.length > 0 && <FieldError>{errors.join(' ')}</FieldError>}
                <Table>
                    <TableHeader>
                        <TableRow>
                            {[
                                'Nomor urut',
                                'Nama Variabel',
                                'Pertanyaan',
                                'Jenis Pertanyaan',
                                'Teks Alternatif',
                                'Aksi',
                            ]
                                .filter((label) => configuration.editable || label !== 'Aksi')
                                .map((label) => (
                                    <TableHead key={label}>{label}</TableHead>
                                ))}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {configuration.variables.length === 0 && activeEditor !== 'variable:new' && (
                            <TableRow>
                                <TableCell colSpan={configuration.editable ? 6 : 5} className="text-muted-foreground">
                                    Belum ada pertanyaan.
                                </TableCell>
                            </TableRow>
                        )}
                        {configuration.variables.map((variable, index) => {
                            const key = `variable:${variable.id}`;
                            if (activeEditor === key)
                                return (
                                    <ConfigurationVariableRow
                                        key={`editor-${variable.id}`}
                                        variable={variable}
                                        index={index}
                                        options={options}
                                        templateId={templateId}
                                        versionId={configuration.id}
                                        disabled={disabled}
                                        onClose={close}
                                        onProcessingChange={onProcessingChange}
                                    />
                                );
                            return (
                                <Fragment key={`variable-${variable.id}`}>
                                    <TableRow>
                                        <TableCell>{index + 1}</TableCell>
                                        <TableCell>
                                            {variable.is_multiple ? (
                                                <code>{variable.name}</code>
                                            ) : (
                                                <CopyPlaceholder name={variable.name} />
                                            )}
                                            {variable.is_multiple && (
                                                <p className="mt-2 text-xs text-muted-foreground">
                                                    Gunakan variabel kolom di bawah untuk tabel berulang.
                                                </p>
                                            )}
                                        </TableCell>
                                        <TableCell className="whitespace-normal">{variable.label}</TableCell>
                                        <TableCell>
                                            {options.types.find((type) => type.id === variable.type)?.label}
                                            <span className="ml-2 text-muted-foreground">
                                                {variable.is_required ? 'Wajib' : 'Opsional'}
                                            </span>
                                        </TableCell>
                                        <TableCell className="max-w-80 whitespace-pre-wrap">
                                            {variable.is_multiple || [5, 6, 7, 8].includes(variable.type)
                                                ? 'Tidak berlaku'
                                                : variable.option_source === 2
                                                  ? 'Per atribut'
                                                  : variable.default_text || '—'}
                                        </TableCell>
                                        {configuration.editable && (
                                            <TableCell>
                                                <div className="flex items-center gap-2" data-focus={key}>
                                                    <TableActionButton
                                                        type="button"
                                                        variant="outline"
                                                        icon={ArrowUp01Icon}
                                                        tooltip="Naikkan"
                                                        label={`Naikkan ${variable.label}`}
                                                        disabled={blocked || index === 0}
                                                        onClick={() =>
                                                            mutate(
                                                                variables.move({ ...args, item: variable.id! }),
                                                                { direction: 'up' },
                                                                undefined,
                                                                key,
                                                            )
                                                        }
                                                    />
                                                    <TableActionButton
                                                        type="button"
                                                        variant="outline"
                                                        icon={ArrowDown01Icon}
                                                        tooltip="Turunkan"
                                                        label={`Turunkan ${variable.label}`}
                                                        disabled={
                                                            blocked || index === configuration.variables.length - 1
                                                        }
                                                        onClick={() =>
                                                            mutate(
                                                                variables.move({ ...args, item: variable.id! }),
                                                                { direction: 'down' },
                                                                undefined,
                                                                key,
                                                            )
                                                        }
                                                    />
                                                    <EditConfigurationItem
                                                        label={`variabel ${variable.label}`}
                                                        disabled={blocked}
                                                        onClick={() => edit(key)}
                                                    />
                                                    <DeleteConfigurationItem
                                                        label={`variabel ${variable.label}`}
                                                        description="Variabel dan pengaturan atributnya akan dihapus. Sesuaikan variabel DOCX dan validasi ulang dokumen setelah menghapus."
                                                        disabled={blocked}
                                                        onDelete={(done) =>
                                                            mutate(
                                                                variables.destroy({ ...args, item: variable.id! }),
                                                                {},
                                                                done,
                                                                'variable:new',
                                                            )
                                                        }
                                                    />
                                                </div>
                                            </TableCell>
                                        )}
                                    </TableRow>
                                    {variable.type === 4 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={configuration.editable ? 6 : 5}
                                                className="whitespace-normal"
                                            >
                                                <p className="mb-2 text-sm text-muted-foreground">
                                                    Sumber:{' '}
                                                    {
                                                        options.sources.find(
                                                            (source) => source.id === variable.option_source,
                                                        )?.label
                                                    }
                                                    {variable.reference_group
                                                        ? ` · ${options.referenceGroups.find((group) => group.id === variable.reference_group)?.label ?? 'Grup tidak tersedia'}`
                                                        : ''}{' '}
                                                    · {variable.is_multiple ? 'Pilihan multiple' : 'Pilihan tunggal'}
                                                </p>
                                                {variable.option_source === 2 &&
                                                    !variable.is_multiple &&
                                                    attributes(
                                                        'variable',
                                                        variable.id!,
                                                        variable.name,
                                                        variable.employee_attributes,
                                                    )}
                                                {variable.is_multiple && (
                                                    <div className="flex flex-col gap-2">
                                                        <RepeatedTableGuide variable={variable} options={options} />
                                                        {variable.columns.map((column) => (
                                                            <div
                                                                key={column.id}
                                                                className="flex flex-wrap items-center gap-3 text-sm"
                                                            >
                                                                <CopyPlaceholder name={column.placeholder} />
                                                                <span className="text-muted-foreground">
                                                                    {
                                                                        options.columns[
                                                                            variable.option_source ?? 0
                                                                        ]?.find(
                                                                            (item) =>
                                                                                item.id === column.source_attribute,
                                                                        )?.label
                                                                    }
                                                                    {column.date_format
                                                                        ? ` · ${column.date_format}`
                                                                        : ''}
                                                                </span>
                                                            </div>
                                                        ))}
                                                    </div>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    )}
                                    {variable.type === 5 && variable.radio && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={configuration.editable ? 6 : 5}
                                                className="whitespace-normal"
                                            >
                                                <p className="mb-2 text-sm text-muted-foreground">
                                                    Tanda terpilih:{' '}
                                                    <span className="font-semibold text-foreground">
                                                        {variable.radio.selected_text}
                                                    </span>
                                                </p>
                                                <div className="flex flex-col gap-2">
                                                    {variable.radio.options.map((opt) => (
                                                        <div
                                                            key={opt.id ?? opt.code}
                                                            className="flex flex-wrap items-center gap-3 text-sm"
                                                        >
                                                            <CopyPlaceholder name={`${variable.name}:${opt.code}`} />
                                                            <span>{opt.label}</span>
                                                        </div>
                                                    ))}
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    )}
                                    {variable.type === 6 && variable.numeric && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={configuration.editable ? 6 : 5}
                                                className="whitespace-normal"
                                            >
                                                <p className="mb-2 text-sm text-muted-foreground">
                                                    Mode:{' '}
                                                    {variable.numeric.mode === 'decimal'
                                                        ? `Desimal (${variable.numeric.scale} digit)`
                                                        : 'Bulat (Integer)'}{' '}
                                                    · Negatif:{' '}
                                                    {variable.numeric.allow_negative ? 'Diizinkan' : 'Ditolak'}
                                                </p>
                                                <div className="flex flex-wrap items-center gap-4 text-sm">
                                                    <CopyPlaceholder
                                                        name={variable.name}
                                                        label="Salin variabel nilai"
                                                    />
                                                    <CopyPlaceholder
                                                        name={`${variable.name}:terbilang`}
                                                        label="Salin variabel terbilang"
                                                    />
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    )}
                                    {variable.type === 7 && variable.numeric && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={configuration.editable ? 6 : 5}
                                                className="whitespace-normal"
                                            >
                                                <p className="mb-2 text-sm text-muted-foreground">
                                                    Mode:{' '}
                                                    {variable.numeric.mode === 'decimal'
                                                        ? 'Desimal (Rupiah dan Sen)'
                                                        : 'Bulat (Rupiah penuh)'}{' '}
                                                    · Negatif:{' '}
                                                    {variable.numeric.allow_negative ? 'Diizinkan' : 'Ditolak'}
                                                </p>
                                                <div className="flex flex-wrap items-center gap-4 text-sm">
                                                    <CopyPlaceholder
                                                        name={variable.name}
                                                        label="Salin variabel nominal"
                                                    />
                                                    <CopyPlaceholder
                                                        name={`${variable.name}:terbilang`}
                                                        label="Salin variabel terbilang"
                                                    />
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    )}
                                    {variable.type === 8 && variable.date_range && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={configuration.editable ? 6 : 5}
                                                className="whitespace-normal"
                                            >
                                                <p className="mb-2 text-sm text-muted-foreground">
                                                    Basis:{' '}
                                                    {variable.date_range.basis === 'working_days'
                                                        ? 'Hari Kerja (Kalender Kerja)'
                                                        : 'Hari Kalender'}{' '}
                                                    · Format: {variable.date_range.date_format}
                                                </p>
                                                <div className="flex flex-wrap items-center gap-3 text-sm">
                                                    <CopyPlaceholder name={`${variable.name}:mulai`} label="Mulai" />
                                                    <CopyPlaceholder name={`${variable.name}:akhir`} label="Akhir" />
                                                    <CopyPlaceholder
                                                        name={`${variable.name}:jumlah_hari`}
                                                        label="Jumlah hari"
                                                    />
                                                    <CopyPlaceholder
                                                        name={`${variable.name}:terbilang`}
                                                        label="Terbilang hari"
                                                    />
                                                    <CopyPlaceholder
                                                        name={`${variable.name}:durasi`}
                                                        label="Durasi lengkap"
                                                    />
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </Fragment>
                            );
                        })}
                        {activeEditor === 'variable:new' && (
                            <ConfigurationVariableRow
                                key="new-variable"
                                variable={newVariable()}
                                index={configuration.variables.length}
                                options={options}
                                templateId={templateId}
                                versionId={configuration.id}
                                disabled={disabled}
                                onClose={close}
                                onProcessingChange={onProcessingChange}
                            />
                        )}
                    </TableBody>
                </Table>
            </FormCard>
            <FormCard
                title="Penandatangan"
                step={3}
                description="Tentukan kandidat pegawai aktif pada setiap slot. Token penandatangan opsional; perubahan jumlah slot dapat mengubah nama variabel pada DOCX."
                action={
                    configuration.editable ? (
                        <CardAction
                            className="col-span-full col-start-1 row-span-1 row-start-3 justify-self-start sm:col-span-1 sm:col-start-2 sm:row-span-2 sm:row-start-1 sm:justify-self-end"
                            data-focus="slot:new"
                        >
                            <AddConfigurationItem
                                label="Penandatangan"
                                disabled={blocked}
                                onClick={() => edit('slot:new')}
                            />
                        </CardAction>
                    ) : undefined
                }
            >
                {configuration.warnings.map((warning) => (
                    <FieldError key={`${warning.slot}-${warning.employee}`}>
                        {warning.name} pada slot {warning.slot} sudah tidak aktif. Ganti atau hapus kandidat sebelum
                        publikasi.
                    </FieldError>
                ))}
                <Table>
                    <TableHeader>
                        <TableRow>
                            {['Slot', 'Nama Variabel', 'Kandidat Penandatangan', 'Aksi']
                                .filter((label) => configuration.editable || label !== 'Aksi')
                                .map((label) => (
                                    <TableHead key={label}>{label}</TableHead>
                                ))}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {configuration.signer_slots.length === 0 && activeEditor !== 'slot:new' && (
                            <TableRow>
                                <TableCell colSpan={configuration.editable ? 4 : 3} className="text-muted-foreground">
                                    Belum ada slot penandatangan.
                                </TableCell>
                            </TableRow>
                        )}
                        {configuration.signer_slots.map((slot, index) => {
                            const key = `slot:${slot.id}`;
                            if (activeEditor === key)
                                return (
                                    <ConfigurationSlotRow
                                        key={`editor-${slot.id}`}
                                        slot={{
                                            id: slot.id,
                                            candidates: slot.candidates,
                                            employee_attributes: slot.employee_attributes,
                                        }}
                                        index={index}
                                        templateId={templateId}
                                        configuration={configuration}
                                        options={options}
                                        disabled={disabled}
                                        onClose={close}
                                        onProcessingChange={onProcessingChange}
                                    />
                                );
                            return (
                                <Fragment key={`slot-${slot.id}`}>
                                    <TableRow>
                                        <TableCell>Slot {index + 1}</TableCell>
                                        <TableCell>
                                            <CopyPlaceholder name={slot.placeholder} />
                                        </TableCell>
                                        <TableCell className="whitespace-normal">
                                            {slot.candidates
                                                .map(
                                                    (id) =>
                                                        options.employees.find((employee) => employee.id === id)
                                                            ?.label ??
                                                        configuration.warnings.find(
                                                            (warning) => warning.employee === id,
                                                        )?.name ??
                                                        'Tidak tersedia',
                                                )
                                                .join(', ')}
                                        </TableCell>
                                        {configuration.editable && (
                                            <TableCell>
                                                <div className="flex items-center gap-2" data-focus={key}>
                                                    <EditConfigurationItem
                                                        label={`penandatangan slot ${index + 1}`}
                                                        disabled={blocked}
                                                        onClick={() => edit(key)}
                                                    />
                                                    <DeleteConfigurationItem
                                                        label={`penandatangan slot ${index + 1}`}
                                                        description="Slot dan pengaturan atributnya akan dihapus. Nomor slot dan variabel penandatangan dapat berubah. Sesuaikan DOCX dan lakukan validasi ulang."
                                                        disabled={blocked}
                                                        onDelete={(done) =>
                                                            mutate(
                                                                slots.destroy({ ...args, item: slot.id! }),
                                                                {},
                                                                done,
                                                                'slot:new',
                                                            )
                                                        }
                                                    />
                                                </div>
                                            </TableCell>
                                        )}
                                    </TableRow>
                                    <TableRow>
                                        <TableCell
                                            colSpan={configuration.editable ? 4 : 3}
                                            className="whitespace-normal"
                                        >
                                            {attributes('slot', slot.id!, slot.placeholder, slot.employee_attributes)}
                                        </TableCell>
                                    </TableRow>
                                </Fragment>
                            );
                        })}
                        {activeEditor === 'slot:new' && (
                            <ConfigurationSlotRow
                                key="new-slot"
                                slot={{ id: null, candidates: [], employee_attributes: [] }}
                                index={configuration.signer_slots.length}
                                templateId={templateId}
                                configuration={configuration}
                                options={options}
                                disabled={disabled}
                                onClose={close}
                                onProcessingChange={onProcessingChange}
                            />
                        )}
                    </TableBody>
                </Table>
            </FormCard>
        </div>
    );
}
