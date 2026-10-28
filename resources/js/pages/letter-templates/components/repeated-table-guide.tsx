import type { ConfigurationOptions, ConfigurationVariable } from '@/schemas/letter-template-configuration';
import { FieldDescription } from '@/components/ui/field';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { CopyPlaceholder } from './copy-placeholder';

export function RepeatedTableGuide({
    variable,
    options,
}: {
    variable: ConfigurationVariable;
    options: ConfigurationOptions;
}) {
    const start = '${blok:' + variable.name + '}';
    const end = '${/blok:' + variable.name + '}';
    const columns = variable.columns;
    return (
        <div className="flex w-[calc(100vw-5rem)] max-w-full min-w-0 flex-col gap-3 lg:w-[calc(100vw-23rem)]">
            <h4 className="text-sm font-medium">Cara menyusun tabel berulang di DOCX</h4>
            <FieldDescription>
                Horizontal: satu pilihan menempati satu baris. Letakkan semua placeholder kelompok pada baris yang sama.
                Baris tersebut akan diulang; penanda blok tidak diperlukan.
            </FieldDescription>
            <Table>
                <TableHeader>
                    <TableRow>
                        {columns.map((column, i) => (
                            <TableHead key={i}>
                                {options.columns[variable.option_source ?? 0]?.find(
                                    (item) => item.id === column.source_attribute,
                                )?.label ?? column.source_attribute}
                            </TableHead>
                        ))}
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow>
                        {columns.map((column, i) => (
                            <TableCell key={i}>
                                <code>{'${' + column.placeholder + '}'}</code>
                            </TableCell>
                        ))}
                    </TableRow>
                </TableBody>
            </Table>
            <FieldDescription>
                Vertikal: satu pilihan menempati beberapa baris. Pasang penanda awal sebelum isi baris pertama, lalu
                penanda akhir setelah isi baris terakhir. Semua baris dalam blok diulang sebagai satu kesatuan.
            </FieldDescription>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Label</TableHead>
                        <TableHead>Pemisah</TableHead>
                        <TableHead>Nilai</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {columns.map((column, i) => (
                        <TableRow key={i}>
                            <TableCell>
                                {i === 0 && <code>{start}</code>}
                                {options.columns[variable.option_source ?? 0]?.find(
                                    (item) => item.id === column.source_attribute,
                                )?.label ?? column.source_attribute}
                            </TableCell>
                            <TableCell>:</TableCell>
                            <TableCell>
                                <code>{'${' + column.placeholder + '}'}</code>
                            </TableCell>
                        </TableRow>
                    ))}
                    <TableRow>
                        <TableCell>
                            <code>{end}</code>
                        </TableCell>
                        <TableCell />
                        <TableCell />
                    </TableRow>
                </TableBody>
            </Table>
            <CopyPlaceholder name={'blok:' + variable.name} label="Salin penanda awal" />
            <CopyPlaceholder name={'/blok:' + variable.name} label="Salin penanda akhir" />
            <FieldDescription>
                Penanda tidak muncul pada hasil. Baris penutup yang hanya berisi penanda dan spasi menjadi pemisah
                antarorang dan dihapus pada orang terakhir. Jika penutup berada bersama data, baris tetap dipertahankan.
                Atur tinggi baris dan jarak di Word. Nomor urut hanya muncul jika Anda menambahkan atribut row_number.
                Gunakan satu pasangan per kelompok dalam satu tabel, tanpa tabel bersarang atau gabungan sel vertikal.
                Unduhan contoh mengikuti konfigurasi yang sudah disimpan.
            </FieldDescription>
        </div>
    );
}
