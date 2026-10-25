# Catatan Perubahan

## [Belum Dirilis]

### Ditambahkan

- Publikasi Templat Surat setelah pemeriksaan sumber, konfigurasi, hasil uji terbaru yang belum kedaluwarsa, konfirmasi visual, dan seluruh kandidat penandatangan aktif; pointer versi terbit aktif diperbarui tanpa menghapus publikasi terdahulu.
- Pembukaan kembali publikasi yang belum digunakan sebagai draft dengan nomor yang sama, serta penyalinan versi yang sudah digunakan atau diarsipkan menjadi draft bernomor baru. Seluruh konfigurasi/fallback/format disalin mandiri dan sumber DOCX memakai berkas terpisah; bukti pemeriksaan wajib dibuat ulang.
- Arsip draft sebagai status versi serta aktif/nonaktif templat dengan konfirmasi dan audit, tanpa hapus permanen atau integrasi Arsip pusat. Publikasi mempertahankan status Nonaktif hingga admin mengaktifkannya secara terpisah.
- Kartu kesiapan Publikasi dan Status, pemilihan versi historis melalui Timeline Versi, dan 50 aktivitas terbaru. Identitas/klasifikasi/konfigurasi/DOCX mengikuti versi terpilih dan versi historis tetap hanya-baca walaupun templat memiliki draft lain.
- Perlindungan satu draft berjalan, penolakan token versi/hasil yang usang, serta rollback database dan pembersihan berkas pada kegagalan salinan. Dialog serta navigasi versi menghormati editor aktif agar perubahan lokal tidak hilang.

- Generate DOCX hasil uji dari data contoh sesuai token yang dipakai, termasuk fallback, tanggal, seluruh atribut Pegawai, penandatangan berbeda per slot, header/footer, dan tabel berulang dengan urutan pemilihan admin.
- Unduhan hasil uji privat dan konfirmasi pemeriksaan visual yang terikat pada hasil terbaru, hash sumber/konfigurasi/hasil, serta masa berlaku 24 jam. Generate ulang menghapus konfirmasi lama; hasil usang, hilang, berubah, kedaluwarsa, atau memakai penandatangan tidak aktif ditolak.
- Batas isian, jumlah baris, ekspansi XML, ukuran hasil, serta pemeriksaan batas waktu generate dipusatkan dalam konfigurasi. Penyimpanan gagal mempertahankan bukti dan berkas sebelumnya serta membersihkan hasil baru.
- Kartu Generate dan Pemeriksaan DOCX menampilkan isian yang diperlukan saja, urutan baris pilihan, unduhan hasil, dan pernyataan pemeriksaan visual. Tahap publikasi dan cleanup terjadwal tetap mengikuti tahap berikutnya.
- Form data contoh dibentuk ulang setelah validasi pertama berhasil agar daftar isian baru, termasuk multiple, tersedia tanpa memuat ulang halaman; generate ulang hasil tetap mempertahankan isian contoh.

- Katalog bersama 25 atribut Pegawai untuk variabel tunggal, kolom tabel berulang, dan penandatangan; token atribut otomatis serta alias nama lengkap tetap tersedia. Pemetaan mencakup pangkat, golongan, masa kerja sampai hari, status, serta STR/SIP.
- Fallback teks literal variabel tunggal/per atribut Pegawai dan format tanggal per atribut/kolom, disimpan relasional per versi. Format default `d-m-Y`, nama bulan/hari mengikuti locale aplikasi, dan input menyediakan bantuan pola PHP serta contoh dari backend.
- Fingerprint konfigurasi mencakup fallback, format, locale, dan revisi kontrak katalog agar bukti lama harus diperiksa ulang tanpa menghapus DOCX sumber. Payload konfigurasi lama tetap dapat disimpan tanpa kehilangan pengaturan tambahan.

- Upload/penggantian DOCX sumber privat per draft, unduhan terautentikasi, metadata ukuran/nama/SHA-256, dan pembersihan berkas gagal tanpa menimpa sumber versi historis.
- Validasi struktur ZIP/XML dan placeholder DOCX, termasuk text run terpecah, header/footer, token atribut penandatangan opsional, dan baris tabel berulang. Bukti validasi terikat hash sumber serta konfigurasi; galat ditampilkan pada halaman kelola.
- Unduh DOCX Contoh sejak identitas draft disimpan, berdasarkan konfigurasi versi terbaru atau historis yang dipilih, dengan panduan pergantian admin dan tanpa mengambil data pribadi kandidat pegawai.
- Konfigurasi teknis DOCX terpusat pada `config/letter-templates.php`; batas upload aplikasi 10 MB, batas dekompresi/XML/rasio ZIP, serta batas placeholder. Form upload memakai batas backend dan helper Wayfinder.

- Konfigurasi Templat Surat per draft: variabel teks pendek/panjang/tanggal, combobox tunggal/multiple bersumber referensi atau pegawai, kolom tabel berulang, dan slot kandidat penandatangan dengan placeholder yang dapat disalin.
- Validasi benturan placeholder, kepemilikan UUID variabel/kolom/slot, dan kandidat pegawai aktif diperiksa saat penyimpanan atomik. Penyimpanan membatalkan bukti pemeriksaan serta mempertahankan DOCX sumber dan versi historis.
- Peringatan nama pegawai dan slot untuk kandidat yang menjadi tidak aktif, tanpa menghapus kandidat otomatis. Form identitas dan konfigurasi saling menunggu saat menyimpan.
- Penghapusan klasifikasi/pegawai dan arsip grup referensi ditolak dengan pesan yang jelas selama masih dirujuk versi templat, termasuk versi historis atau diarsipkan. Penolakan arsip grup tidak meninggalkan snapshot; arsip item terpisah tetap tersedia.

- Identitas dan daftar Templat Surat melalui route terautentikasi `lt.*`, halaman tambah/kelola, dan sidebar existing. Draft awal hanya menyimpan nama serta klasifikasi tanpa mengubah counter penomoran.
- Pencarian literal nama/kode/nama klasifikasi, filter tunggal status/klasifikasi, dan paginasi stabil. Klasifikasi daftar mengikuti versi aktif, lalu draft berjalan, lalu versi terbaru.
- Perubahan identitas draft membatalkan bukti validasi/uji/pemeriksaan, mempertahankan DOCX sumber serta versi historis, dan mencatat audit; payload sistem dan versi milik templat lain tidak dapat mengubah target.

- Fondasi Templat Surat dengan tujuh tabel relasional, UUID unik, enum status/jenis/sumber pilihan, metadata DOCX dan bukti pemeriksaan per versi, serta factory konfigurasi.
- Audit templat mempertahankan nama dan UUID pelaku setelah akun dihapus; foreign key membatasi penghapusan konfigurasi/master historis dan memastikan versi aktif milik templat yang sama.

- Formulir Pegawai mendukung jenis kelamin (1: Laki-laki, 2: Perempuan), STR, dan SIP dengan validasi kelengkapan per kelompok, keunikan nomor, serta urutan tanggal SIP.
- Pemilih tanggal bersama dengan kalender berbahasa Indonesia dan input manual `hh-bb-tttt`; nilai yang dikirim tetap menggunakan format ISO.
- Filter kelompok jabatan dan jenis kelamin; seluruh filter kategori Pegawai memakai combobox pilihan ganda, dengan opsi jabatan mengikuti kelompok yang dipilih.
- Fitur Daftar Referensi untuk mengelola grup dan item dalam satu halaman kerja, dengan pencarian, jumlah item, paginasi, edit item langsung, dan konfirmasi hapus permanen.
- Pengurutan item melalui tombol atas/bawah serta drag dengan mouse, sentuh, dan keyboard; penyimpanan urutan atomik, pembaruan optimistis, dan pemulihan saat gagal.
- Factory dan pengujian referensi untuk UUID, validasi, isolasi item per grup, cascade delete, urutan, dan seeder yang mempertahankan urutan pengguna.
- Constraint unik nama grup dan konten per grup, serta indeks urutan item melalui migration baru.

- Fitur Pegawai dengan halaman daftar, tambah, detail, ubah, dan hapus permanen; pencarian nama/NIK/NIP/email; filter jabatan, status kontrak, status pegawai, dan rentang masa kerja. Pegawai aktif ditampilkan terlebih dahulu dengan urutan masa kerja terlama.
- Validasi identitas unik, jabatan wajib, dan tanggal bergabung maksimal hari berjalan; tombol detail berikon Info dengan varian `info` biru muda.

- Konfigurasi `pint.json` menyelaraskan aturan format PHP dengan Prettier, termasuk spasi penggabungan string, arrow function, constructor, dan anonymous class, agar kedua formatter tidak saling mengubah hasil.
- CRUD Jabatan dengan pencarian nama, paginasi, formulir tambah/ubah, konfirmasi hapus, dan navigasi melalui route terautentikasi `jt.*` pada URL `job-title`.
- Migration `2026_10_23_114205_add_uuid_to_job_titles_table.php` menambahkan UUID berindex, mengisi UUID untuk data lama, dan menetapkan kolom UUID sebagai NOT NULL pada SQLite. UUID hanya dipakai untuk URL; primary key tetap id numerik auto-increment untuk relasi.
- Pengujian Jabatan untuk autentikasi, pencarian literal, paginasi, validasi, persistensi, kontrak Resource, seeder, serta penolakan id numerik dan UUID yang tidak dikenal.

### Diubah

- Halaman kelola Templat Surat memakai satu kartu Identitas Templat serta kartu terpisah Konfigurasi Formulir Surat dan Penandatangan. Unduh DOCX Contoh dipindahkan ke header DOCX Sumber di samping Edit, termasuk pada mode hanya-baca.
- Variabel ditampilkan dalam tabel dengan editor per baris, Wajib/Opsional di samping jenis, detail combobox di bawahnya, serta urutan dan hapus yang langsung tersimpan. Penandatangan memiliki editor kandidat per slot; tambah membuka item lokal sampai disimpan.
- Atribut Pegawai memakai tabel dengan edit fallback per atribut dan format untuk tanggal. Label Nama beserta gelar/Nama tanpa gelar diperjelas; token tetap sama. Hanya satu editor aktif, Batal/Escape tanpa navigasi, dan galat mempertahankan input.
- Endpoint konfigurasi per item memeriksa kepemilikan, namespace global, dan status draft dalam transaksi; mempertahankan item lain serta fallback atribut terbaru ketika pemilik diedit. Kandidat tidak aktif di slot lain tidak memblokir edit variabel/atribut. Bukti pemeriksaan dibatalkan setelah perubahan konfigurasi.

- Key kartu identitas, konfigurasi, dan DOCX Templat Surat dibedakan berdasarkan jenis kartu agar tidak mengganda saat Edit/Simpan/Batal meskipun memakai UUID versi yang sama. Reset form saat keluar dari mode edit tetap dipertahankan.
- Kartu kelola Templat Surat dibuka dalam mode baca dan hanya satu kartu dapat diedit; Simpan/Batal hanya tampil saat edit. Batal memulihkan form tanpa navigasi, tombol urutan memakai ikon outline kecil, dan hapus memakai destructive.
- Panduan DOCX memuat keluarga token Pegawai/fallback/format; token nama penandatangan tidak diwajibkan. Lokasi unduhan terbaru mengikuti header DOCX Sumber di atas.

- Detail Pegawai disusun menjadi kartu ringkasan, data diri, pekerjaan, serta STR/SIP dengan kondisi kosong, tampilan responsif, dan dukungan mode gelap mengikuti tema yang ada.
- Kolom jenis kelamin Pegawai menggunakan integer tetap 1/2; formulir dikelompokkan menjadi Data Diri, Data Kepegawaian, STR, dan SIP.
- Penyimpanan grup referensi membuka halaman edit untuk melanjutkan pengelolaan item. Overlay pemuatan menghormati opsi kunjungan `showProgress: false` agar aksi item dapat menjaga fokus.

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
