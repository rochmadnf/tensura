import { ArrowUp01Icon, ArrowDown01Icon, Delete02Icon, Add01Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { EmployeeDateFormat } from './employee-attribute-fields';
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
    function patch(changes: Partial<ConfigurationVariable>) {
        onChange({ ...variable, ...changes });
    }
    const choices = (items: { id: string | number; label: string }[]) =>
        items.map((item) => ({ value: String(item.id), label: item.label }));
    return (
        <FieldGroup>
            {' '}
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
                                Letakkan variabel setiap kolom pada satu baris tabel di DOCX.
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
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="icon-sm"
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
                                                    <HugeiconsIcon icon={ArrowUp01Icon} aria-hidden="true" />
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="icon-sm"
                                                    disabled={disabled || columnIndex === variable.columns.length - 1}
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
                                                    <HugeiconsIcon icon={ArrowDown01Icon} aria-hidden="true" />
                                                </Button>
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
                                    <HugeiconsIcon icon={Add01Icon} data-icon="inline-start" aria-hidden="true" />
                                    Tambah Kolom
                                </Button>
                            </FieldGroup>
                        </FieldSet>
                    )}
                </>
            )}
        </FieldGroup>
    );
}
