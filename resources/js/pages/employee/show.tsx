import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty';
import { Separator } from '@/components/ui/separator';
import { getInitialName } from '@/lib/utils';
import empl from '@/routes/empl';
import type { EmployeeResource } from '@/schemas/employee';
import { ArrowLeftIcon, Edit04Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Head, Link } from '@inertiajs/react';

const pageName = 'Detail Pegawai';
interface EmployeeShowProps {
    resource: { data: EmployeeResource };
}
type Detail = [label: string, value: string | number | null];
function Details({ items }: { items: Detail[] }) {
    return (
        <dl className="grid gap-5 sm:grid-cols-2">
            {items.map(([label, value]) => (
                <div key={label} className="flex min-w-0 flex-col gap-1">
                    <dt className="text-sm text-muted-foreground">{label}</dt>
                    <dd className="font-medium wrap-anywhere">{value === '' || value == null ? '—' : value}</dd>
                </div>
            ))}
        </dl>
    );
}
function MissingRegistration({ title }: { title: 'STR' | 'SIP' }) {
    return (
        <Empty className="p-4 md:p-4">
            <EmptyHeader>
                <EmptyTitle>Belum ada data {title}</EmptyTitle>
                <EmptyDescription>Data {title} dapat dilengkapi melalui formulir ubah pegawai.</EmptyDescription>
            </EmptyHeader>
        </Empty>
    );
}
export default function EmployeeShow({ resource: { data: employee } }: EmployeeShowProps) {
    const personal: Detail[] = [
        ['NIK', employee.nik],
        ['NIP', employee.nip],
        ['Nama', employee.name.raw],
        ['Gelar Depan', employee.name.first],
        ['Gelar Belakang', employee.name.last],
        ['Jenis Kelamin', employee.gender.label],
        ['Email', employee.email],
    ];
    const work: Detail[] = [
        ['Jabatan', employee.job_title.name],
        ['Kelompok Jabatan', employee.job_title.group.label],
        ['Pangkat', employee.rank],
        ['Golongan', employee.grade],
        ['Tanggal Bergabung', employee.join_date],
        ['Masa Kerja', employee.tenure_years === null ? null : `${employee.tenure_years} tahun`],
        ['Status Kontrak', employee.contract_status.label],
        ['Status Pegawai', employee.status.label],
    ];
    return (
        <>
            <Head title={pageName}>
                <meta name="description" content="Halaman detail identitas, pekerjaan, STR, dan SIP pegawai" />
            </Head>
            <div className="container mx-auto flex min-w-0 flex-col gap-6">
                <Card>
                    <CardHeader className="flex flex-col gap-4 sm:flex-row sm:items-center">
                        <Avatar className="size-16 shrink-0">
                            <AvatarFallback>{getInitialName(employee.name.raw)}</AvatarFallback>
                        </Avatar>
                        <div className="flex min-w-0 flex-col gap-2">
                            <CardTitle className="wrap-anywhere">{employee.name.full}</CardTitle>
                            <CardDescription className="wrap-anywhere">
                                {employee.job_title.name ?? '—'} · {employee.job_title.group.label ?? '—'}
                            </CardDescription>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-wrap gap-2">
                            <Badge variant="secondary">Status Pegawai: {employee.status.label ?? '—'}</Badge>
                            <Badge variant="outline">Status Kontrak: {employee.contract_status.label ?? '—'}</Badge>
                        </div>
                    </CardContent>
                    <Separator />
                    <CardFooter className="flex flex-wrap gap-3">
                        <Button
                            variant="warning"
                            nativeButton={false}
                            render={<Link href={empl.edit(employee.id).url} />}
                        >
                            <HugeiconsIcon icon={Edit04Icon} data-icon="inline-start" />
                            Ubah
                        </Button>
                        <Button variant="outline" nativeButton={false} render={<Link href={empl.index().url} />}>
                            <HugeiconsIcon icon={ArrowLeftIcon} data-icon="inline-start" />
                            Kembali ke daftar
                        </Button>
                    </CardFooter>
                </Card>
                <div className="grid min-w-0 gap-6 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Data Diri</CardTitle>
                            <CardDescription>Identitas dan kontak pegawai.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Details items={personal} />
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Data Pekerjaan</CardTitle>
                            <CardDescription>Jabatan dan informasi kepegawaian.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Details items={work} />
                        </CardContent>
                    </Card>
                </div>
                <Card>
                    <CardHeader>
                        <CardTitle>Data Lainnya</CardTitle>
                        <CardDescription>Registrasi dan izin praktik pegawai.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 lg:grid-cols-2">
                        <Card size="sm">
                            <CardHeader>
                                <CardTitle>STR</CardTitle>
                                <CardDescription>Surat Tanda Registrasi.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                {employee.str ? (
                                    <Details
                                        items={[
                                            ['Nomor STR', employee.str.number],
                                            ['Status STR', employee.str.status.label],
                                            ['Tanggal Terbit', employee.str.published_date],
                                        ]}
                                    />
                                ) : (
                                    <MissingRegistration title="STR" />
                                )}
                            </CardContent>
                        </Card>
                        <Card size="sm">
                            <CardHeader>
                                <CardTitle>SIP</CardTitle>
                                <CardDescription>Surat Izin Praktik.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                {employee.sip ? (
                                    <Details
                                        items={[
                                            ['Nomor SIP', employee.sip.number],
                                            ['Tanggal Terbit', employee.sip.published_date],
                                            ['Tanggal Berakhir', employee.sip.expired_date],
                                        ]}
                                    />
                                ) : (
                                    <MissingRegistration title="SIP" />
                                )}
                            </CardContent>
                        </Card>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
EmployeeShow.layout = { pageName };
