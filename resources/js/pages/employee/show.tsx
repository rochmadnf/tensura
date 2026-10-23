import { Button } from '@/components/ui/button';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import empl from '@/routes/empl';
import type { EmployeeResource } from '@/schemas/employee';
import { Edit04Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Head, Link } from '@inertiajs/react';

const pageName = 'Detail Pegawai';
interface EmployeeShowProps {
    resource: { data: EmployeeResource };
}

export default function EmployeeShow({ resource: { data: employee } }: EmployeeShowProps) {
    const details = [
        ['NIK', employee.nik],
        ['NIP', employee.nip],
        ['Nama', employee.name.raw],
        ['Gelar Depan', employee.name.first],
        ['Gelar Belakang', employee.name.last],
        ['Jabatan', employee.job_title.name],
        ['Pangkat', employee.rank],
        ['Golongan', employee.grade],
        ['Tanggal Bergabung', employee.join_date],
        ['Masa Kerja', `${employee.tenure_years} tahun`],
        ['Email', employee.email],
        ['Status Kontrak', employee.contract_status.label],
        ['Status Pegawai', employee.status.label],
    ];
    return (
        <>
            <Head title={pageName}>
                <meta name="description" content="Halaman detail identitas dan kepegawaian" />
            </Head>
            <div className="container mx-auto">
                <Card>
                    <CardHeader>
                        <CardTitle>{employee.name.full}</CardTitle>
                        <CardDescription>Informasi lengkap pegawai.</CardDescription>
                        <CardAction>
                            <Button
                                variant="warning"
                                nativeButton={false}
                                render={<Link href={empl.edit(employee.id).url} />}
                            >
                                <HugeiconsIcon icon={Edit04Icon} data-icon="inline-start" strokeWidth={2} />
                                Ubah
                            </Button>
                        </CardAction>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-6">
                        <dl className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            {details.map(([label, value]) => (
                                <div key={label} className="flex min-w-0 flex-col gap-1">
                                    <dt className="text-sm text-muted-foreground">{label}</dt>
                                    <dd className="font-medium wrap-anywhere">{value || '—'}</dd>
                                </div>
                            ))}
                        </dl>
                        <Button
                            variant="outline"
                            nativeButton={false}
                            className="self-start"
                            render={<Link href={empl.index().url} />}
                        >
                            Kembali ke daftar
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
EmployeeShow.layout = { pageName };
