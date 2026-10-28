import { useState, type SubmitEventHandler } from 'react';
import { Head, Link, router, useForm, useHttp } from '@inertiajs/react';
import { CheckmarkCircle02Icon, Download04Icon, LoaderIcon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { FormCard } from '@/components/form-card';
import { FormActions } from '@/components/form-actions';
import { OptionSelect } from '@/components/option-select';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/reui/alert';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { gooeyToast } from '@/components/ui/goey-toaster';
import lt from '@/routes/lt';
import exchange from '@/routes/lt/exchange';

const pageName = 'Pertukaran Templat Surat';
type Review = {
    token: string;
    name: string;
    source_version: number;
    has_docx: boolean;
    fonts: number;
    expires_at: string;
    incompatibilities: string[];
    font_limitations: string[];
    variables: { name: string; label: string; needs_reference: boolean }[];
    signer_slots: number;
};
type Estimate = {
    estimated_bytes: number;
    configuration_bytes: number;
    docx_bytes: number;
    font_bytes: number;
    missing_fonts: string[];
    limitations: string[];
};
type ExportData = { include_docx: boolean; include_fonts: boolean; password: string; password_confirmation: string };
const size = (bytes: number) => `${(bytes / 1024).toLocaleString('id-ID', { maximumFractionDigits: 1 })} KB`;

export default function LetterTemplateExchange({
    mode,
    source,
    review,
    targets = [],
}: {
    mode: 'import' | 'export' | 'review';
    source?: { template: string; version: string; name: string; number: number };
    review?: Review;
    targets?: { id: string; label: string; version_token: string | null }[];
}) {
    const upload = useHttp<{ package: File | null; password: string }, { review_url: string }>({
        package: null,
        password: '',
    });
    const exporting = useHttp<ExportData, { download_url: string; size: number }>({
        include_docx: true,
        include_fonts: false,
        password: '',
        password_confirmation: '',
    });
    const estimating = useHttp<{ include_docx: boolean; include_fonts: boolean }, Estimate>({
        include_docx: true,
        include_fonts: false,
    });
    const [estimate, setEstimate] = useState<Estimate | null>(null);
    const [replacementOpen, setReplacementOpen] = useState(false);
    const form = useForm({
        target: '',
        name: review?.name ?? '',
        letter_number: '',
        reference_groups: {} as Record<string, string>,
        signers: Array.from({ length: review?.signer_slots ?? 0 }, () => [] as string[]),
        replace_draft: false,
        version_token: null as string | null,
        confirmed: false,
    });
    const busy = upload.processing || exporting.processing || estimating.processing || form.processing;
    const title =
        mode === 'export' ? 'Ekspor Templat' : mode === 'review' ? 'Periksa dan Petakan Impor' : 'Impor Templat';
    const showError = () =>
        gooeyToast.error('Galat', { description: 'Periksa isian dan pesan kesalahan, lalu coba kembali.' });
    const args = { letterTemplate: source?.template ?? '', version: source?.version ?? '' };
    const target = targets.find((item) => item.id === form.data.target);
    const packageErrors = [
        ...Object.values(estimating.errors).flat(),
        ...Object.entries(exporting.errors)
            .filter(([key]) => key !== 'password' && key !== 'password_confirmation')
            .flatMap(([, value]) => value),
    ].join(' ');
    const importErrors = form.errors as Record<string, string | undefined>;
    const generalImportErrors = Object.entries(importErrors)
        .filter(
            ([key]) =>
                !['name', 'target', 'letter_number'].includes(key) &&
                !key.startsWith('variables.') &&
                !key.startsWith('signer_slots.') &&
                !key.startsWith('signers.') &&
                !key.startsWith('reference_groups.'),
        )
        .map(([, value]) => value)
        .join(' ');
    async function estimatePackage() {
        if (busy || !source) return;
        try {
            estimating.transform(() => ({
                include_docx: exporting.data.include_docx,
                include_fonts: exporting.data.include_fonts,
            }));
            setEstimate(await estimating.post(exchange.estimate(args).url));
        } catch {
            showError();
        }
    }
    const submitExport: SubmitEventHandler<HTMLFormElement> = async (event) => {
        event.preventDefault();
        if (busy || !source || !estimate || estimate.missing_fonts.length > 0) return;
        try {
            await exporting.post(exchange.export(args).url);
            exporting.reset('password', 'password_confirmation');
            gooeyToast.success('Berhasil', { description: 'Paket terenkripsi siap diunduh.' });
        } catch {
            showError();
        }
    };
    const submitUpload: SubmitEventHandler<HTMLFormElement> = async (event) => {
        event.preventDefault();
        if (busy) return;
        try {
            const result = await upload.post(exchange.inspect().url);
            upload.reset('password');
            router.visit(result.review_url);
        } catch {
            showError();
        }
    };
    const submitImport: SubmitEventHandler<HTMLFormElement> = (event) => {
        event.preventDefault();
        if (busy || !review || review.incompatibilities.length > 0) return;
        form.post(exchange.store(review.token).url, {
            onError: showError,
            onSuccess: () =>
                gooeyToast.success('Berhasil', {
                    description: 'Templat diimpor sebagai draft. Lanjutkan validasi dan pemeriksaan lokal.',
                }),
        });
    };
    function cancelImport() {
        if (!busy && review) router.delete(exchange.destroy(review.token).url);
    }
    return (
        <>
            <Head title={title}>
                <meta
                    name="description"
                    content="Ekspor dan impor paket templat Tensura dengan kata sandi dan pemeriksaan kompatibilitas."
                />
            </Head>
            <div className="container mx-auto">
                <FormCard
                    title={title}
                    description={
                        mode === 'export'
                            ? `${source?.name}, versi ${source?.number}. Kata sandi diperlukan untuk membuka paket di instalasi tujuan.`
                            : 'Semua hasil impor disimpan sebagai draft dan memerlukan validasi, uji, serta konfirmasi lokal.'
                    }
                >
                    {mode === 'import' && (
                        <form noValidate onSubmit={submitUpload}>
                            <FieldGroup>
                                <Field data-disabled={busy} data-invalid={!!upload.errors.package}>
                                    <FieldLabel htmlFor="package">
                                        Paket Tensura (.tnex){' '}
                                        <span className="text-destructive" aria-hidden="true">
                                            *
                                        </span>
                                    </FieldLabel>
                                    <Input
                                        id="package"
                                        type="file"
                                        accept=".tnex"
                                        required
                                        disabled={busy}
                                        aria-invalid={!!upload.errors.package}
                                        onChange={(event) => upload.setData('package', event.target.files?.[0] ?? null)}
                                    />
                                    <FieldDescription>
                                        Ukuran maksimum 64 MB. Pemeriksaan berlaku 30 menit dan hanya dapat digunakan
                                        akun ini.
                                    </FieldDescription>
                                    <FieldError>{upload.errors.package}</FieldError>
                                </Field>
                                <Field data-disabled={busy} data-invalid={!!upload.errors.password}>
                                    <FieldLabel htmlFor="import-password">
                                        Kata sandi paket{' '}
                                        <span className="text-destructive" aria-hidden="true">
                                            *
                                        </span>
                                    </FieldLabel>
                                    <Input
                                        id="import-password"
                                        type="password"
                                        autoComplete="off"
                                        required
                                        minLength={12}
                                        disabled={busy}
                                        aria-invalid={!!upload.errors.password}
                                        value={upload.data.password}
                                        onChange={(event) => upload.setData('password', event.target.value)}
                                    />
                                    <FieldError>{upload.errors.password}</FieldError>
                                </Field>
                                <div className="flex flex-wrap gap-2">
                                    <Button type="submit" disabled={busy}>
                                        <HugeiconsIcon
                                            icon={busy ? LoaderIcon : CheckmarkCircle02Icon}
                                            strokeWidth={2}
                                            data-icon="inline-start"
                                            className={busy ? 'animate-spin' : undefined}
                                        />
                                        {busy ? 'Memeriksa…' : 'Periksa Paket'}
                                    </Button>
                                    <Button
                                        variant="outline"
                                        nativeButton={false}
                                        disabled={busy}
                                        render={<Link href={lt.index().url} />}
                                    >
                                        Batal
                                    </Button>
                                </div>
                            </FieldGroup>
                        </form>
                    )}
                    {mode === 'export' && (
                        <form noValidate onSubmit={submitExport}>
                            <FieldGroup>
                                {(['include_docx', 'include_fonts'] as const).map((key) => (
                                    <Field key={key} orientation="horizontal" data-disabled={busy}>
                                        <Checkbox
                                            id={key}
                                            checked={exporting.data[key]}
                                            disabled={busy}
                                            onCheckedChange={(checked) => {
                                                exporting.setData(key, checked);
                                                setEstimate(null);
                                            }}
                                        />
                                        <FieldLabel htmlFor={key}>
                                            {key === 'include_docx'
                                                ? 'Sertakan DOCX asli'
                                                : 'Sertakan font yang digunakan'}
                                        </FieldLabel>
                                    </Field>
                                ))}
                                <FieldDescription>
                                    Font diperiksa dari DOCX sumber meskipun dokumen tidak disertakan. Data pegawai, isi
                                    referensi, nomor surat, riwayat, dan bukti pemeriksaan tidak dibawa.
                                </FieldDescription>
                                <Button variant="outline" type="button" disabled={busy} onClick={estimatePackage}>
                                    <HugeiconsIcon
                                        icon={estimating.processing ? LoaderIcon : CheckmarkCircle02Icon}
                                        strokeWidth={2}
                                        data-icon="inline-start"
                                        className={estimating.processing ? 'animate-spin' : undefined}
                                    />
                                    {estimating.processing ? 'Menghitung…' : 'Hitung Perkiraan Ukuran'}
                                </Button>
                                {estimate && (
                                    <p className="text-sm" role="status">
                                        Perkiraan {size(estimate.estimated_bytes)}: konfigurasi{' '}
                                        {size(estimate.configuration_bytes)}, DOCX {size(estimate.docx_bytes)}, font{' '}
                                        {size(estimate.font_bytes)}.
                                    </p>
                                )}
                                {estimate && estimate.missing_fonts.length > 0 && (
                                    <Alert variant="warning">
                                        <AlertDescription>
                                            Lengkapi font berikut atau nonaktifkan penyertaan font:{' '}
                                            {estimate.missing_fonts.join(', ')}.
                                        </AlertDescription>
                                    </Alert>
                                )}
                                {estimate?.limitations.map((message) => (
                                    <Alert variant="warning" key={message}>
                                        <AlertDescription>{message}</AlertDescription>
                                    </Alert>
                                ))}
                                {(['password', 'password_confirmation'] as const).map((key) => (
                                    <Field key={key} data-disabled={busy} data-invalid={!!exporting.errors[key]}>
                                        <FieldLabel htmlFor={key}>
                                            {key === 'password'
                                                ? 'Kata sandi (minimal 12 karakter)'
                                                : 'Konfirmasi kata sandi'}{' '}
                                            <span className="text-destructive" aria-hidden="true">
                                                *
                                            </span>
                                        </FieldLabel>
                                        <Input
                                            id={key}
                                            type="password"
                                            autoComplete="new-password"
                                            required
                                            minLength={12}
                                            disabled={busy}
                                            aria-invalid={!!exporting.errors[key]}
                                            value={exporting.data[key]}
                                            onChange={(event) => exporting.setData(key, event.target.value)}
                                        />
                                        <FieldError>{exporting.errors[key]}</FieldError>
                                    </Field>
                                ))}
                                {packageErrors && (
                                    <Alert variant="destructive">
                                        <AlertDescription>{packageErrors}</AlertDescription>
                                    </Alert>
                                )}
                                {exporting.response && (
                                    <Alert variant="success">
                                        <AlertDescription>
                                            Paket selesai dibuat. Ukuran akhir {size(exporting.response.size)}. Tautan
                                            unduhan berlaku sekali selama 30 menit.
                                        </AlertDescription>
                                    </Alert>
                                )}
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        type="submit"
                                        disabled={busy || !estimate || estimate.missing_fonts.length > 0}
                                    >
                                        <HugeiconsIcon
                                            icon={exporting.processing ? LoaderIcon : Download04Icon}
                                            strokeWidth={2}
                                            data-icon="inline-start"
                                            className={exporting.processing ? 'animate-spin' : undefined}
                                        />
                                        {exporting.processing ? 'Membuat paket…' : 'Buat Paket Ekspor'}
                                    </Button>
                                    {exporting.response && (
                                        <Button
                                            variant="outline"
                                            nativeButton={false}
                                            disabled={busy}
                                            render={<a href={exporting.response.download_url} />}
                                        >
                                            <HugeiconsIcon
                                                icon={Download04Icon}
                                                strokeWidth={2}
                                                data-icon="inline-start"
                                            />
                                            Unduh Paket .tnex
                                        </Button>
                                    )}
                                    <Button
                                        variant="outline"
                                        nativeButton={false}
                                        disabled={busy}
                                        render={<Link href={lt.index().url} />}
                                    >
                                        Batal
                                    </Button>
                                </div>
                            </FieldGroup>
                        </form>
                    )}
                    {mode === 'review' && review && (
                        <form noValidate onSubmit={submitImport}>
                            <FieldGroup>
                                <div className="flex flex-wrap gap-2">
                                    <Badge variant="secondary">Versi sumber {review.source_version}</Badge>
                                    <Badge variant={review.has_docx ? 'info' : 'warning'}>
                                        {review.has_docx ? 'Dengan DOCX' : 'Tanpa DOCX'}
                                    </Badge>
                                    <Badge variant="secondary">{review.fonts} berkas font</Badge>
                                    <Badge variant={review.incompatibilities.length ? 'destructive' : 'success'}>
                                        {review.incompatibilities.length ? 'Tidak kompatibel' : 'Kompatibel'}
                                    </Badge>
                                </div>
                                <p className="text-sm">
                                    {review.variables.length} pertanyaan, {review.signer_slots} slot penandatangan.
                                    Berlaku sampai {new Date(review.expires_at).toLocaleString('id-ID')}.
                                </p>
                                {!review.has_docx && (
                                    <Alert variant="warning">
                                        <AlertDescription>
                                            Draft hasil impor tidak memiliki DOCX, termasuk ketika mengganti draft.
                                            Unggah dokumen kembali sebelum validasi.
                                        </AlertDescription>
                                    </Alert>
                                )}
                                {[...review.incompatibilities, ...review.font_limitations].map((message) => (
                                    <Alert key={message} variant="warning">
                                        <AlertDescription>{message}</AlertDescription>
                                    </Alert>
                                ))}
                                <Field data-disabled={busy} data-invalid={!!form.errors.target}>
                                    <FieldLabel htmlFor="target">Tujuan impor</FieldLabel>
                                    <OptionSelect
                                        id="target"
                                        name="target"
                                        forceCombobox
                                        disabled={busy}
                                        aria-invalid={!!form.errors.target}
                                        items={targets.map((item) => ({ value: item.id, label: item.label }))}
                                        value={form.data.target}
                                        placeholder="Buat templat baru"
                                        resetLabel="Buat templat baru"
                                        onValueChange={(value) => {
                                            form.setData({
                                                ...form.data,
                                                target: value,
                                                version_token:
                                                    targets.find((item) => item.id === value)?.version_token ?? null,
                                                replace_draft: false,
                                            });
                                        }}
                                    />
                                    <FieldError>{form.errors.target}</FieldError>
                                </Field>
                                {!form.data.target && (
                                    <Field data-disabled={busy} data-invalid={!!form.errors.name}>
                                        <FieldLabel htmlFor="template-name">
                                            Nama templat baru{' '}
                                            <span className="text-destructive" aria-hidden="true">
                                                *
                                            </span>
                                        </FieldLabel>
                                        <Input
                                            id="template-name"
                                            required
                                            disabled={busy}
                                            aria-invalid={!!form.errors.name}
                                            value={form.data.name}
                                            onChange={(event) => form.setData('name', event.target.value)}
                                        />
                                        <FieldError>{form.errors.name}</FieldError>
                                    </Field>
                                )}
                                {target?.version_token && (
                                    <Field data-disabled={busy}>
                                        <FieldDescription>
                                            Templat tujuan sudah memiliki draft. Konfirmasikan penggantian seluruh
                                            konfigurasi dan dokumennya.
                                        </FieldDescription>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            disabled={busy}
                                            onClick={() => setReplacementOpen(true)}
                                        >
                                            <HugeiconsIcon
                                                icon={CheckmarkCircle02Icon}
                                                strokeWidth={2}
                                                data-icon="inline-start"
                                            />
                                            {form.data.replace_draft
                                                ? 'Penggantian Draft Dikonfirmasi'
                                                : 'Konfirmasi Penggantian Draft'}
                                        </Button>
                                    </Field>
                                )}
                                <Field data-disabled={busy} data-invalid={!!form.errors.letter_number}>
                                    <FieldLabel htmlFor="letter-number">
                                        Klasifikasi nomor tujuan{' '}
                                        <span className="text-destructive" aria-hidden="true">
                                            *
                                        </span>
                                    </FieldLabel>
                                    <OptionSelect
                                        id="letter-number"
                                        name="letter_number"
                                        placeholder="Pilih klasifikasi nomor"
                                        required
                                        items={[]}
                                        forceCombobox
                                        remote={{ source: 'classifications' }}
                                        value={form.data.letter_number}
                                        disabled={busy}
                                        aria-invalid={!!form.errors.letter_number}
                                        onValueChange={(value) => form.setData('letter_number', value)}
                                    />
                                    <FieldError>{form.errors.letter_number}</FieldError>
                                </Field>
                                {review.variables.map(
                                    (variable, index) =>
                                        variable.needs_reference && (
                                            <Field
                                                key={variable.name}
                                                data-disabled={busy}
                                                data-invalid={
                                                    !!(
                                                        importErrors[`reference_groups.${variable.name}`] ||
                                                        importErrors[`variables.${index}.reference_group`]
                                                    )
                                                }
                                            >
                                                <FieldLabel htmlFor={`reference-${variable.name}`}>
                                                    Grup referensi: {variable.label}{' '}
                                                    <span className="text-destructive" aria-hidden="true">
                                                        *
                                                    </span>
                                                </FieldLabel>
                                                <OptionSelect
                                                    id={`reference-${variable.name}`}
                                                    name={`reference_groups.${variable.name}`}
                                                    placeholder="Pilih grup referensi"
                                                    aria-invalid={
                                                        !!(
                                                            importErrors[`reference_groups.${variable.name}`] ||
                                                            importErrors[`variables.${index}.reference_group`]
                                                        )
                                                    }
                                                    required
                                                    items={[]}
                                                    forceCombobox
                                                    remote={{ source: 'reference-groups' }}
                                                    value={form.data.reference_groups[variable.name] ?? ''}
                                                    disabled={busy}
                                                    onValueChange={(value) =>
                                                        form.setData('reference_groups', {
                                                            ...form.data.reference_groups,
                                                            [variable.name]: value,
                                                        })
                                                    }
                                                />
                                                <FieldError>
                                                    {importErrors[`reference_groups.${variable.name}`] ||
                                                        importErrors[`variables.${index}.reference_group`]}
                                                </FieldError>
                                            </Field>
                                        ),
                                )}
                                {form.data.signers.map((values, index) => (
                                    <Field
                                        key={index}
                                        data-disabled={busy}
                                        data-invalid={Object.keys(importErrors).some(
                                            (key) =>
                                                key.startsWith(`signer_slots.${index}.`) ||
                                                key.startsWith(`signers.${index}`),
                                        )}
                                    >
                                        <FieldLabel htmlFor={`signer-${index}`}>
                                            Kandidat penandatangan {index + 1}{' '}
                                            <span className="text-destructive" aria-hidden="true">
                                                *
                                            </span>
                                        </FieldLabel>
                                        <OptionSelect
                                            id={`signer-${index}`}
                                            name={`signers.${index}`}
                                            placeholder="Pilih kandidat penandatangan"
                                            aria-invalid={Object.keys(importErrors).some(
                                                (key) =>
                                                    key.startsWith(`signer_slots.${index}.`) ||
                                                    key.startsWith(`signers.${index}`),
                                            )}
                                            required
                                            multiple
                                            items={[]}
                                            forceCombobox
                                            remote={{ source: 'employees' }}
                                            value={values}
                                            disabled={busy}
                                            onValueChange={(value) =>
                                                form.setData(
                                                    'signers',
                                                    form.data.signers.map((current, position) =>
                                                        position === index ? value : current,
                                                    ),
                                                )
                                            }
                                        />
                                        <FieldError>
                                            {Object.entries(importErrors)
                                                .filter(
                                                    ([key]) =>
                                                        key.startsWith(`signer_slots.${index}.`) ||
                                                        key.startsWith(`signers.${index}`),
                                                )
                                                .map(([, value]) => value)
                                                .join(' ')}
                                        </FieldError>
                                    </Field>
                                ))}
                                <Field
                                    orientation="horizontal"
                                    data-disabled={busy}
                                    data-invalid={!!form.errors.confirmed}
                                >
                                    <Checkbox
                                        id="confirmed"
                                        required
                                        disabled={busy}
                                        checked={form.data.confirmed}
                                        onCheckedChange={(checked) => form.setData('confirmed', checked)}
                                    />
                                    <FieldLabel htmlFor="confirmed">
                                        Saya sudah memeriksa ringkasan dan pemetaan, lalu menyimpan hasil sebagai draft.{' '}
                                        <span className="text-destructive" aria-hidden="true">
                                            *
                                        </span>
                                    </FieldLabel>
                                </Field>
                                {generalImportErrors && (
                                    <Alert variant="destructive">
                                        <AlertDescription>{generalImportErrors}</AlertDescription>
                                    </Alert>
                                )}
                                <FormActions
                                    cancelUrl={lt.index().url}
                                    onCancel={cancelImport}
                                    processing={form.processing}
                                    disabled={busy}
                                    submitDisabled={
                                        review.incompatibilities.length > 0 ||
                                        !form.data.confirmed ||
                                        (!!target?.version_token && !form.data.replace_draft)
                                    }
                                />
                            </FieldGroup>
                        </form>
                    )}
                </FormCard>
            </div>
            <AlertDialog open={replacementOpen} onOpenChange={setReplacementOpen}>
                <AlertDialogContent>
                    <AlertDialogTitle>Ganti draft {target?.label}?</AlertDialogTitle>
                    <AlertDialogDescription>
                        Konfigurasi dan DOCX draft akan diganti oleh paket ini. Bukti validasi, uji, dan konfirmasi lama
                        dibatalkan. Perubahan yang terjadi setelah pemeriksaan akan memblokir penyimpanan.
                    </AlertDialogDescription>
                    <AlertDialogFooter>
                        <AlertDialogCancel render={<Button variant="outline" />}>Batal</AlertDialogCancel>
                        <Button
                            variant="destructive"
                            onClick={() => {
                                form.setData('replace_draft', true);
                                setReplacementOpen(false);
                            }}
                        >
                            Konfirmasi Penggantian
                        </Button>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}
LetterTemplateExchange.layout = { pageName };
