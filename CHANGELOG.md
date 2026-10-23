# Catatan Perubahan

## [Belum Dirilis]

### Ditambahkan

- Konfigurasi `pint.json` menyelaraskan aturan format PHP dengan Prettier, termasuk spasi penggabungan string, arrow function, constructor, dan anonymous class, agar kedua formatter tidak saling mengubah hasil.
- CRUD Jabatan dengan pencarian nama, paginasi, formulir tambah/ubah, konfirmasi hapus, dan navigasi melalui route terautentikasi `jt.*` pada URL `job-title`.
- Migration `2026_10_23_114205_add_uuid_to_job_titles_table.php` menambahkan UUID berindex, mengisi UUID untuk data lama, dan menetapkan kolom UUID sebagai NOT NULL pada SQLite. UUID hanya dipakai untuk URL; primary key tetap id numerik auto-increment untuk relasi.
- Pengujian Jabatan untuk autentikasi, pencarian literal, paginasi, validasi, persistensi, kontrak Resource, seeder, serta penolakan id numerik dan UUID yang tidak dikenal.

### Diubah

- Resource Jabatan mengekspos UUID sebagai `id` menggantikan id numerik, dengan informasi kelompok yang aman saat bernilai null.
- Kelompok Jabatan wajib diisi pada formulir dan validasi, sementara kolom database tetap nullable. Penghapusan Jabatan bersifat permanen dan terpusat pada satu pemanggilan `delete()` di controller, tanpa arsip atau SoftDeletes.

### 2026-10-23 — Tabel data yang dapat digunakan kembali

#### Perubahan

- Memindahkan komponen tampilan tabel penomoran surat, konfigurasi fitur tabel, dan bagian navigasi halaman ke `resources/js/components/data-table` agar dapat digunakan kembali di berbagai fitur.
- Menambahkan `useDataTable` dan `createDataTableColumnHelper` dengan konfigurasi TanStack Table v9 bersama untuk penyaringan, pengurutan, paginasi, visibilitas kolom, dan pemilihan baris. Setiap fitur menyediakan kontrol antarmukanya sendiri untuk kemampuan tersebut.
- Memindahkan pengaturan lebar kolom, perataan teks, dan tampilan skeleton ke metadata kolom bertipe: `meta.className` dan `meta.skeletonClassName`.
- Memungkinkan pengaturan status pemuatan, konten saat data kosong, dan konten bagian bawah tabel melalui props `DataTable`.
- Mengganti permintaan Inertia di bagian navigasi halaman dengan callback `onPaginationChange`. Pilihan jumlah data per halaman dan status nonaktif dapat diatur.
- Mempertahankan parameter pencarian, paginasi server, skeleton pemuatan, serta aksi ubah/hapus pada penomoran surat melalui komponen pembungkus khusus fitur tersebut. Menambahkan tipe props halaman indeks secara eksplisit dan ID baris yang stabil.
- Menambahkan rentang kolom untuk header berkelompok, rentang sel saat data kosong berdasarkan kolom yang terlihat, serta penomoran baris yang reaktif. Memperbaiki ikon navigasi halaman berikutnya dan terakhir.
- Menambahkan sembilan pengujian regresi tampilan serta konfigurasi Vitest terpisah menggunakan Vite Plus yang sudah tersedia.

#### Penggunaan Terbaru

Impor API bersama dari `@/components/data-table`. Simpan tipe data baris, definisi kolom, rute, dan aksi bisnis di masing-masing fitur. Lihat [komponen pembungkus penomoran surat](resources/js/pages/letter-number/components/data-table/index.tsx) dan [definisi kolomnya](resources/js/pages/letter-number/components/data-table/column.tsx) sebagai contoh integrasi Inertia yang sudah digunakan.

Untuk fitur dengan paginasi server, definisikan kolom di luar komponen agar referensinya stabil, lalu gunakan metadata server untuk mengatur status tabel:

```tsx
import {
    DataTable,
    DataTableFooter,
    createDataTableColumnHelper,
    useDataTable,
    type PaginationMeta,
    type PaginationParams,
} from '@/components/data-table';

type FeatureRow = { id: string; name: string };

const columnHelper = createDataTableColumnHelper<FeatureRow>();
const columns = columnHelper.columns([
    columnHelper.accessor('name', {
        header: 'Nama',
        meta: { className: 'text-left', skeletonClassName: 'w-40' },
    }),
]);

interface FeatureTableProps {
    data: FeatureRow[];
    meta: PaginationMeta;
    isLoading?: boolean;
    onPaginationChange: (params: PaginationParams) => void;
}

export function FeatureTable({ data, meta, isLoading = false, onPaginationChange }: FeatureTableProps) {
    const table = useDataTable({
        data,
        columns,
        getRowId: (row) => row.id,
        manualPagination: true,
        rowCount: meta.total,
        state: {
            pagination: { pageIndex: meta.current_page - 1, pageSize: meta.per_page },
        },
    });

    return (
        <DataTable
            table={table}
            isLoading={isLoading}
            emptyMessage="Tidak ada data yang ditemukan."
            footer={<DataTableFooter meta={meta} onPaginationChange={onPaginationChange} disabled={isLoading} />}
        />
    );
}
```

- `PaginationMeta` berisi `current_page`, `from`, `to`, `last_page`, `per_page`, dan `total`. Nomor halaman server dimulai dari satu, sedangkan indeks halaman tabel dimulai dari nol.
- `onPaginationChange` menerima `{ page }` untuk berpindah halaman atau `{ page: 1, per_page }` untuk mengubah jumlah data per halaman. Fitur mengirim permintaan melalui rute Wayfinder miliknya, mempertahankan parameter kueri yang relevan, dan memperbarui props Inertia miliknya. Komponen navigasi bersama tidak bergantung pada rute atau nama prop tertentu.
- Nilai awal `isLoading` adalah `false`. Fitur Inertia dapat memanggil `useTableLoading('resources')` dengan nama prop data yang digunakan, lalu meneruskan hasilnya. Skeleton pemuatan mengikuti kolom yang terlihat dan dibatasi hingga sepuluh baris.
- `emptyMessage` menerima konten React. Prop `footer` bersifat opsional. Nilai awal `DataTableFooter.pageSizeOptions` adalah `[5, 10, 15, 20, 50, 100]`; pilihan khusus harus sesuai dengan nilai yang diterima backend.
- Untuk paginasi di sisi klien, teruskan seluruh data ke `useDataTable`, jangan sertakan `manualPagination` maupun status paginasi yang dikendalikan server, dan atur `initialState.pagination` bila diperlukan. Hubungkan kontrol navigasi ke metode pengatur paginasi tabel; hitung metadata dari status tabel jika menggunakan `DataTableFooter`.
- Pertahankan referensi data dan kolom selama isinya tidak berubah. Gunakan langganan seperti `Subscribe` dengan `table.atoms.pagination` saat sel membaca status paginasi, sebagaimana diterapkan pada kolom nomor baris penomoran surat.

Jalankan pengujian regresi tabel bersama dengan perintah berikut:

```sh
pnpm exec vp test resources/js/components/data-table/index.test.jsx
```
