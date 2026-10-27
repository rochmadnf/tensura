import { useEffect, useState } from 'react';
import { InformationCircleIcon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Button } from '@/components/ui/button';
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTitle, PopoverTrigger } from '@/components/ui/popover';
import { dateFormat } from '@/routes/lt';

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
                                // ui-allow: icon-button-tooltip (pemicu popover)
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
