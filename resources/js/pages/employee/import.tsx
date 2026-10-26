import { useState, type SubmitEventHandler } from 'react';
import { Head, Link, useHttp } from '@inertiajs/react';
import { FormCard } from '@/components/form-card';
import { Button } from '@/components/ui/button';
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
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

export default function EmployeeImport({ hasJobTitles }: { hasJobTitles: boolean }) {
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
                    {!hasJobTitles && (
                        <Empty>
                            <EmptyHeader>
                                <EmptyTitle>Master jabatan belum tersedia</EmptyTitle>
                                <EmptyDescription>
                                    Tambahkan minimal satu jabatan sebelum mengunduh template dan mengimpor pegawai.
                                </EmptyDescription>
                            </EmptyHeader>
                            <Button nativeButton={false} render={<Link href={jt.create().url} />}>
                                Tambah Jabatan
                            </Button>
                        </Empty>
                    )}
                    <form onSubmit={handleSubmit} noValidate aria-busy={http.processing}>
                        <FieldGroup>
                            <Field>
                                <FieldLabel>Template pegawai</FieldLabel>
                                <FieldDescription>
                                    Pilih jabatan dan status dari dropdown Excel. Simpan NIK/NIP sebagai teks agar digit
                                    tetap utuh. Unduh ulang template setelah master jabatan berubah.
                                </FieldDescription>
                                <div>
                                    {hasJobTitles ? (
                                        <Button
                                            variant="secondary"
                                            nativeButton={false}
                                            disabled={http.processing}
                                            render={<a href={employeeImport.template().url} download />}
                                        >
                                            Unduh Template Excel
                                        </Button>
                                    ) : (
                                        <Button variant="secondary" disabled>
                                            Unduh Template Excel
                                        </Button>
                                    )}
                                </div>
                            </Field>
                            <Field data-invalid={!!http.errors.file} data-disabled={!hasJobTitles || http.processing}>
                                <FieldLabel htmlFor="employee-file">File Excel</FieldLabel>
                                <Input
                                    id="employee-file"
                                    name="file"
                                    type="file"
                                    accept=".xlsx"
                                    disabled={!hasJobTitles || http.processing}
                                    aria-invalid={!!http.errors.file}
                                    aria-describedby="employee-file-help employee-file-error"
                                    onChange={(event) => {
                                        http.setData('file', event.target.files?.[0] ?? null);
                                        http.clearErrors();
                                        setRequestError('');
                                    }}
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
                                    {http.processing ? 'Memproses Impor…' : 'Impor Pegawai'}
                                </Button>
                                <Button
                                    variant="secondary"
                                    nativeButton={false}
                                    disabled={http.processing}
                                    render={<Link href={empl.index().url} />}
                                >
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
