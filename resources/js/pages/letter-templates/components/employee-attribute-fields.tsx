import { useEffect, useState } from 'react';
import { InformationCircleIcon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Button } from '@/components/ui/button';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTitle, PopoverTrigger } from '@/components/ui/popover';
import { dateFormat } from '@/routes/lt';
import type { EmployeeAttributeOption, EmployeeAttributeSetting } from '@/schemas/letter-template-configuration';
import { CopyPlaceholder } from './copy-placeholder';

export function EmployeeDateFormat({
    id,
    value,
    disabled,
    onChange,
    error,
}: {
    id: string;
    value: string | null;
    disabled: boolean;
    onChange: (value: string | null) => void;
    error?: string;
}) {
    const [example, setExample] = useState('');
    useEffect(() => {
        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            fetch(dateFormat({ query: { date_format: value || 'd-m-Y' } }).url, {
                signal: controller.signal,
                headers: { Accept: 'application/json' },
            })
                .then(async (response) => {
                    if (!response.ok) throw new Error('Format tidak dapat ditampilkan.');
                    const result: { example: string; locale: string } = await response.json();
                    setExample(`${result.example} (${result.locale})`);
                })
                .catch(() => {
                    if (!controller.signal.aborted) setExample('Contoh format tidak tersedia.');
                });
        }, 400);
        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [value]);
    return (
        <Field data-invalid={!!error}>
            <FieldLabel htmlFor={id}>Format tanggal</FieldLabel>
            <div className="flex items-center gap-2">
                <Input
                    id={id}
                    value={value ?? 'd-m-Y'}
                    maxLength={100}
                    disabled={disabled}
                    aria-invalid={!!error}
                    onChange={(event) => onChange(event.target.value || null)}
                />
                <Popover>
                    <PopoverTrigger
                        render={
                            <Button
                                type="button"
                                variant="outline"
                                size="icon-sm"
                                aria-label="Informasi format tanggal"
                            />
                        }
                    >
                        <HugeiconsIcon icon={InformationCircleIcon} aria-hidden="true" />
                    </PopoverTrigger>
                    <PopoverContent align="end">
                        <PopoverTitle>Format tanggal PHP</PopoverTitle>
                        <p>
                            d/j: hari dengan/tanpa nol; m/n: bulan angka; M/F: nama bulan singkat/lengkap; Y/y: tahun
                            empat/dua digit; D/l: nama hari singkat/lengkap.
                        </p>
                        <p>
                            Contoh: d-m-Y → 25-10-2026; d F Y → 25 Oktober 2026. Nama hari dan bulan mengikuti bahasa
                            aplikasi.
                        </p>
                        <p>Awali huruf literal dengan garis miring terbalik, misalnya \T\g\l d-m-Y → Tgl 25-10-2026.</p>
                        <a
                            href="https://www.php.net/manual/en/datetime.format.php"
                            target="_blank"
                            rel="noreferrer"
                            className="text-primary underline underline-offset-4"
                        >
                            Dokumentasi format PHP
                        </a>
                    </PopoverContent>
                </Popover>
            </div>
            <FieldDescription>Contoh tanggal 25 Oktober 2026: {example || 'Memuat…'}</FieldDescription>
            <FieldError>{error}</FieldError>
        </Field>
    );
}

export function EmployeeAttributeFields({
    prefix,
    path,
    attributes,
    settings,
    onChange,
    disabled,
    errors,
    readOnly = false,
}: {
    prefix: string;
    path: string;
    attributes: EmployeeAttributeOption[];
    settings: EmployeeAttributeSetting[];
    onChange?: (settings: EmployeeAttributeSetting[]) => void;
    disabled: boolean;
    errors: Record<string, string | undefined>;
    readOnly?: boolean;
}) {
    return (
        <details className="text-sm" open={Object.keys(errors).some((key) => key.startsWith(path)) ? true : undefined}>
            <summary className="cursor-pointer font-medium">Atribut Pegawai ({attributes.length})</summary>
            <FieldGroup className="pt-4">
                <p className="text-muted-foreground">
                    Semua token mengambil data dari pegawai yang sama. Gunakan atribut yang dibutuhkan. Fallback berupa
                    teks literal saat atribut kosong.
                </p>
                <FieldError>{errors[path]}</FieldError>
                {attributes.map((attribute) => {
                    const index = settings.findIndex((setting) => setting.source_attribute === attribute.id);
                    const setting = settings[index] ?? {
                        source_attribute: attribute.id,
                        default_text: null,
                        date_format: attribute.type === 'date' ? 'd-m-Y' : null,
                    };
                    const inputId = `${path}.${attribute.id}`;
                    const fieldPath = `${path}.${index < 0 ? settings.length : index}`;
                    function patch(changes: Partial<EmployeeAttributeSetting>) {
                        onChange?.(
                            index < 0
                                ? [...settings, { ...setting, ...changes }]
                                : settings.map((item, position) =>
                                      position === index ? { ...setting, ...changes } : item,
                                  ),
                        );
                    }
                    return (
                        <FieldGroup key={attribute.id}>
                            <p className="font-medium">{attribute.label}</p>
                            <CopyPlaceholder name={`${prefix}_${attribute.suffix}`} />
                            {readOnly ? (
                                <p className="text-muted-foreground">
                                    Fallback: {setting.default_text ?? 'Kosong'}
                                    {attribute.type === 'date' ? ` · Format: ${setting.date_format ?? 'd-m-Y'}` : ''}
                                </p>
                            ) : (
                                <>
                                    <Field>
                                        <FieldLabel htmlFor={`${inputId}.default_text`}>
                                            Fallback {attribute.label}
                                        </FieldLabel>
                                        <Input
                                            id={`${inputId}.default_text`}
                                            value={setting.default_text ?? ''}
                                            maxLength={10000}
                                            disabled={disabled}
                                            placeholder="Kosong"
                                            onChange={(event) => patch({ default_text: event.target.value || null })}
                                        />
                                        <FieldError>
                                            {errors[`${fieldPath}.default_text`] ||
                                                errors[`${fieldPath}.source_attribute`]}
                                        </FieldError>
                                    </Field>
                                    {attribute.type === 'date' && (
                                        <EmployeeDateFormat
                                            id={`${inputId}.date_format`}
                                            value={setting.date_format}
                                            disabled={disabled}
                                            onChange={(date_format) => patch({ date_format })}
                                            error={errors[`${fieldPath}.date_format`]}
                                        />
                                    )}
                                </>
                            )}
                        </FieldGroup>
                    );
                })}
            </FieldGroup>
        </details>
    );
}
