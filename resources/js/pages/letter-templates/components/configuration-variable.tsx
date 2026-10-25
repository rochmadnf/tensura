import { OptionSelect } from '@/components/option-select';
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
import type { ConfigurationOptions, ConfigurationVariable } from '@/schemas/letter-template-configuration';
import { CopyPlaceholder } from './copy-placeholder';

export function ConfigurationVariableFields({
    variable,
    index,
    options,
    disabled,
    errors,
    onChange,
    onRemove,
    onMove,
    count,
}: {
    variable: ConfigurationVariable;
    index: number;
    options: ConfigurationOptions;
    disabled: boolean;
    errors: Record<string, string | undefined>;
    onChange: (value: ConfigurationVariable) => void;
    onRemove: () => void;
    onMove: (direction: number) => void;
    count: number;
}) {
    const prefix = `variables.${index}`;
    const combo = variable.type === 4;
    function patch(changes: Partial<ConfigurationVariable>) {
        onChange({ ...variable, ...changes });
    }
    const choices = (items: { id: string | number; label: string }[]) =>
        items.map((item) => ({ value: String(item.id), label: item.label }));

    return (
        <FieldSet>
            <FieldLegend>{variable.label || `Variabel ${index + 1}`}</FieldLegend>
            <FieldGroup>
                <FieldError>{errors[`${prefix}.id`]}</FieldError>
                <Field data-invalid={!!errors[`${prefix}.name`]}>
                    <FieldLabel htmlFor={`${prefix}.name`}>Nama Placeholder</FieldLabel>
                    <Input
                        id={`${prefix}.name`}
                        value={variable.name}
                        onChange={(e) => patch({ name: e.target.value })}
                        maxLength={100}
                        aria-invalid={!!errors[`${prefix}.name`]}
                        disabled={disabled}
                        placeholder="tujuan_perjalanan"
                    />
                    <FieldDescription>
                        Awali dengan huruf kecil. Gunakan huruf kecil, angka, dan garis bawah.
                    </FieldDescription>
                    <FieldError>{errors[`${prefix}.name`]}</FieldError>
                    {!variable.is_multiple && <CopyPlaceholder name={variable.name} />}
                </Field>
                <Field data-invalid={!!errors[`${prefix}.label`]}>
                    <FieldLabel htmlFor={`${prefix}.label`}>Label Variabel</FieldLabel>
                    <Input
                        id={`${prefix}.label`}
                        value={variable.label}
                        onChange={(e) => patch({ label: e.target.value })}
                        maxLength={100}
                        aria-invalid={!!errors[`${prefix}.label`]}
                        disabled={disabled}
                        placeholder="Tujuan Perjalanan"
                    />
                    <FieldError>{errors[`${prefix}.label`]}</FieldError>
                </Field>
                <Field data-invalid={!!errors[`${prefix}.type`]}>
                    <FieldLabel htmlFor={`${prefix}.type`}>Jenis Variabel</FieldLabel>
                    <OptionSelect
                        id={`${prefix}.type`}
                        name={`${prefix}.type`}
                        items={choices(options.types)}
                        value={String(variable.type)}
                        onValueChange={(value) =>
                            patch({
                                type: Number(value),
                                option_source: null,
                                reference_group: null,
                                is_multiple: false,
                                columns: [],
                            })
                        }
                        disabled={disabled}
                        aria-invalid={!!errors[`${prefix}.type`]}
                        placeholder="Pilih jenis variabel"
                    />
                    <FieldError>{errors[`${prefix}.type`]}</FieldError>
                </Field>
                <Field orientation="horizontal">
                    <Checkbox
                        id={`${prefix}.is_required`}
                        checked={variable.is_required}
                        onCheckedChange={(checked) => patch({ is_required: checked })}
                        disabled={disabled}
                    />
                    <FieldLabel htmlFor={`${prefix}.is_required`}>Wajib diisi</FieldLabel>
                </Field>
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
                                    patch({ is_multiple: checked, columns: checked ? variable.columns : [] })
                                }
                                disabled={disabled}
                            />
                            <FieldLabel htmlFor={`${prefix}.is_multiple`}>
                                Pilihan multiple untuk tabel berulang
                            </FieldLabel>
                        </Field>
                        {variable.is_multiple && (
                            <FieldSet>
                                <FieldLegend>Kolom Tabel Berulang</FieldLegend>
                                <FieldDescription>
                                    Letakkan placeholder setiap kolom pada satu baris tabel di DOCX.
                                </FieldDescription>
                                <FieldError>{errors[`${prefix}.columns`]}</FieldError>
                                <FieldGroup>
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
                                                        items={choices(
                                                            options.columns[variable.option_source ?? 0] ?? [],
                                                        )}
                                                        value={column.source_attribute}
                                                        onValueChange={(value) =>
                                                            patchColumn({ source_attribute: value })
                                                        }
                                                        disabled={disabled}
                                                        aria-invalid={!!errors[`${path}.source_attribute`]}
                                                        placeholder="Pilih atribut"
                                                    />
                                                    <FieldError>{errors[`${path}.source_attribute`]}</FieldError>
                                                </Field>
                                                <Field data-invalid={!!errors[`${path}.placeholder`]}>
                                                    <FieldLabel htmlFor={`${path}.placeholder`}>
                                                        Placeholder Kolom {columnIndex + 1}
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
                                                <div className="flex flex-wrap gap-2">
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        disabled={disabled || columnIndex === 0}
                                                        onClick={() => {
                                                            const columns = [...variable.columns];
                                                            [columns[columnIndex - 1], columns[columnIndex]] = [
                                                                columns[columnIndex],
                                                                columns[columnIndex - 1],
                                                            ];
                                                            patch({ columns });
                                                        }}
                                                        aria-label={`Naikkan kolom ${columnIndex + 1}`}
                                                    >
                                                        Naik
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        disabled={
                                                            disabled || columnIndex === variable.columns.length - 1
                                                        }
                                                        onClick={() => {
                                                            const columns = [...variable.columns];
                                                            [columns[columnIndex + 1], columns[columnIndex]] = [
                                                                columns[columnIndex],
                                                                columns[columnIndex + 1],
                                                            ];
                                                            patch({ columns });
                                                        }}
                                                        aria-label={`Turunkan kolom ${columnIndex + 1}`}
                                                    >
                                                        Turun
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
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
                                                    { id: null, source_attribute: '', placeholder: '' },
                                                ],
                                            })
                                        }
                                    >
                                        Tambah Kolom
                                    </Button>
                                </FieldGroup>
                            </FieldSet>
                        )}
                    </>
                )}
                <div className="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        disabled={disabled || index === 0}
                        onClick={() => onMove(-1)}
                        aria-label={`Naikkan variabel ${variable.label || index + 1}`}
                    >
                        Naik
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        disabled={disabled || index === count - 1}
                        onClick={() => onMove(1)}
                        aria-label={`Turunkan variabel ${variable.label || index + 1}`}
                    >
                        Turun
                    </Button>
                    <Button type="button" variant="outline" disabled={disabled} onClick={onRemove}>
                        Hapus Variabel
                    </Button>
                </div>
            </FieldGroup>
        </FieldSet>
    );
}
