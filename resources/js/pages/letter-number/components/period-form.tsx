import type { SubmitEventHandler } from 'react';
import { FormCard } from '@/components/form-card';
import { FormActions } from '@/components/form-actions';
import { OptionSelect } from '@/components/option-select';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { gooeyToast } from '@/components/ui/goey-toaster';
import { useValibotForm } from '@/hooks/use-valibot-form';
import { LetterNumberPeriodSchema } from '@/schemas/letter-number-period';
import ln from '@/routes/ln';
import { store } from '@/routes/ln/periods';
import type { LetterNumberColumns } from './data-table/column';

const months = Array.from({ length: 12 }, (_, index) => ({
    value: String(index + 1),
    label: new Date(2026, index, 1).toLocaleDateString('id-ID', { month: 'long' }),
}));

export function PeriodForm({
    letterNumber,
    defaults,
}: {
    letterNumber: LetterNumberColumns;
    defaults: { year: number; month: number };
}) {
    const { form, submit } = useValibotForm(LetterNumberPeriodSchema, {
        year: String(defaults.year),
        month: String(defaults.month),
        mode: letterNumber.needs_initialization ? ('legacy' as const) : ('empty' as const),
        last_page_number: null as string | null,
        last_sequence_number: null as string | null,
    });
    const handleSubmit: SubmitEventHandler<HTMLFormElement> = (event) => {
        event.preventDefault();
        if (form.processing) return;
        const action = store(letterNumber.id);
        const showError = () => gooeyToast.error('Galat', { description: 'Periksa periode dan angka awal.' });
        if (
            !submit(action.method, action.url, {
                preserveScroll: true,
                onError: showError,
                onSuccess: () => {
                    form.reset();
                    gooeyToast.success('Berhasil', { description: 'Periode penomoran disiapkan.' });
                },
            })
        )
            showError();
    };
    return (
        <FormCard
            sectioned
            title="Siapkan Periode"
            description="Angka awal berarti nomor terakhir dari pencatatan sebelumnya. Periode yang tersimpan tidak dapat diubah melalui formulir ini."
        >
            <form noValidate onSubmit={handleSubmit}>
                <FieldGroup>
                    {letterNumber.needs_initialization ? (
                        <FieldDescription>
                            Angka lama {String(letterNumber.page_number).padStart(2, '0')}.
                            {String(letterNumber.sequence_number).padStart(2, '0')} belum mempunyai periode. Pilih bulan
                            dan tahun untuk memindahkannya satu kali; angka lama tetap disimpan selama transisi.
                        </FieldDescription>
                    ) : (
                        <Field data-invalid={!!form.errors.mode}>
                            <FieldLabel htmlFor="period-mode">Sumber angka awal</FieldLabel>
                            <OptionSelect
                                id="period-mode"
                                name="mode"
                                placeholder="Pilih sumber angka awal"
                                items={[
                                    { value: 'empty', label: 'Belum digunakan — mulai 01.01' },
                                    { value: 'custom', label: 'Nomor terakhir dari pencatatan sebelumnya' },
                                ]}
                                value={form.data.mode}
                                onValueChange={(value) => {
                                    form.setData('mode', value as 'empty' | 'custom');
                                    form.setData('last_page_number', null);
                                    form.setData('last_sequence_number', null);
                                    form.clearErrors();
                                }}
                                disabled={form.processing}
                            />
                            <FieldError>{form.errors.mode}</FieldError>
                        </Field>
                    )}
                    {letterNumber.needs_initialization && <FieldError>{form.errors.mode}</FieldError>}
                    <FieldGroup className="grid gap-4 sm:grid-cols-2">
                        <Field data-invalid={!!form.errors.month}>
                            <FieldLabel htmlFor="period-month">Bulan</FieldLabel>
                            <OptionSelect
                                id="period-month"
                                name="month"
                                placeholder="Pilih bulan"
                                items={months}
                                value={form.data.month}
                                onValueChange={(value) => form.setData('month', value)}
                                disabled={form.processing}
                            />
                            <FieldError>{form.errors.month}</FieldError>
                        </Field>
                        <Field data-invalid={!!form.errors.year}>
                            <FieldLabel htmlFor="period-year">Tahun</FieldLabel>
                            <Input
                                id="period-year"
                                name="year"
                                type="number"
                                min={1}
                                max={9999}
                                value={form.data.year}
                                onChange={(event) => form.setData('year', event.target.value)}
                                disabled={form.processing}
                                aria-invalid={!!form.errors.year}
                            />
                            <FieldError>{form.errors.year}</FieldError>
                        </Field>
                    </FieldGroup>
                    {form.data.mode === 'custom' && (
                        <FieldGroup className="grid gap-4 sm:grid-cols-2">
                            {(['last_page_number', 'last_sequence_number'] as const).map((name) => (
                                <Field key={name} data-invalid={!!form.errors[name]}>
                                    <FieldLabel htmlFor={name}>
                                        {name === 'last_page_number' ? 'Halaman terakhir' : 'Nomor urut terakhir'}
                                    </FieldLabel>
                                    <Input
                                        id={name}
                                        name={name}
                                        type="number"
                                        min={1}
                                        max={name === 'last_page_number' ? 2147483647 : 99}
                                        value={form.data[name] ?? ''}
                                        onChange={(event) => form.setData(name, event.target.value || null)}
                                        disabled={form.processing}
                                        aria-invalid={!!form.errors[name]}
                                    />
                                    <FieldError>{form.errors[name]}</FieldError>
                                </Field>
                            ))}
                        </FieldGroup>
                    )}
                    <FormActions
                        cancelUrl={ln.index().url}
                        processing={form.processing}
                        onCancel={() => {
                            form.reset();
                            form.clearErrors();
                        }}
                    />
                </FieldGroup>
            </form>
        </FormCard>
    );
}
