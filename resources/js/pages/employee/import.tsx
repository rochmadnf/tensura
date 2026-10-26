import { useState, type SubmitEventHandler } from 'react';
import { Head, Link, useHttp } from '@inertiajs/react';
import { FormCard } from '@/components/form-card';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription, AlertTitle } from '@/components/reui/alert';
import { HugeiconsIcon } from '@hugeicons/react';
import {
    AlertCircleIcon,
    ArrowLeft01Icon,
    InformationCircleIcon,
    LoaderIcon,
    PlusSignIcon,
    Upload04Icon,
} from '@hugeicons/core-free-icons';
import { EmployeeExcelUpload } from './components/employee-excel-upload';
import { EmployeeImportHelp } from './components/employee-import-help';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import empl from '@/routes/empl';
import employeeImport from '@/routes/empl/import';
import jt from '@/routes/jt';

type ImportResult = {
    total: number;
    processed: number;
    imported: number;
    duplicate: number;
    invalid: number;
    interrupted: boolean;
    issues: { row: number; name: string; category: 'duplicate' | 'invalid'; errors: Record<string, string> }[];
};

const pageName = 'Impor Pegawai';
const pageSize = 10;

export default function EmployeeImport({
    hasJobTitles,
    instructions,
}: {
    hasJobTitles: boolean;
    instructions: string[];
}) {
    const http = useHttp<{ file: File | null }, ImportResult>({ file: null });
    const [result, setResult] = useState<ImportResult | null>(null);
    const [requestError, setRequestError] = useState('');
    const [issuePage, setIssuePage] = useState(1);
    const pages = Math.max(1, Math.ceil((result?.issues.length ?? 0) / pageSize));

    const handleSubmit: SubmitEventHandler<HTMLFormElement> = async (event) => {
        event.preventDefault();
        if (http.processing || !hasJobTitles) return;
        http.clearErrors();
        setRequestError('');
        setResult(null);
        setIssuePage(1);
        if (!http.data.file) {
            http.setError('file', 'Pilih file Excel yang akan diimpor.');
            return;
        }
        if (!http.data.file.name.toLowerCase().endsWith('.xlsx')) {
            http.setError('file', 'Gunakan file Excel berformat .xlsx.');
            return;
        }
        if (http.data.file.size > 2 * 1024 * 1024) {
            http.setError('file', 'Ukuran file maksimal 2 MB.');
            return;
        }
        try {
            const report = await http.post(employeeImport.store().url);
            if (report) setResult(report);
        } catch {
            setRequestError(
                'Permintaan impor gagal. Periksa pesan file di bawah. Jika koneksi terputus atau server bermasalah, sebagian data mungkin sudah tersimpan. Periksa daftar pegawai atau unggah ulang; data yang sudah masuk akan dilewati.',
            );
        }
    };

    return (
        <>
            <Head title={pageName} />
            <div className="container mx-auto flex min-w-0 flex-col gap-6">
                <FormCard
                    title={pageName}
                    description="Tambahkan pegawai dari template Excel. Data yang sudah tersedia tidak diperbarui."
                    cancelUrl={empl.index().url}
                    disabled={http.processing}
                >
                    <form onSubmit={handleSubmit} noValidate aria-busy={http.processing}>
                        <FieldGroup>
                            {!hasJobTitles && (
                                <Alert variant="destructive">
                                    <HugeiconsIcon icon={AlertCircleIcon} aria-hidden="true" />
                                    <AlertTitle>Master jabatan belum tersedia</AlertTitle>
                                    <AlertDescription>
                                        Template dan impor belum dapat digunakan. Tambahkan minimal satu jabatan
                                        terlebih dahulu.
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            nativeButton={false}
                                            render={<Link href={jt.create().url} />}
                                        >
                                            <HugeiconsIcon
                                                icon={PlusSignIcon}
                                                data-icon="inline-start"
                                                aria-hidden="true"
                                            />
                                            Tambah Jabatan
                                        </Button>
                                    </AlertDescription>
                                </Alert>
                            )}
                            <Field>
                                <FieldLabel>Template Pegawai</FieldLabel>
                                <Alert variant="info" role="note">
                                    <HugeiconsIcon icon={InformationCircleIcon} aria-hidden="true" />
                                    <AlertDescription>
                                        Pilih jabatan dan status dari dropdown Excel. Simpan NIK/NIP sebagai teks agar
                                        digit tetap utuh. Unduh ulang template setelah master jabatan berubah.
                                    </AlertDescription>
                                </Alert>
                                <EmployeeImportHelp
                                    instructions={instructions}
                                    hasJobTitles={hasJobTitles}
                                    processing={http.processing}
                                />
                            </Field>
                            <Field data-invalid={!!http.errors.file} data-disabled={!hasJobTitles || http.processing}>
                                <FieldLabel id="employee-file-label" htmlFor="employee-file">
                                    File Excel
                                </FieldLabel>
                                <EmployeeExcelUpload
                                    disabled={!hasJobTitles || http.processing}
                                    invalid={!!http.errors.file}
                                    onChange={(file) => {
                                        http.setData('file', file);
                                        http.clearErrors();
                                        setRequestError('');
                                    }}
                                    onError={(message) => http.setError('file', message)}
                                />
                                <FieldDescription id="employee-file-help">
                                    Maksimal 2 MB dan 1.000 baris pengisian (baris Excel 2–1001). Baris valid disimpan;
                                    duplikat dan baris tidak valid dilaporkan untuk diperbaiki.
                                </FieldDescription>
                                <div id="employee-file-error" aria-live="polite">
                                    {http.errors.file && <FieldError>{http.errors.file}</FieldError>}
                                </div>
                            </Field>
                            {requestError && (
                                <p role="alert" className="text-sm text-destructive">
                                    {requestError}
                                </p>
                            )}
                            <div className="flex flex-wrap items-center gap-3">
                                <Button type="submit" disabled={!hasJobTitles || http.processing}>
                                    <HugeiconsIcon
                                        icon={http.processing ? LoaderIcon : Upload04Icon}
                                        data-icon="inline-start"
                                        aria-hidden="true"
                                        className={http.processing ? 'animate-spin' : undefined}
                                    />
                                    {http.processing ? 'Memproses Impor…' : 'Impor Pegawai'}
                                </Button>
                                <Button
                                    variant="secondary"
                                    nativeButton={false}
                                    disabled={http.processing}
                                    render={<Link href={empl.index().url} />}
                                >
                                    <HugeiconsIcon icon={ArrowLeft01Icon} data-icon="inline-start" aria-hidden="true" />
                                    Kembali ke Pegawai
                                </Button>
                            </div>
                            {http.processing && (
                                <p role="status" className="text-sm text-muted-foreground">
                                    {http.progress?.percentage !== undefined && http.progress.percentage < 100
                                        ? `Mengunggah file ${http.progress.percentage}%…`
                                        : 'Memeriksa dan menyimpan pegawai. Tunggu hingga laporan tampil.'}
                                </p>
                            )}
                        </FieldGroup>
                    </form>
                </FormCard>
                {result && (
                    <FormCard
                        title="Hasil Impor"
                        description="Laporan tersedia selama halaman ini terbuka. Perbaiki baris yang dilewati lalu unggah ulang."
                    >
                        <div className="flex min-w-0 flex-col gap-5">
                            <p role="status">
                                {result.processed} dari {result.total} baris diproses:{' '}
                                <strong>{result.imported} berhasil</strong>, {result.duplicate} duplikat,{' '}
                                {result.invalid} tidak valid.
                            </p>
                            {result.interrupted && (
                                <p role="alert" className="text-sm text-destructive">
                                    Proses terhenti karena gangguan server. Baris yang berhasil sudah tersimpan; baris
                                    setelahnya belum diproses. Silakan unggah ulang, data yang sudah masuk akan
                                    dilewati.
                                </p>
                            )}
                            {result.issues.length === 0 ? (
                                <p>
                                    {result.interrupted
                                        ? 'Tidak ada masalah baris sebelum proses terhenti.'
                                        : 'Seluruh baris berhasil diimpor.'}
                                </p>
                            ) : (
                                <>
                                    <Table>
                                        <TableCaption>Rincian pegawai yang dilewati</TableCaption>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead className="text-right">Baris Excel</TableHead>
                                                <TableHead>Nama</TableHead>
                                                <TableHead>Hasil</TableHead>
                                                <TableHead>Alasan</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {result.issues
                                                .slice((issuePage - 1) * pageSize, issuePage * pageSize)
                                                .map((issue) => (
                                                    <TableRow key={issue.row}>
                                                        <TableCell className="text-right align-top tabular-nums">
                                                            {issue.row}
                                                        </TableCell>
                                                        <TableCell className="max-w-56 align-top wrap-break-word whitespace-normal">
                                                            {issue.name || 'Tanpa nama'}
                                                        </TableCell>
                                                        <TableCell className="align-top">
                                                            {issue.category === 'duplicate'
                                                                ? 'Duplikat'
                                                                : 'Tidak valid'}
                                                        </TableCell>
                                                        <TableCell className="min-w-64 wrap-break-word whitespace-normal">
                                                            <ul className="flex list-disc flex-col gap-1 pl-4">
                                                                {Object.entries(issue.errors).map(
                                                                    ([field, message]) => (
                                                                        <li key={field}>{message}</li>
                                                                    ),
                                                                )}
                                                            </ul>
                                                        </TableCell>
                                                    </TableRow>
                                                ))}
                                        </TableBody>
                                    </Table>
                                    {pages > 1 && (
                                        <nav
                                            aria-label="Halaman laporan impor"
                                            className="flex flex-wrap items-center justify-between gap-3"
                                        >
                                            <p className="text-sm text-muted-foreground" aria-live="polite">
                                                Halaman {issuePage} dari {pages}
                                            </p>
                                            <div className="flex gap-2">
                                                <Button
                                                    variant="secondary"
                                                    disabled={issuePage === 1}
                                                    onClick={() => setIssuePage(issuePage - 1)}
                                                >
                                                    Sebelumnya
                                                </Button>
                                                <Button
                                                    variant="secondary"
                                                    disabled={issuePage === pages}
                                                    onClick={() => setIssuePage(issuePage + 1)}
                                                >
                                                    Berikutnya
                                                </Button>
                                            </div>
                                        </nav>
                                    )}
                                </>
                            )}
                        </div>
                    </FormCard>
                )}
            </div>
        </>
    );
}

EmployeeImport.layout = { pageName };
