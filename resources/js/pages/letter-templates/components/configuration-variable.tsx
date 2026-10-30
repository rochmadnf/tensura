import { Add01Icon, ArrowDown01Icon, ArrowUp01Icon, Delete02Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { EmployeeDateFormat } from './employee-attribute-fields';
import { OptionSelect } from '@/components/option-select';
import { TableActionButton } from '@/components/table-action-button';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
    FieldLegend,
    FieldSet,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import type { ConfigurationOptions, ConfigurationVariable } from '@/schemas/letter-template-configuration';
import { CopyPlaceholder } from './copy-placeholder';
import { RepeatedTableGuide } from './repeated-table-guide';
import { slugifyVariable } from '@/lib/variable-slug';

type VariableFieldsProps = {
    variable: ConfigurationVariable;
    options: ConfigurationOptions;
    disabled: boolean;
    errors: Record<string, string | undefined>;
    onChange: (value: ConfigurationVariable) => void;
};

export function ConfigurationVariableDetails({ variable, options, disabled, errors, onChange }: VariableFieldsProps) {
    const prefix = 'item';
    const combo = variable.type === 4;
    const isRadio = variable.type === 5;
    const isNumber = variable.type === 6;
    const isMoney = variable.type === 7;
    const isDateRange = variable.type === 8;

    function patch(changes: Partial<ConfigurationVariable>) {
        onChange({ ...variable, ...changes });
    }

    const choices = (items: { id: string | number; label: string }[]) =>
        items.map((item) => ({ value: String(item.id), label: item.label }));

    return (
        <FieldGroup>
            {combo && (
                <>
                    <Field data-invalid={!!errors[`${prefix}.option_source`]}>
                        <FieldLabel htmlFor={`${prefix}.option_source`}>Sumber Pilihan</FieldLabel>
                        <OptionSelect
                            id={`${prefix}.option_source`}
                            name={`${prefix}.option_source`}
                            items={choices(options.sources)}
                            value={variable.option_source === null ? '' : String(variable.option_source)}
                            onValueChange={(value) =>
                                patch({
                                    option_source: value ? Number(value) : null,
                                    reference_group: null,
                                    columns: [],
                                    default_text: null,
                                    employee_attributes: [],
                                })
                            }
                            disabled={disabled}
                            aria-invalid={!!errors[`${prefix}.option_source`]}
                            placeholder="Pilih sumber"
                        />
                        <FieldError>{errors[`${prefix}.option_source`]}</FieldError>
                    </Field>
                    {variable.option_source === 1 && (
                        <Field data-invalid={!!errors[`${prefix}.reference_group`]}>
                            <FieldLabel htmlFor={`${prefix}.reference_group`}>Grup Referensi</FieldLabel>
                            <OptionSelect
                                forceCombobox
                                id={`${prefix}.reference_group`}
                                name={`${prefix}.reference_group`}
                                remote={{ source: 'reference-groups' }}
                                items={choices(options.referenceGroups)}
                                value={variable.reference_group ?? ''}
                                onValueChange={(value) => patch({ reference_group: value || null })}
                                disabled={disabled}
                                aria-invalid={!!errors[`${prefix}.reference_group`]}
                                placeholder="Pilih grup referensi"
                            />
                            <FieldError>{errors[`${prefix}.reference_group`]}</FieldError>
                        </Field>
                    )}
                    <Field orientation="horizontal">
                        <Checkbox
                            id={`${prefix}.is_multiple`}
                            checked={variable.is_multiple}
                            onCheckedChange={(checked) =>
                                patch({
                                    is_multiple: checked,
                                    columns: checked ? variable.columns : [],
                                    default_text: null,
                                    employee_attributes: [],
                                })
                            }
                            disabled={disabled}
                        />
                        <FieldLabel htmlFor={`${prefix}.is_multiple`}>Pilihan multiple untuk tabel berulang</FieldLabel>
                    </Field>
                    {variable.is_multiple && (
                        <FieldSet>
                            <FieldLegend>Kolom Tabel Berulang</FieldLegend>
                            <FieldDescription>
                                Susun satu baris horizontal atau beberapa baris vertikal dengan penanda blok di DOCX.
                            </FieldDescription>
                            <FieldError>{errors[`${prefix}.columns`]}</FieldError>
                            <FieldGroup>
                                <RepeatedTableGuide variable={variable} options={options} />
                                {variable.columns.map((column, columnIndex) => {
                                    const path = `${prefix}.columns.${columnIndex}`;
                                    const patchColumn = (changes: Partial<typeof column>) =>
                                        patch({
                                            columns: variable.columns.map((value, position) =>
                                                position === columnIndex ? { ...value, ...changes } : value,
                                            ),
                                        });
                                    return (
                                        <FieldGroup key={column.id ?? `column-${columnIndex}`}>
                                            <FieldError>{errors[`${path}.id`]}</FieldError>
                                            <Field data-invalid={!!errors[`${path}.source_attribute`]}>
                                                <FieldLabel htmlFor={`${path}.source_attribute`}>
                                                    Atribut Kolom {columnIndex + 1}
                                                </FieldLabel>
                                                <OptionSelect
                                                    id={`${path}.source_attribute`}
                                                    name={`${path}.source_attribute`}
                                                    items={choices(options.columns[variable.option_source ?? 0] ?? [])}
                                                    value={column.source_attribute}
                                                    onValueChange={(value) =>
                                                        patchColumn({ source_attribute: value, date_format: null })
                                                    }
                                                    disabled={disabled}
                                                    aria-invalid={!!errors[`${path}.source_attribute`]}
                                                    placeholder="Pilih atribut"
                                                />
                                                <FieldError>{errors[`${path}.source_attribute`]}</FieldError>
                                            </Field>
                                            <Field data-invalid={!!errors[`${path}.placeholder`]}>
                                                <FieldLabel htmlFor={`${path}.placeholder`}>
                                                    Nama Variabel Kolom {columnIndex + 1}
                                                </FieldLabel>
                                                <Input
                                                    id={`${path}.placeholder`}
                                                    value={column.placeholder}
                                                    onChange={(e) => patchColumn({ placeholder: e.target.value })}
                                                    maxLength={100}
                                                    disabled={disabled}
                                                    aria-invalid={!!errors[`${path}.placeholder`]}
                                                />
                                                <FieldError>{errors[`${path}.placeholder`]}</FieldError>
                                                <CopyPlaceholder name={column.placeholder} />
                                            </Field>
                                            {variable.option_source === 2 &&
                                                options.employeeAttributes.find(
                                                    (attribute) => attribute.id === column.source_attribute,
                                                )?.type === 'date' && (
                                                    <EmployeeDateFormat
                                                        id={`${path}.date_format`}
                                                        value={column.date_format}
                                                        disabled={disabled}
                                                        onChange={(date_format) => patchColumn({ date_format })}
                                                        error={errors[`${path}.date_format`]}
                                                    />
                                                )}
                                            <div className="flex flex-wrap gap-2">
                                                <TableActionButton
                                                    type="button"
                                                    variant="outline"
                                                    disabled={disabled || columnIndex === 0}
                                                    onClick={() => {
                                                        const columns = [...variable.columns];
                                                        [columns[columnIndex - 1], columns[columnIndex]] = [
                                                            columns[columnIndex],
                                                            columns[columnIndex - 1],
                                                        ];
                                                        patch({ columns });
                                                    }}
                                                    label={`Naikkan kolom ${columnIndex + 1}`}
                                                    tooltip="Naikkan"
                                                    icon={ArrowUp01Icon}
                                                />
                                                <TableActionButton
                                                    type="button"
                                                    variant="outline"
                                                    disabled={disabled || columnIndex === variable.columns.length - 1}
                                                    onClick={() => {
                                                        const columns = [...variable.columns];
                                                        [columns[columnIndex + 1], columns[columnIndex]] = [
                                                            columns[columnIndex],
                                                            columns[columnIndex + 1],
                                                        ];
                                                        patch({ columns });
                                                    }}
                                                    label={`Turunkan kolom ${columnIndex + 1}`}
                                                    tooltip="Turunkan"
                                                    icon={ArrowDown01Icon}
                                                />
                                                <Button
                                                    type="button"
                                                    variant="destructive"
                                                    size="sm"
                                                    disabled={disabled}
                                                    onClick={() =>
                                                        patch({
                                                            columns: variable.columns.filter(
                                                                (_, position) => position !== columnIndex,
                                                            ),
                                                        })
                                                    }
                                                >
                                                    <HugeiconsIcon
                                                        icon={Delete02Icon}
                                                        strokeWidth={2}
                                                        data-icon="inline-start"
                                                        aria-hidden="true"
                                                    />
                                                    Hapus Kolom {columnIndex + 1}
                                                </Button>
                                            </div>
                                        </FieldGroup>
                                    );
                                })}
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={disabled || variable.option_source === null}
                                    onClick={() =>
                                        patch({
                                            columns: [
                                                ...variable.columns,
                                                {
                                                    id: null,
                                                    source_attribute: '',
                                                    placeholder: '',
                                                    date_format: null,
                                                },
                                            ],
                                        })
                                    }
                                >
                                    <HugeiconsIcon
                                        icon={Add01Icon}
                                        strokeWidth={2}
                                        data-icon="inline-start"
                                        aria-hidden="true"
                                    />
                                    Tambah Kolom
                                </Button>
                            </FieldGroup>
                        </FieldSet>
                    )}
                </>
            )}

            {isRadio && (
                <FieldSet>
                    <FieldLegend>Pengaturan Pilihan Tunggal (Radio)</FieldLegend>
                    <FieldDescription>
                        Setiap opsi memiliki kode unik. Di dokumen DOCX, gunakan token per opsi{' '}
                        <code>{'${' + (variable.name || 'nama_variabel') + ':kode_opsi}'}</code>.
                    </FieldDescription>

                    <Field data-invalid={!!errors[`${prefix}.radio.selected_text`]}>
                        <FieldLabel htmlFor={`${prefix}.radio.selected_text`}>Tanda Jika Terpilih *</FieldLabel>
                        <Input
                            id={`${prefix}.radio.selected_text`}
                            value={variable.radio?.selected_text ?? '✔'}
                            maxLength={100}
                            disabled={disabled}
                            onChange={(e) =>
                                patch({
                                    radio: {
                                        selected_text: e.target.value,
                                        options: variable.radio?.options ?? [],
                                    },
                                })
                            }
                            placeholder="Cth: ✔"
                        />
                        <FieldDescription>
                            Teks atau simbol yang menggantikan placeholder ketika opsi ini dipilih (default: ✔). Opsi
                            lain akan diganti string kosong.
                        </FieldDescription>
                        <FieldError>{errors[`${prefix}.radio.selected_text`]}</FieldError>
                    </Field>

                    <div className="flex flex-col gap-4">
                        <FieldError>{errors[`${prefix}.radio.options`]}</FieldError>
                        {(variable.radio?.options ?? []).map((option, optIdx) => {
                            const optPath = `${prefix}.radio.options.${optIdx}`;
                            const patchOption = (changes: Partial<typeof option>) => {
                                const currentOpts = variable.radio?.options ?? [];
                                patch({
                                    radio: {
                                        selected_text: variable.radio?.selected_text ?? '✔',
                                        options: currentOpts.map((o, idx) =>
                                            idx === optIdx ? { ...o, ...changes } : o,
                                        ),
                                    },
                                });
                            };

                            const handleLabelChange = (newLabel: string) => {
                                const currentSlug = slugifyVariable(option.label);
                                const isCodeAuto = !option.code || option.code === currentSlug;
                                patchOption({
                                    label: newLabel,
                                    code: isCodeAuto ? slugifyVariable(newLabel) : option.code,
                                });
                            };

                            return (
                                <div
                                    key={option.id ?? `opt-${optIdx}`}
                                    className="flex flex-col gap-3 rounded-lg border bg-card p-3.5"
                                >
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <Field data-invalid={!!errors[`${optPath}.label`]}>
                                            <FieldLabel htmlFor={`${optPath}.label`}>
                                                Label Opsi {optIdx + 1} *
                                            </FieldLabel>
                                            <Input
                                                id={`${optPath}.label`}
                                                value={option.label}
                                                maxLength={100}
                                                disabled={disabled}
                                                placeholder="Cth: Rawat Jalan"
                                                onChange={(e) => handleLabelChange(e.target.value)}
                                            />
                                            <FieldError>{errors[`${optPath}.label`]}</FieldError>
                                        </Field>

                                        <Field data-invalid={!!errors[`${optPath}.code`]}>
                                            <FieldLabel htmlFor={`${optPath}.code`}>
                                                Kode Opsi {optIdx + 1} *
                                            </FieldLabel>
                                            <Input
                                                id={`${optPath}.code`}
                                                value={option.code}
                                                maxLength={100}
                                                disabled={disabled}
                                                placeholder="Cth: rawat_jalan"
                                                onChange={(e) => patchOption({ code: e.target.value })}
                                            />
                                            <FieldError>{errors[`${optPath}.code`]}</FieldError>
                                        </Field>
                                    </div>

                                    {variable.name && option.code && (
                                        <div className="flex items-center gap-2">
                                            <span className="text-xs text-muted-foreground">Token:</span>
                                            <CopyPlaceholder
                                                name={`${variable.name}:${option.code}`}
                                                label={`Salin token ${option.code}`}
                                            />
                                        </div>
                                    )}

                                    <div className="flex flex-wrap items-center gap-2">
                                        <TableActionButton
                                            type="button"
                                            variant="outline"
                                            disabled={disabled || optIdx === 0}
                                            onClick={() => {
                                                const opts = [...(variable.radio?.options ?? [])];
                                                [opts[optIdx - 1], opts[optIdx]] = [opts[optIdx], opts[optIdx - 1]];
                                                patch({
                                                    radio: {
                                                        selected_text: variable.radio?.selected_text ?? '✔',
                                                        options: opts,
                                                    },
                                                });
                                            }}
                                            label={`Naikkan opsi ${optIdx + 1}`}
                                            tooltip="Naikkan"
                                            icon={ArrowUp01Icon}
                                        />
                                        <TableActionButton
                                            type="button"
                                            variant="outline"
                                            disabled={disabled || optIdx === (variable.radio?.options?.length ?? 0) - 1}
                                            onClick={() => {
                                                const opts = [...(variable.radio?.options ?? [])];
                                                [opts[optIdx + 1], opts[optIdx]] = [opts[optIdx], opts[optIdx + 1]];
                                                patch({
                                                    radio: {
                                                        selected_text: variable.radio?.selected_text ?? '✔',
                                                        options: opts,
                                                    },
                                                });
                                            }}
                                            label={`Turunkan opsi ${optIdx + 1}`}
                                            tooltip="Turunkan"
                                            icon={ArrowDown01Icon}
                                        />
                                        <Button
                                            type="button"
                                            variant="destructive"
                                            size="sm"
                                            disabled={disabled || (variable.radio?.options?.length ?? 0) <= 2}
                                            onClick={() => {
                                                const opts = (variable.radio?.options ?? []).filter(
                                                    (_, idx) => idx !== optIdx,
                                                );
                                                patch({
                                                    radio: {
                                                        selected_text: variable.radio?.selected_text ?? '✔',
                                                        options: opts,
                                                    },
                                                });
                                            }}
                                        >
                                            <HugeiconsIcon
                                                icon={Delete02Icon}
                                                strokeWidth={2}
                                                data-icon="inline-start"
                                                aria-hidden="true"
                                            />
                                            Hapus Opsi
                                        </Button>
                                    </div>
                                </div>
                            );
                        })}

                        <Button
                            type="button"
                            variant="outline"
                            disabled={disabled || (variable.radio?.options?.length ?? 0) >= 100}
                            onClick={() => {
                                const currentOpts = variable.radio?.options ?? [];
                                patch({
                                    radio: {
                                        selected_text: variable.radio?.selected_text ?? '✔',
                                        options: [...currentOpts, { id: null, code: '', label: '' }],
                                    },
                                });
                            }}
                        >
                            <HugeiconsIcon
                                icon={Add01Icon}
                                strokeWidth={2}
                                data-icon="inline-start"
                                aria-hidden="true"
                            />
                            Tambah Opsi
                        </Button>
                    </div>
                </FieldSet>
            )}

            {isNumber && (
                <FieldSet>
                    <FieldLegend>Pengaturan Angka</FieldLegend>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field data-invalid={!!errors[`${prefix}.numeric.mode`]}>
                            <FieldLabel htmlFor={`${prefix}.numeric.mode`}>Mode Nilai *</FieldLabel>
                            <OptionSelect
                                id={`${prefix}.numeric.mode`}
                                name={`${prefix}.numeric.mode`}
                                placeholder="Pilih mode nilai"
                                items={[
                                    { value: 'integer', label: 'Bulat (Integer)' },
                                    { value: 'decimal', label: 'Desimal' },
                                ]}
                                value={variable.numeric?.mode ?? 'integer'}
                                onValueChange={(mode) =>
                                    patch({
                                        numeric: {
                                            mode: mode as 'integer' | 'decimal',
                                            scale: mode === 'integer' ? 0 : variable.numeric?.scale || 2,
                                            allow_negative: variable.numeric?.allow_negative ?? false,
                                        },
                                    })
                                }
                                disabled={disabled}
                            />
                            <FieldError>{errors[`${prefix}.numeric.mode`]}</FieldError>
                        </Field>

                        {variable.numeric?.mode === 'decimal' && (
                            <Field data-invalid={!!errors[`${prefix}.numeric.scale`]}>
                                <FieldLabel htmlFor={`${prefix}.numeric.scale`}>Presisi Desimal (Digit) *</FieldLabel>
                                <Input
                                    id={`${prefix}.numeric.scale`}
                                    type="number"
                                    min={1}
                                    max={6}
                                    value={variable.numeric?.scale ?? 2}
                                    disabled={disabled}
                                    onChange={(e) =>
                                        patch({
                                            numeric: {
                                                mode: 'decimal',
                                                scale: Math.max(1, Math.min(6, Number(e.target.value) || 1)),
                                                allow_negative: variable.numeric?.allow_negative ?? false,
                                            },
                                        })
                                    }
                                />
                                <FieldDescription>Batas digit desimal (skala 1–6).</FieldDescription>
                                <FieldError>{errors[`${prefix}.numeric.scale`]}</FieldError>
                            </Field>
                        )}
                    </div>

                    <Field orientation="horizontal">
                        <FieldLabel htmlFor={`${prefix}.numeric.allow_negative`}>Izinkan nilai negatif</FieldLabel>
                        <Switch
                            id={`${prefix}.numeric.allow_negative`}
                            checked={variable.numeric?.allow_negative ?? false}
                            disabled={disabled}
                            onCheckedChange={(allow_negative) =>
                                patch({
                                    numeric: {
                                        mode: variable.numeric?.mode ?? 'integer',
                                        scale: variable.numeric?.scale ?? 0,
                                        allow_negative,
                                    },
                                })
                            }
                        />
                    </Field>

                    {variable.name && (
                        <div className="flex flex-col gap-2 pt-2">
                            <FieldDescription>Token yang dapat digunakan di DOCX:</FieldDescription>
                            <div className="flex flex-wrap items-center gap-4">
                                <CopyPlaceholder name={variable.name} label="Salin nilai angka" />
                                <CopyPlaceholder name={`${variable.name}:terbilang`} label="Salin terbilang angka" />
                            </div>
                        </div>
                    )}
                </FieldSet>
            )}

            {isMoney && (
                <FieldSet>
                    <FieldLegend>Pengaturan Nominal (Rupiah)</FieldLegend>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field data-invalid={!!errors[`${prefix}.numeric.mode`]}>
                            <FieldLabel htmlFor={`${prefix}.numeric.mode`}>Format Nominal *</FieldLabel>
                            <OptionSelect
                                id={`${prefix}.numeric.mode`}
                                name={`${prefix}.numeric.mode`}
                                placeholder="Pilih format nominal"
                                items={[
                                    { value: 'decimal', label: 'Desimal (Rupiah dan Sen, 2 digit)' },
                                    { value: 'integer', label: 'Bulat (Rupiah penuh tanpa sen)' },
                                ]}
                                value={variable.numeric?.mode ?? 'decimal'}
                                onValueChange={(mode) =>
                                    patch({
                                        numeric: {
                                            mode: mode as 'integer' | 'decimal',
                                            scale: mode === 'integer' ? 0 : 2,
                                            allow_negative: variable.numeric?.allow_negative ?? false,
                                        },
                                    })
                                }
                                disabled={disabled}
                            />
                            <FieldError>{errors[`${prefix}.numeric.mode`]}</FieldError>
                        </Field>

                        <Field orientation="horizontal" className="sm:self-end sm:pb-2">
                            <FieldLabel htmlFor={`${prefix}.numeric.allow_negative`}>
                                Izinkan nominal negatif
                            </FieldLabel>
                            <Switch
                                id={`${prefix}.numeric.allow_negative`}
                                checked={variable.numeric?.allow_negative ?? false}
                                disabled={disabled}
                                onCheckedChange={(allow_negative) =>
                                    patch({
                                        numeric: {
                                            mode: variable.numeric?.mode ?? 'decimal',
                                            scale: (variable.numeric?.mode ?? 'decimal') === 'integer' ? 0 : 2,
                                            allow_negative,
                                        },
                                    })
                                }
                            />
                        </Field>
                    </div>

                    {variable.name && (
                        <div className="flex flex-col gap-2 pt-2">
                            <FieldDescription>Token yang dapat digunakan di DOCX:</FieldDescription>
                            <div className="flex flex-wrap items-center gap-4">
                                <CopyPlaceholder name={variable.name} label="Salin nominal (Rp)" />
                                <CopyPlaceholder name={`${variable.name}:terbilang`} label="Salin terbilang nominal" />
                            </div>
                        </div>
                    )}
                </FieldSet>
            )}

            {isDateRange && (
                <FieldSet>
                    <FieldLegend>Pengaturan Rentang Tanggal</FieldLegend>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field data-invalid={!!errors[`${prefix}.date_range.basis`]}>
                            <FieldLabel htmlFor={`${prefix}.date_range.basis`}>Basis Perhitungan *</FieldLabel>
                            <OptionSelect
                                id={`${prefix}.date_range.basis`}
                                name={`${prefix}.date_range.basis`}
                                placeholder="Pilih basis perhitungan"
                                items={[
                                    { value: 'working_days', label: 'Hari Kerja (Kalender Kerja)' },
                                    { value: 'calendar_days', label: 'Hari Kalender' },
                                ]}
                                value={variable.date_range?.basis ?? 'working_days'}
                                onValueChange={(basis) =>
                                    patch({
                                        date_range: {
                                            basis: basis as 'calendar_days' | 'working_days',
                                            date_format: variable.date_range?.date_format || 'd-m-Y',
                                        },
                                    })
                                }
                                disabled={disabled}
                            />
                            <FieldDescription>
                                Hari kerja memperhitungkan pola mingguan dan hari libur Kalender Kerja.
                            </FieldDescription>
                            <FieldError>{errors[`${prefix}.date_range.basis`]}</FieldError>
                        </Field>

                        <Field data-invalid={!!errors[`${prefix}.date_range.date_format`]}>
                            <FieldLabel htmlFor={`${prefix}.date_range.date_format`}>Format Tanggal *</FieldLabel>
                            <OptionSelect
                                id={`${prefix}.date_range.date_format`}
                                name={`${prefix}.date_range.date_format`}
                                placeholder="Pilih format tanggal"
                                items={[
                                    { value: 'd-m-Y', label: 'd-m-Y (contoh: 30-10-2026)' },
                                    { value: 'd F Y', label: 'd F Y (contoh: 30 Oktober 2026)' },
                                    { value: 'd/m/Y', label: 'd/m/Y (contoh: 30/10/2026)' },
                                    { value: 'j M Y', label: 'j M Y (contoh: 30 Okt 2026)' },
                                ]}
                                value={variable.date_range?.date_format ?? 'd-m-Y'}
                                onValueChange={(format) =>
                                    patch({
                                        date_range: {
                                            basis: variable.date_range?.basis ?? 'working_days',
                                            date_format: format,
                                        },
                                    })
                                }
                                disabled={disabled}
                            />
                            <FieldError>{errors[`${prefix}.date_range.date_format`]}</FieldError>
                        </Field>
                    </div>

                    {variable.name && (
                        <div className="flex flex-col gap-2 pt-2">
                            <FieldDescription>Token yang dapat digunakan di DOCX:</FieldDescription>
                            <div className="flex flex-wrap items-center gap-3">
                                <CopyPlaceholder name={`${variable.name}:mulai`} label="Mulai" />
                                <CopyPlaceholder name={`${variable.name}:akhir`} label="Akhir" />
                                <CopyPlaceholder name={`${variable.name}:jumlah_hari`} label="Jumlah hari" />
                                <CopyPlaceholder name={`${variable.name}:terbilang`} label="Terbilang hari" />
                                <CopyPlaceholder name={`${variable.name}:durasi`} label="Durasi lengkap" />
                            </div>
                        </div>
                    )}
                </FieldSet>
            )}
        </FieldGroup>
    );
}
