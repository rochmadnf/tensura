# Alur Pembuatan Fitur Baru (Tensura)

Wajib dibaca agent **sebelum** membuat fitur/modul baru. Referensi CRUD sederhana: **JobTitle** (Jabatan). Gunakan **LetterNumber** untuk field yang dilindungi saat update, **Employee** untuk filter relasi/tanggal, dan **Reference/Archive** untuk penghapusan yang dapat dipulihkan.

Kontrak backend rinci (Form Request, Resource, routing, query list) ada di [LARAVEL_PATTERNS.md](LARAVEL_PATTERNS.md). Dokumen ini mengatur urutan kerja, wawancara, penamaan, frontend, skill, dan verifikasi. Jangan menduplikasi isi LARAVEL_PATTERNS.md.

## 0. Prinsip

1. **Pastikan kebutuhan sebelum implementasi.** Baca spesifikasi dan keputusan yang sudah diberikan, lalu klarifikasi hanya keputusan produk yang belum jelas. Persetujuan yang sudah diberikan tetap berlaku; jangan meminta persetujuan ulang untuk pekerjaan yang sama. Untuk fitur yang masih perlu wawancara, sepakati ringkasan bagian 1 sebelum implementasi yang bergantung padanya. Perbaikan dan pembaruan dokumentasi yang sudah diminta tidak memerlukan wawancara fitur baru.
2. **Jangan menanyakan yang bisa dibaca dari kode.** Periksa dulu `resources/js/components/app-sidebar.tsx`, `routes/web.php`, `database/migrations`, dan `php artisan route:list`. Tanyakan hanya yang belum diketahui.
3. **Satu topik per pertanyaan**, sertakan rekomendasi default, dan beri pilihan jawaban singkat bila memungkinkan.
4. **Utamakan komponen bersama dan kontrak domain yang sesuai.** Pilih referensi berdasarkan kebutuhan; perbedaan lama yang perlu dipertahankan dicatat pada bagian 8.
5. Jangan merefaktor fitur yang sudah ada, menambah dependensi, atau membuat folder dasar baru tanpa persetujuan.
6. Aktifkan skill yang relevan pada tiap fase (bagian 5) dan baca fitur referensi sebelum meniru polanya.

## 1. Wawancara

Isi keputusan berikut dari permintaan pengguna, sesi sebelumnya, dan kode yang ada. Tanyakan hanya bagian yang belum dapat ditentukan; tidak perlu menjadikan setiap butir pertanyaan tersendiri. Skill wawancara boleh digunakan jika tersedia dan relevan, tanpa mengulang informasi yang sudah disepakati.

### A. Sidebar dan penamaan halaman

1. **Periksa apakah menu fitur sudah ada di `app-sidebar.tsx`.**
    - **Ada** → baca item tersebut (`id`, `title`/`name`, grup, `url`). Nama halaman mengikuti nama menu, kecuali pengguna menentukan nama lain.
        - `pageName` halaman index = nama menu. Halaman create = `Tambah {nama menu}`, edit = `Ubah {nama menu}`. `$pageId` di controller = `id` item sidebar tersebut.
        - Bila pengguna menentukan nama halaman berbeda, pertahankan nama menu kecuali perubahan menu juga diminta.
        - Jika `url` masih `'#'`, gantikan dengan `{alias}.index().url`.
    - **Tidak** → tanyakan: grup sidebar (Data Master / Pegawai / grup baru), nama menu, ikon (cari di `@hugeicons/core-free-icons`, skill `hugeicons`), dan posisi urutan. Buat `id` UUID baru; nilai yang sama dipakai sebagai `$pageId` di controller dan di item sidebar.
2. **Nama teknis**: nama model (Inggris, singular, PascalCase), slug URL (kebab-case), alias route (singkat dan bermakna seperti `jt`, `ln`, `empl`; harus belum dipakai, cek `route:list`), dan namespace domain (`MasterData` untuk data master).
3. **Periksa apakah tabel sudah ada.**
    - **Ya** → baca skema dengan tool `database-schema`. Jika data lama perlu kolom `uuid`, ikuti migration `add_uuid_to_job_titles_table` (tambah nullable, isi data lama, ubah menjadi NOT NULL).
    - **Tidak** → lanjut ke bagian B.

### B. Data

4. **Daftar kolom**: nama, tipe, panjang, nullable, unique, default, dan label Indonesia untuk tiap kolom.
5. **Ada kolom pilihan tetap (enum)?**
    - **Ya** → buat `App\Enums\{Nama}Enum` (backed `int`, `label()`, `options()` seperti `JobTitleGroupEnum`). Tanyakan nilai dan labelnya.
    - **Tidak** → lanjut.
6. **Ada relasi ke tabel lain?** Jika ya: jenis relasi, kolom foreign key, dan perilaku `onDelete`. Relasi memakai id numerik; UUID hanya untuk URL dan Resource.

### C. Perilaku

7. **Operasi apa saja?** Default: index, create, store, edit, update, destroy (tanpa show).
8. **Kolom mana yang tidak boleh diubah saat edit?** (contoh LetterNumber: `page_number`, `sequence_number`). Kolom itu tampil read-only dan tidak diterima oleh `Update{Nama}Request`.
9. **Perilaku penghapusan**: permanen, arsip snapshot yang dapat dipulihkan, atau kebutuhan soft delete tersendiri. CRUD sederhana mengikuti penghapusan permanen JobTitle/LetterNumber jika tidak ada kebutuhan pemulihan. Reference memakai arsip snapshot, bukan `SoftDeletes`. Jika perlu arsip, tentukan entitas/induk-anak, pemulihan, konflik, dan hapus permanen arsip; ikuti bagian arsip di LARAVEL_PATTERNS.md. Perubahan skema/perilaku di luar lingkup yang disepakati memerlukan persetujuan.
10. **Validasi khusus**: unik, format, rentang, pesan galat bila berbeda dari pesan bawaan.
11. **Otorisasi**: cukup login (default) atau perlu role/policy per record?

### D. Halaman daftar

12. **Kolom tabel** yang ditampilkan, kolom teks yang dicari lewat keyword (termasuk relasi bila diperlukan), urutan default yang deterministik, dan apakah ada halaman detail. Daftarkan kolom pencarian pada `searchableColumns()` melalui trait `App\Models\Searchable`.
13. **Perlu filter?**
    - **Ya** → field apa, sumber opsi (enum → `options()`), tunggal atau multi. Format query: `filter=group:1,2|field:x`. Ikuti `JobTitleController::index` (`FilterQuery::parse`, nilai divalidasi terhadap enum) dan `DataFilterDialog` + `OptionSelect`.
    - **Tidak** → hanya keyword dan paginasi.

### E. Penutup

14. **Perlu seeder/data awal?** Jika ya, gunakan `updateOrCreate` agar dapat dijalankan ulang dan daftarkan di `DatabaseSeeder`.
15. **Dokumentasi/changelog** dibuat atau diperbarui hanya jika diminta pengguna; gunakan bahasa Indonesia untuk entri `CHANGELOG.md`. Jangan menjadikan dokumentasi tambahan sebagai syarat menyelesaikan fitur.

### Ringkasan keputusan (wajib)

Rangkum nama fitur, `pageId`, nama halaman, namespace, alias route, kolom, enum, relasi, operasi, kolom yang dilindungi saat edit, jenis hapus/pemulihan, validasi, otorisasi, kolom list/pencarian, urutan, filter, dan seeder. Bedakan keputusan pengguna, pola yang dibaca dari kode, dan asumsi implementasi. Bila spesifikasi sudah lengkap dan pekerjaan telah diotorisasi, lanjutkan tanpa meminta persetujuan ulang; bila ada keputusan produk yang belum jelas, minta jawaban sambil melanjutkan pemeriksaan yang tidak bergantung pada jawaban itu.

## 2. Aturan penamaan

Contoh memakai JobTitle (`job-title`, alias `jt`).

| Item           | Pola                                                                                                                                                                                                                                 | Contoh                                                      |
| -------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ----------------------------------------------------------- |
| Model          | `App\Models\{Domain}\{Nama}`; `HasUuids` dengan `uniqueIds()` → `['uuid']`; `$fillable`; `casts()` protected; `HasFactory` dan `#[UseFactory]`. Listing yang dapat dicari memakai `Searchable` dan `searchableColumns()`             | `App\Models\MasterData\JobTitle`                            |
| Factory        | `Database\Factories\{Nama}Factory`; set `$model` bila model ada di sub-namespace                                                                                                                                                     | `JobTitleFactory`                                           |
| Migration      | tabel plural snake_case; `id()`, kolom UUID, `timestamps()`; indeks/constraint sesuai kontrak dan skema aktual (bagian 8)                                                                                                            | `create_job_titles_table`                                   |
| Controller     | `App\Http\Controllers\{Domain}\{Nama}Controller`; `protected string $pageId`                                                                                                                                                         | `JobTitleController`                                        |
| Request        | `Store{Nama}Request`, `Update{Nama}Request`; `authorize()` → `$this->user() !== null`; `attributes()` berbahasa Indonesia; aturan memakai `bail`                                                                                     | `StoreJobTitleRequest`                                      |
| Resource       | `App\Http\Resources\{Domain}\{Nama}Resource` dengan `@mixin`; `id` = `uuid`                                                                                                                                                          | `JobTitleResource`                                          |
| Enum           | `App\Enums\{Nama}{Atribut}Enum`, `int`, `label()`, `options()`                                                                                                                                                                       | `JobTitleGroupEnum`                                         |
| Route          | `Route::resource('job-title', X::class)->except('show')->parameters(['job-title' => 'jobTitle'])->scoped(['jobTitle' => 'uuid'])->names('jt')`; nama route `jt.index`, `jt.create`, `jt.store`, `jt.edit`, `jt.update`, `jt.destroy` | `routes/web.php`                                            |
| Halaman        | `resources/js/pages/{slug}/{index,create,edit}.tsx`                                                                                                                                                                                  | `pages/job-title/index.tsx`                                 |
| Komponen tabel | `resources/js/pages/{slug}/components/data-table/{index,column,delete-action}.tsx`                                                                                                                                                   | `JobTitleDataTable`, `columns`, `DeleteAction`              |
| Form fitur     | `resources/js/pages/{slug}/components/{slug}-form.tsx`; dipakai bersama halaman create/edit                                                                                                                                          | `JobTitleForm`, `LetterNumberForm`                          |
| Schema         | `resources/js/schemas/{slug}.ts` memakai `defineRequest` dengan label Indonesia                                                                                                                                                      | `createJobTitleSchema(groups)`                              |
| Route helper   | impor `@/routes/{alias}` (Wayfinder, `actions: false`); jangan menulis URL manual                                                                                                                                                    | `import jt from '@/routes/jt'`                              |
| Test           | `{Nama}IndexTest`, `{Nama}StoreTest`, `{Nama}EditTest`, `{Nama}DeleteTest` di `tests/Feature`                                                                                                                                        | `JobTitleStoreTest`                                         |
| Judul halaman  | index = nama menu sidebar; `Tambah {nama}`; `Ubah {nama}`. Tombol tambah: `{nama} Baru`                                                                                                                                              | `Jabatan`, `Tambah Jabatan`, `Ubah Jabatan`, `Jabatan Baru` |

Semua teks yang tampil ke pengguna (label, pesan, toast, placeholder) berbahasa Indonesia. Toast: sukses `Berhasil`, galat `Galat`.

## 3. Urutan implementasi

1. **Baca dan periksa**: `AGENTS.md`, aturan `.ai/rules` bila tersedia, LARAVEL_PATTERNS.md, fitur referensi, `routes/web.php`, `app-sidebar.tsx`, dan skema database. Konfirmasi versi paket melalui Composer / `package.json` dan gunakan Boost `search-docs` untuk API framework yang diperlukan. Jangan menyalin contoh dari versi berbeda.
2. **Backend** (gunakan `php artisan make:... --no-interaction`; cek opsi dengan `--help`):
    - migration → enum (jika ada) → model + factory → `Store`/`Update` request → resource → controller → route → seeder (jika diminta).
    - Untuk listing yang dapat dicari, gunakan `Searchable`, daftar kolom eksplisit, `normalizeSearchKeyword()` untuk prop `filters.keyword`, dan `->search($keyword)` pada query. Jangan menulis ulang SQL pencarian di controller. Tambahkan pengurutan dan pagination sesuai LARAVEL_PATTERNS.md.
    - Untuk arsip, implementasikan snapshot, pemilihan record, konflik, preview/token, pemulihan, serta transaksi/locking yang diperlukan sebelum menghubungkan UI. Registry handler saja belum membuat modul dapat dipulihkan.
    - `php artisan make:model {Domain}/{Nama} -mf --no-interaction`, `make:controller`, `make:request`, `make:resource`.
3. **Wayfinder**: `php artisan wayfinder:generate --no-interaction` setiap signature route berubah. Plugin Vite juga menjalankan generator saat development/build; konfigurasi saat ini `routes: true`, `actions: false`, `formVariants: false`. Jangan mengedit file hasil generator atau mengandalkan helper yang tidak dihasilkan. Folder `resources/js/routes` di-gitignore.
4. **Frontend**: schema dan tipe → kolom → pembungkus `DeleteResourceAction` → pembungkus `ResourceDataTable` → form fitur bersama → `index`/`create`/`edit` (dan `show` bila diperlukan) → perbarui item sidebar.
    - Halaman Inertia harus ada sebagai file sebelum test `assertInertia` lulus (`ensure_pages_exist` aktif).
5. **Test** (bagian 6).
6. **Verifikasi** (bagian 7).

### Sidebar

- `id` item = `$pageId` controller (dipakai `NavGroups`/`NavMain` untuk status aktif lewat `page.id`).
- `url` = `{alias}.index().url`.
- Renderer ikon: `HugeiconsIcon` dari `@hugeicons/react`; data ikon dari `@hugeicons/core-free-icons`. Verifikasi nama ikon yang benar melalui skill `hugeicons`.
- Controller mengirim `'page' => ['id' => $this->pageId]` di setiap `inertia(...)`.

## 4. Aturan frontend

- **Toolbar**: gunakan `SectionData` dengan `searchUrl`, `defaultKeyword={filters.keyword}`, dan `dataTable`. Tombol tambah melalui `renderButton`/`textButton` hanya jika operasinya tersedia; filter melalui `filterAction`. `archiveUrl` hanya diisi jika tujuan arsip sudah didukung.
- **Pencarian**: `SectionData` sudah memakai `useListingSearch` dan state `ListingSearch`. Gunakan perilaku debounce 500 ms, submit/reset, pembatalan request, retry, preservasi ketikan, dan sinkronisasi Back/Forward yang ada; jangan membuat state pencarian kedua di halaman.
- **Pembungkus tabel**: ikuti `job-title/components/data-table/index.tsx` yang meneruskan `data`, `meta`, `columns`, `url`, dan `getRowId` ke `ResourceDataTable`. Komponen bersama mengatur pagination server, loading, dan footer. Gunakan UUID record sebagai row ID, bukan indeks baris. `DataTable`/`useDataTable` dipakai langsung hanya bila alur khusus memerlukannya, seperti seleksi record detail arsip.
- **Request list**: pencarian, filter, dan pagination memakai `buildListingQuery`, `preserveScroll: true`, `preserveState: true`, `showProgress: false`, dan partial reload `['resources', 'filters']`; pencarian juga memakai `replace: true`. Pertahankan parameter lain dan reset halaman ketika keyword/filter berubah. Reuse `useTableLoading('resources')`; overlay modal navigasi tidak boleh mengambil fokus saat pembaruan list.
- **Filter**: gunakan `DataFilterDialog` dan `OptionSelect`. Nilai draft diterapkan saat Terapkan; Reset mengosongkan draft filter dan tidak langsung mengubah hasil. Ikuti parser/serializer bersama dan validasi nilai domain sesuai LARAVEL_PATTERNS.md.
- **Kolom dan aksi**: definisikan kolom di luar komponen melalui `createDataTableColumnHelper` (TanStack Table v9). Nomor baris mengikuti `Subscribe` ke `table.atoms.pagination`. Gunakan `TableActionButton` untuk aksi ubah (`warning`, `Edit04Icon`), detail, dan aksi ikon lain; sertakan nama record pada label aksesibel. `DeleteAction` menjadi pembungkus tipis `DeleteResourceAction` dengan URL dan teks domain.
- **Form create/edit**: satukan field dalam `{Nama}Form` dengan record opsional; halaman hanya mengatur metadata dan props. Gunakan `FormCard`, `FormActions`, `useValibotForm`, schema `defineRequest`, serta `FieldGroup`/`Field`/`FieldLabel`/`FieldError`. Field dan aturan domain tetap dimiliki form fitur. Jika `submit()` mengembalikan `false`, tampilkan toast galat; cegah submit ganda saat processing. Field yang dilindungi boleh ditampilkan disabled/readOnly, tetapi wajib dikeluarkan dari payload update dan allowlist backend.
- **Hapus dan arsip**: gunakan dialog, locking, request, dan toast dari `DeleteResourceAction` untuk hapus resource biasa. Pesan Reference harus menjelaskan pemindahan ke arsip. Pemulihan dan hapus permanen arsip tetap menggunakan hook/dialog khusus dengan preview/token; jangan menggantinya dengan dialog hapus CRUD.
- **Halaman**: `<Head title>` + `meta description` berbahasa Indonesia, dan `Halaman.layout = { pageName }`. Tipe props dideklarasikan eksplisit.
- **Gaya (skill `shadcn`, `ui-styling`, `tailwindcss-development`)**: `gap-*` bukan `space-*`; token semantik (`bg-primary`, `text-muted-foreground`), bukan warna mentah; `size-*` bila lebar = tinggi; ikon dalam komponen memakai `data-icon` tanpa kelas ukuran; `cn` dari `'cn'`; tidak ada `z-index` manual pada overlay.
- **Komponen UI**: dahulukan komponen yang sudah tersedia dan ikuti skill `shadcn` saat menambah atau mengubahnya. Pengecualian `resources/js/components/ui/*` dari lint/format bukan larangan perubahan; edit hanya bila diperlukan dan sesuai lingkup.
- **Mobile**: gunakan `useIsMobile` yang membaca `matchMedia` sejak render browser pertama, mendengarkan perubahan breakpoint 768 px, dan aman tanpa `window`. Jangan menggandakan state awal desktop yang menyebabkan flicker.
- Format: Prettier (`printWidth` 120, 4 spasi, single quote). Hindari impor tak terpakai.

## 5. Pemetaan skill

| Fase                                    | Skill                                                                                                                                                               |
| --------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Kebutuhan dan rancangan                 | Skill wawancara yang tersedia bila diperlukan; `codebase-design` untuk interface atau seam modul                                                                    |
| Backend                                 | `laravel-best-practices`, `laravel-patterns`                                                                                                                        |
| Route dan helper frontend               | `wayfinder-development`                                                                                                                                             |
| Halaman Inertia, form, navigasi         | `inertia-react-development`                                                                                                                                         |
| Tabel                                   | `getting-started`, `table-state` (TanStack Table v9)                                                                                                                |
| Komponen dan gaya                       | `shadcn`, `ui-styling`, `tailwindcss-development`, `hugeicons`, `design-taste-frontend`, `ui-ux-pro-max` sesuai domain; `goey-toast` ketika menambah/mengubah toast |
| Tipe TypeScript rumit                   | `typescript-advanced-types`                                                                                                                                         |
| Test                                    | `testing-best-practices`                                                                                                                                            |
| Review branch/PR/perubahan yang diminta | `code-review`                                                                                                                                                       |
| Inferensi dan pencatatan konvensi       | `infer-conventions` hanya jika diminta secara eksplisit; bukan langkah otomatis saat pola belum jelas                                                               |
| Hanya untuk kode TanStack Table lama v8 | `migrate-v8-to-v9`                                                                                                                                                  |

Katalog skill yang tersedia dalam sesi beserta pemicunya menjadi acuan aktivasi. `boost.json` saat ini sudah mencantumkan `design-taste-frontend`; daftar konfigurasi atau skrip `add-skills` bukan bukti bahwa setiap skill tersedia dalam sesi. Jangan memanggil atau menginstal skill yang tidak tersedia hanya untuk memenuhi tabel ini. Pemeriksaan diff sendiri tetap dilakukan sebelum selesai; review dengan skill `code-review` digunakan sesuai permintaan dan instruksinya.

## 6. Pengujian minimum

Baca skill `testing-best-practices`. Gunakan PHPUnit 12, `LazilyRefreshDatabase`, factory, `UserFactory::new()->create()` + `actingAs`, dan `withoutVite()` mengikuti kebutuhan endpoint. Setiap test membuat datanya sendiri. Nama test menyatakan hasil. Data provider `public static` dengan kunci deskriptif. Pilih cakupan yang bermakna untuk perilaku yang dibuat/diubah; gunakan kembali matriks regresi yang sudah ada.

Tes database memakai PostgreSQL disposable sesuai konfigurasi `.env.testing.example`, `tests/bootstrap.php`, dan bagian pengujian LARAVEL_PATTERNS.md. Jangan memakai database aplikasi atau mengganti runner menjadi SQLite. Konfigurasi lokal tidak dicommit; override runner utama memakai `TEST_DB_*`, sedangkan runner arsip memakai `ARCHIVE_TEST_*`.

- **Index**: guest diarahkan ke `login.index`; komponen dan `page.id`; bentuk `resources.data`; keyword (trim, minimal 2 karakter, tidak membedakan kapital, wildcard/karakter escape literal, hanya kolom yang ditentukan); malformed keyword sesuai kontrak endpoint; `per_page` dibatasi allowlist; urutan stabil dan query string terbawa saat paginasi; kombinasi filter termasuk nilai tidak valid dan injeksi. Gunakan `ListingSearchTest` dan test index fitur yang relevan.
- **Store**: halaman create terbuka; data tersimpan, `id`/`uuid` dari input diabaikan; validasi per field dengan pesan Indonesia (kosong, terlalu pendek/panjang, bukan string, enum tidak valid, duplikat); guest ditolak.
- **Edit/Update**: halaman terisi; hanya kolom yang boleh diubah yang berubah (kolom terlarang dikirim dan tetap utuh); nilai milik sendiri diterima, milik record lain ditolak; UUID tidak dikenal dan id numerik → 404; guest ditolak.
- **Delete**: hanya record terpilih yang dihapus/diarsipkan, record lain tetap ada; guest ditolak; UUID tidak dikenal dan id numerik → 404. Jika diarsipkan, periksa snapshot, relasi, dan data aktif sesuai kontrak.
- **Arsip**: bila terdampak, uji scope pilihan record, konflik, token salah pengguna/aksi atau kedaluwarsa karena state berubah, partial restore, dan hapus permanen. Jalankan runner arsip terpisah untuk pengujian konkurensi; kasus yang dilewati runner utama belum terverifikasi.
- **Frontend**: jalankan test state/query pencarian, schema, atau render yang terdampak melalui `vite-plus/test`. Tidak perlu test baru untuk perubahan copy/styling/layout murni. Verifikasi fokus, request lambat, dan Back/Forward di browser bila relevan.
- Jangan mem-mock database. Siapkan record prasyarat sebelum `Event::fake()` bila dipakai.

## 7. Verifikasi sebelum selesai

Jalankan pemeriksaan sesuai jenis perubahan. Contoh untuk CRUD yang mengubah backend dan frontend (ganti file JobTitle dengan fitur terkait):

```sh
php artisan test --compact tests/Feature/JobTitleIndexTest.php tests/Feature/JobTitleStoreTest.php tests/Feature/JobTitleEditTest.php tests/Feature/JobTitleDeleteTest.php
vendor/bin/pint --dirty --format agent
pnpm exec prettier --write resources/js/pages/job-title
pnpm run lint
pnpm run types:check
pnpm run build
```

- Jalankan file test frontend yang relevan dengan `pnpm exec vp test run --config vitest.config.ts <file-test>`. Regenerasi Wayfinder saat route berubah; jalankan `pnpm run build:ssr` bila SSR terdampak. Perintah dan konfigurasi runner arsip tersedia di LARAVEL_PATTERNS.md.
- Jalankan ulang test setelah mengubahnya. Pint wajib saat PHP berubah. Untuk perubahan dokumentasi saja, cukup periksa referensi, format Markdown, dan `git diff --check`; tidak perlu test aplikasi atau build.
- `types:check` tetap merupakan pemeriksaan proyek penuh. Jangan menganggapnya lulus jika masih ada error; pisahkan error akibat perubahan dari masalah lama dan laporkan dengan jelas.
- Periksa manual bila UI berubah: menu sidebar aktif, fokus input saat request berjalan, pencarian/reset, filter/Terapkan/Reset, paginasi, Back/Forward, tambah/ubah/hapus, toast, loading, keyboard/label aksesibel, dan viewport mobile. Laporkan bila browser atau pemeriksaan tertentu tidak tersedia.
- Laporan akhir singkat: apa yang dibuat, apa yang diuji, dan apa yang tidak dapat dijalankan beserta alasannya.

## 8. Perbedaan pola yang perlu dipertahankan

| Hal                 | Kontrak saat ini                                                                                                        | Pedoman fitur baru                                                                                                                                          |
| ------------------- | ----------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Namespace           | JobTitle/Employee/Reference di domain `MasterData`; LetterNumber berada langsung di `App\Models` / `App\Http\Resources` | Ikuti domain fitur dan folder yang sudah ada; jangan memindahkan fitur lama demi keseragaman                                                                |
| Route               | JobTitle memakai resource route; LetterNumber mempertahankan route eksplisit, termasuk `/store`                         | Resource route dengan nama dan binding UUID ketika sesuai kebutuhan; pertahankan URL lama                                                                   |
| Pencarian dan tabel | Keempat CRUD dan list arsip memakai `Searchable`, `SectionData`, dan `ResourceDataTable`                                | Gunakan abstraksi bersama; tidak ada lagi alasan meniru query/pagination manual lama LetterNumber                                                           |
| Identitas Resource  | CRUD memakai `id` berisi UUID; arsip memakai `uuid`                                                                     | Selaraskan tipe frontend dan `getRowId` dengan kontrak yang dipilih                                                                                         |
| UUID database       | JobTitle/Employee/LetterNumber memakai indeks biasa; Reference dan tabel arsip sudah memakai constraint unik            | Periksa skema aktual; tentukan constraint berdasarkan invariannya, termasuk kebutuhan restore/konkurensi; jangan mengubah indeks fitur lama tanpa kebutuhan |
| Form update         | LetterNumber melindungi counter; fitur lain memiliki field dan aturan sendiri                                           | Satu form fitur untuk create/edit dengan payload dan validasi sesuai operasi                                                                                |
| Hapus               | JobTitle/Employee/LetterNumber permanen; Reference memakai arsip snapshot                                               | Tentukan kebutuhan pemulihan; gunakan alur backend dan dialog yang sesuai                                                                                   |

- `Employee` sudah memiliki factory/`HasFactory`, `casts()` protected, serta test filter relasi/tanggal. Gunakan sebagai referensi untuk kebutuhan tersebut.
- Fitur lama tidak diubah hanya demi keseragaman, kecuali diminta.
