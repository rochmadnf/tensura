import { useState, type SubmitEventHandler } from 'react';
import { Head, Link, useHttp } from '@inertiajs/react';
import { FormCard } from '@/components/form-card';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription, AlertTitle } from '@/components/reui/alert';
import { HugeiconsIcon } from '@hugeicons/react';
import {
    AlertCircleIcon,
    ArrowDown01Icon,
    ArrowLeft01Icon,
    BadgeXIcon,
    Copy02Icon,
    IdVerifiedIcon,
    LoaderIcon,
    PlusSignIcon,
    Upload01Icon,
    WorkflowIcon,
} from '@hugeicons/core-free-icons';
import { Frame, FramePanel } from '@/components/reui/frame';
import { IconTile } from '@/components/reui/icon-tile';
import { EmployeeExcelUpload } from './components/employee-excel-upload';
import { EmployeeImportHelp } from './components/employee-import-help';
import { Field, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Badge } from '@/components/reui/badge';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Item, ItemActions, ItemContent, ItemDescription, ItemGroup, ItemMedia, ItemTitle } from '@/components/ui/item';
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
        if (http.data.file.size > 5 * 1024 * 1024) {
            http.setError('file', 'Ukuran file maksimal 5 MB.');
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
                                        icon={http.processing ? LoaderIcon : Upload01Icon}
                                        data-icon="inline-start"
                                        aria-hidden="true"
                                        className={http.processing ? 'animate-spin' : undefined}
                                    />
                                    {http.processing ? 'Memproses Impor…' : 'Impor'}
                                </Button>
                                <Button
                                    variant="secondary"
                                    nativeButton={false}
                                    disabled={http.processing}
                                    render={<Link href={empl.index().url} />}
                                >
                                    <HugeiconsIcon icon={ArrowLeft01Icon} data-icon="inline-start" aria-hidden="true" />
                                    Kembali
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
                        <div className="flex min-w-0 flex-col gap-8">
                            <div className="flex flex-wrap gap-3">
                                {[
                                    {
                                        label: 'Diproses',
                                        count: `${result.processed} / ${result.total}`,
                                        icon: WorkflowIcon,
                                        className: 'text-primary',
                                    },
                                    {
                                        label: 'Berhasil',
                                        count: result.imported,
                                        icon: IdVerifiedIcon,
                                        className: 'text-emerald-500',
                                    },
                                    {
                                        label: 'Duplikat',
                                        count: result.duplicate,
                                        icon: Copy02Icon,
                                        className: 'text-yellow-500',
                                    },
                                    {
                                        label: 'Tidak Valid',
                                        count: result.invalid,
                                        icon: BadgeXIcon,
                                        className: 'text-destructive',
                                    },
                                ].map((summary) => (
                                    <Frame variant={'ghost'} key={summary.label} className="w-fit">
                                        <FramePanel className="flex flex-row items-center justify-center gap-3">
                                            <IconTile
                                                variant="soft"
                                                size="lg"
                                                className={summary.className}
                                                aria-hidden="true"
                                            >
                                                <HugeiconsIcon icon={summary.icon} strokeWidth={2} />
                                            </IconTile>
                                            <div className="flex flex-col gap-x-2">
                                                <div className="text-xl font-bold tracking-tight text-foreground">
                                                    {summary.count}
                                                </div>
                                                <p className="text-sm">{summary.label}</p>
                                            </div>
                                        </FramePanel>
                                    </Frame>
                                ))}
                            </div>
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
                                    <ItemGroup aria-label="Rincian pegawai yang dilewati">
                                        {result.issues
                                            .slice((issuePage - 1) * pageSize, issuePage * pageSize)
                                            .map((issue) => (
                                                <Collapsible key={issue.row} role="listitem" defaultOpen={false}>
                                                    <CollapsibleTrigger
                                                        nativeButton={false}
                                                        render={
                                                            <Item
                                                                variant="outline"
                                                                size="xs"
                                                                className="group cursor-pointer text-left hover:bg-muted/50"
                                                            />
                                                        }
                                                    >
                                                        <ItemMedia variant="icon">
                                                            <HugeiconsIcon
                                                                icon={
                                                                    issue.category === 'duplicate'
                                                                        ? Copy02Icon
                                                                        : BadgeXIcon
                                                                }
                                                                className={
                                                                    issue.category === 'duplicate'
                                                                        ? 'text-yellow-500'
                                                                        : 'text-destructive'
                                                                }
                                                                strokeWidth={2}
                                                                aria-hidden="true"
                                                            />
                                                        </ItemMedia>
                                                        <ItemContent className="min-w-0">
                                                            <ItemTitle className="max-w-full wrap-break-word">
                                                                {issue.name || 'Tanpa nama'}
                                                            </ItemTitle>
                                                            <ItemDescription>Baris ke-{issue.row}</ItemDescription>
                                                        </ItemContent>
                                                        <ItemActions>
                                                            <Badge
                                                                variant={
                                                                    issue.category === 'duplicate'
                                                                        ? 'warning-light'
                                                                        : 'destructive-light'
                                                                }
                                                                size="sm"
                                                            >
                                                                {issue.category === 'duplicate'
                                                                    ? 'Duplikat'
                                                                    : 'Tidak Valid'}
                                                            </Badge>
                                                            <HugeiconsIcon
                                                                icon={ArrowDown01Icon}
                                                                aria-hidden="true"
                                                                className="size-4 group-data-panel-open:rotate-180"
                                                            />
                                                        </ItemActions>
                                                    </CollapsibleTrigger>
                                                    <CollapsibleContent className="px-3 py-3 text-sm">
                                                        <p className="mb-2 font-medium">Alasan</p>
                                                        <ul className="flex list-disc flex-col gap-1 pl-4 wrap-break-word">
                                                            {Object.entries(issue.errors).map(([field, message]) => (
                                                                <li key={field}>{message}</li>
                                                            ))}
                                                        </ul>
                                                    </CollapsibleContent>
                                                </Collapsible>
                                            ))}
                                    </ItemGroup>
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
