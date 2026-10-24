# Alur Pembuatan Fitur Baru (Tensura)

Wajib dibaca agent **sebelum** membuat fitur CRUD baru. Referensi utama: **JobTitle** (Jabatan). Referensi kedua: **LetterNumber** (Penomoran Surat).

Kontrak backend rinci (Form Request, Resource, routing, query list) ada di [LARAVEL_PATTERNS.md](LARAVEL_PATTERNS.md). Dokumen ini mengatur urutan kerja, wawancara, penamaan, frontend, skill, dan verifikasi. Jangan menduplikasi isi LARAVEL_PATTERNS.md.

## 0. Prinsip

1. **Wawancara dulu, kode kemudian.** Jangan menulis file apa pun sebelum ringkasan keputusan (bagian 1) disetujui pengguna.
2. **Jangan menanyakan yang bisa dibaca dari kode.** Periksa dulu `resources/js/components/app-sidebar.tsx`, `routes/web.php`, `database/migrations`, dan `php artisan route:list`. Tanyakan hanya yang belum diketahui.
3. **Satu topik per pertanyaan**, sertakan rekomendasi default, dan beri pilihan jawaban singkat bila memungkinkan.
4. **Bila JobTitle dan LetterNumber berbeda, ikuti JobTitle** (lihat bagian 8).
5. Jangan merefaktor fitur yang sudah ada, menambah dependensi, atau membuat folder dasar baru tanpa persetujuan.
6. Aktifkan skill yang relevan pada tiap fase (bagian 5) dan baca fitur referensi sebelum meniru polanya.

## 1. Wawancara

Jalankan berurutan. Percabangan ditulis dengan "Ya/Tidak". Skill `grill-me` / `grill-with-docs` boleh dipakai untuk gaya wawancara, tetapi semua pertanyaan di bawah harus tercakup.

### A. Sidebar dan penamaan halaman

1. **Apakah menu fitur ini sudah ada di `app-sidebar.tsx`?**
    - **Ya** → baca item tersebut (`id`, `title`/`name`, grup, `url`). Lalu tanyakan: **apakah nama halaman mengikuti nama menu di sidebar?** (default: ya)
        - **Ya** → `pageName` halaman index = nama menu persis. Halaman create = `Tambah {nama menu}`, edit = `Ubah {nama menu}`. `$pageId` di controller = `id` item sidebar tersebut.
        - **Tidak** → minta nama halaman yang diinginkan dan konfirmasi bahwa nama menu di sidebar tidak diubah.
        - Jika `url` masih `'#'`, gantikan dengan `{alias}.index().url`.
    - **Tidak** → tanyakan: grup sidebar (Data Master / Pegawai / grup baru), nama menu, ikon (cari di `@hugeicons/core-free-icons`, skill `hugeicons`), dan posisi urutan. Buat `id` UUID baru; nilai yang sama dipakai sebagai `$pageId` di controller dan di item sidebar.
2. **Nama teknis**: nama model (Inggris, singular, PascalCase), slug URL (kebab-case), alias route (2–3 huruf seperti `jt`, `ln`; harus belum dipakai, cek `route:list`), dan namespace domain (default `MasterData`).
3. **Apakah tabel sudah ada?**
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
9. **Hapus permanen atau soft delete?** Default: permanen. Soft delete mengubah skema dan perilaku, jadi butuh persetujuan eksplisit.
10. **Validasi khusus**: unik, format, rentang, pesan galat bila berbeda dari pesan bawaan.
11. **Otorisasi**: cukup login (default) atau perlu role/policy per record?

### D. Halaman daftar

12. **Kolom tabel** yang ditampilkan, kolom yang dicari lewat keyword, dan urutan default.
13. **Perlu filter?**
    - **Ya** → field apa, sumber opsi (enum → `options()`), tunggal atau multi. Format query: `filter=group:1,2|field:x`. Ikuti `JobTitleController::index` (`FilterQuery::parse`, nilai divalidasi terhadap enum) dan `DataFilterDialog` + `OptionSelect`.
    - **Tidak** → hanya keyword dan paginasi.

### E. Penutup

14. **Perlu seeder/data awal?** Jika ya, gunakan `updateOrCreate` agar dapat dijalankan ulang dan daftarkan di `DatabaseSeeder`.
15. **Perlu entri di `CHANGELOG.md`?** (ditulis dalam bahasa Indonesia)

### Ringkasan keputusan (wajib)

Setelah wawancara, tulis tabel ringkas: nama fitur, `pageId`, nama halaman, namespace, alias route, kolom, enum, relasi, operasi, kolom read-only saat edit, jenis hapus, validasi, otorisasi, kolom list, filter, seeder. **Minta persetujuan sebelum menulis kode.**

## 2. Aturan penamaan

Contoh memakai JobTitle (`job-title`, alias `jt`).

| Item           | Pola                                                                                                                                                                                                                                 | Contoh                                                      |
| -------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ----------------------------------------------------------- |
| Model          | `App\Models\{Domain}\{Nama}`; `HasUuids` dengan `uniqueIds()` → `['uuid']`; `$fillable`; `casts()` protected untuk enum. Tambahkan `HasFactory` dan `#[UseFactory]`                                                                  | `App\Models\MasterData\JobTitle`                            |
| Factory        | `Database\Factories\{Nama}Factory`; set `$model` bila model ada di sub-namespace                                                                                                                                                     | `JobTitleFactory`                                           |
| Migration      | tabel plural snake_case; `id()`, `uuid('uuid')->index()`, `timestamps()`                                                                                                                                                             | `create_job_titles_table`                                   |
| Controller     | `App\Http\Controllers\{Domain}\{Nama}Controller`; `protected string $pageId`                                                                                                                                                         | `JobTitleController`                                        |
| Request        | `Store{Nama}Request`, `Update{Nama}Request`; `authorize()` → `$this->user() !== null`; `attributes()` berbahasa Indonesia; aturan memakai `bail`                                                                                     | `StoreJobTitleRequest`                                      |
| Resource       | `App\Http\Resources\{Domain}\{Nama}Resource` dengan `@mixin`; `id` = `uuid`                                                                                                                                                          | `JobTitleResource`                                          |
| Enum           | `App\Enums\{Nama}{Atribut}Enum`, `int`, `label()`, `options()`                                                                                                                                                                       | `JobTitleGroupEnum`                                         |
| Route          | `Route::resource('job-title', X::class)->except('show')->parameters(['job-title' => 'jobTitle'])->scoped(['jobTitle' => 'uuid'])->names('jt')`; nama route `jt.index`, `jt.create`, `jt.store`, `jt.edit`, `jt.update`, `jt.destroy` | `routes/web.php`                                            |
| Halaman        | `resources/js/pages/{slug}/{index,create,edit}.tsx`                                                                                                                                                                                  | `pages/job-title/index.tsx`                                 |
| Komponen tabel | `resources/js/pages/{slug}/components/data-table/{index,column,delete-action}.tsx`                                                                                                                                                   | `JobTitleDataTable`, `columns`, `DeleteAction`              |
| Schema         | `resources/js/schemas/{slug}.ts` memakai `defineRequest` dengan label Indonesia                                                                                                                                                      | `createJobTitleSchema(groups)`                              |
| Route helper   | impor `@/routes/{alias}` (Wayfinder, `actions: false`); jangan menulis URL manual                                                                                                                                                    | `import jt from '@/routes/jt'`                              |
| Test           | `{Nama}IndexTest`, `{Nama}StoreTest`, `{Nama}EditTest`, `{Nama}DeleteTest` di `tests/Feature`                                                                                                                                        | `JobTitleStoreTest`                                         |
| Judul halaman  | index = nama menu sidebar; `Tambah {nama}`; `Ubah {nama}`. Tombol tambah: `{nama} Baru`                                                                                                                                              | `Jabatan`, `Tambah Jabatan`, `Ubah Jabatan`, `Jabatan Baru` |

Semua teks yang tampil ke pengguna (label, pesan, toast, placeholder) berbahasa Indonesia. Toast: sukses `Berhasil`, galat `Galat`.

## 3. Urutan implementasi

1. **Baca**: LARAVEL_PATTERNS.md, fitur referensi (controller, request, resource, halaman, test), `routes/web.php`, `app-sidebar.tsx`, skema database.
2. **Backend** (gunakan `php artisan make:... --no-interaction`; cek opsi dengan `--help`):
    - migration → enum (jika ada) → model + factory → `Store`/`Update` request → resource → controller → route → seeder (jika diminta).
    - `php artisan make:model {Domain}/{Nama} -mf --no-interaction`, `make:controller`, `make:request`, `make:resource`.
3. **Wayfinder**: `php artisan wayfinder:generate --no-interaction` setiap signature route berubah. Folder `resources/js/routes` di-gitignore.
4. **Frontend**: schema → kolom → `delete-action` → pembungkus data-table → `index` → `create` → `edit` → perbarui item sidebar.
    - Halaman Inertia harus ada sebagai file sebelum test `assertInertia` lulus (`ensure_pages_exist` aktif).
5. **Test** (bagian 6).
6. **Verifikasi** (bagian 7).

### Sidebar

- `id` item = `$pageId` controller (dipakai `NavGroups`/`NavMain` untuk status aktif lewat `page.id`).
- `url` = `{alias}.index().url`.
- Ikon: `HugeiconsIcon` dari `@hugeicons/core-free-icons`.
- Controller mengirim `'page' => ['id' => $this->pageId]` di setiap `inertia(...)`.

## 4. Aturan frontend

- **Gunakan ulang**: `SectionData`, `DataTable`/`useDataTable`/`createDataTableColumnHelper`, `DataTableFooter`, `useTableLoading('resources')`, `buildListingQuery`, `DataFilterDialog`, `OptionSelect` (≤5 opsi = select/checkbox, >5 = combobox), `useValibotForm`, `gooeyToast`. Periksa komponen yang ada sebelum membuat yang baru.
- **Pembungkus tabel**: ikuti `job-title/components/data-table/index.tsx` — `router.get(..., { preserveScroll: true, preserveState: true, only: ['resources', 'filters'] })` dengan `buildListingQuery(window.location.search, params)`.
- **Kolom**: kolom `no` (dengan `Subscribe` ke `table.atoms.pagination`), kolom data, kolom `actions` (tombol ubah `variant="warning" size="icon-sm"` dengan `Edit04Icon` + `Tooltip`, dan `DeleteAction` dengan `AlertDialog`). Definisikan kolom di luar komponen.
- **Form create/edit**: `Card` lengkap (`CardHeader`/`CardTitle`/`CardDescription`/`CardAction`/`CardContent`), `FieldGroup` + `Field` + `FieldLabel htmlFor` + `FieldError`, `data-invalid` pada `Field`, tombol Simpan (ikon `SaveIcon`/`LoaderIcon` saat `form.processing`) dan Batal (`variant="destructive"`, `Link` ke index). Jika `submit()` mengembalikan `false`, tampilkan toast galat. Kolom read-only tampil sebagai `Input` `readOnly`/`disabled` di luar data form.
- **Halaman**: `<Head title>` + `meta description` berbahasa Indonesia, dan `Halaman.layout = { pageName }`. Tipe props dideklarasikan eksplisit.
- **Gaya (skill `shadcn`, `ui-styling`, `tailwindcss-development`)**: `gap-*` bukan `space-*`; token semantik (`bg-primary`, `text-muted-foreground`), bukan warna mentah; `size-*` bila lebar = tinggi; ikon dalam komponen memakai `data-icon` tanpa kelas ukuran; `cn` dari `'cn'`; tidak ada `z-index` manual pada overlay.
- **Jangan mengedit `resources/js/components/ui/*`** (dikecualikan dari lint/format; ubah lewat CLI shadcn).
- Format: Prettier (`printWidth` 120, 4 spasi, single quote). Hindari impor tak terpakai.

## 5. Pemetaan skill (`boost.json`)

| Fase                                    | Skill                                                                        |
| --------------------------------------- | ---------------------------------------------------------------------------- |
| Wawancara                               | `grill-me`, `grill-with-docs`, `infer-conventions` (bila pola belum jelas)   |
| Backend                                 | `laravel-best-practices`, `laravel-patterns`                                 |
| Route dan helper frontend               | `wayfinder-development`                                                      |
| Halaman Inertia, form, navigasi         | `inertia-react-development`                                                  |
| Tabel                                   | `getting-started`, `table-state` (TanStack Table v9)                         |
| Komponen dan gaya                       | `shadcn`, `ui-styling`, `tailwindcss-development`, `hugeicons`, `goey-toast` |
| Tipe TypeScript rumit                   | `typescript-advanced-types`                                                  |
| Test                                    | `testing-best-practices`                                                     |
| Sebelum selesai                         | `code-review`                                                                |
| Hanya bila diminta                      | `codebase-design`, `improve-codebase-architecture`, `ui-ux-pro-max`          |
| Hanya untuk kode TanStack Table lama v8 | `migrate-v8-to-v9`                                                           |

Catatan: `composer.json` (`add-skills`) juga memuat `design-taste-frontend`, yang tidak ada di `boost.json`. Jangan mengandalkannya kecuali sudah terpasang.

## 6. Pengujian minimum

Gunakan PHPUnit 12, `LazilyRefreshDatabase`, factory, `UserFactory::new()->create()` + `actingAs`, dan `withoutVite()`. Setiap test membuat datanya sendiri. Nama test menyatakan hasil. Data provider `public static` dengan kunci deskriptif.

- **Index**: guest diarahkan ke `login.index`; komponen dan `page.id`; bentuk `resources.data`; keyword (trim, minimal 2 karakter, wildcard LIKE literal, hanya kolom yang ditentukan); `per_page` dibatasi allowlist; query string terbawa saat paginasi; filter (jika ada) termasuk input tidak valid dan injeksi.
- **Store**: halaman create terbuka; data tersimpan, `id`/`uuid` dari input diabaikan; validasi per field dengan pesan Indonesia (kosong, terlalu pendek/panjang, bukan string, enum tidak valid, duplikat); guest ditolak.
- **Edit/Update**: halaman terisi; hanya kolom yang boleh diubah yang berubah (kolom terlarang dikirim dan tetap utuh); nilai milik sendiri diterima, milik record lain ditolak; UUID tidak dikenal dan id numerik → 404; guest ditolak.
- **Delete**: hanya record terpilih yang hilang; guest ditolak; UUID tidak dikenal dan id numerik → 404.
- Jangan mem-mock database. Siapkan record prasyarat sebelum `Event::fake()` bila dipakai.

## 7. Verifikasi sebelum selesai

```sh
php artisan test --compact tests/Feature/{Nama}*Test.php
php vendor/bin/pint --dirty --format agent
pnpm exec prettier --write <file frontend yang diubah>
pnpm exec vp lint <file frontend yang diubah>
pnpm run types:check
pnpm run build
```

- `types:check` dinilai berdasarkan galat pada file yang diubah. Laporkan galat lama di file lain secara terpisah; jangan memperbaikinya tanpa diminta.
- Periksa manual: menu sidebar aktif di halaman fitur, pencarian, filter, paginasi, tambah, ubah, hapus, toast, dan kondisi loading.
- Laporan akhir singkat: apa yang dibuat, apa yang diuji, dan apa yang tidak dapat dijalankan beserta alasannya.

## 8. Konflik pola yang diketahui

| Hal                | JobTitle (ikuti)                                       | LetterNumber (jangan ditiru)                |
| ------------------ | ------------------------------------------------------ | ------------------------------------------- |
| Namespace model    | `App\Models\MasterData`                                | `App\Models`                                |
| Namespace resource | `App\Http\Resources\MasterData`                        | `App\Http\Resources`                        |
| Route              | `Route::resource(...)` + `scoped` + `names`            | route eksplisit, termasuk `/store`          |
| Query tabel        | `buildListingQuery` + `only: ['resources', 'filters']` | gabung query manual + `only: ['resources']` |

- `Employee` belum final (mis. `casts()` publik, belum ada factory/`HasFactory`); jangan dijadikan referensi.
- Kolom `uuid` saat ini `index()` biasa (bukan unique). Ikuti pola ini kecuali pengguna meminta unique.
- Fitur lama tidak diubah hanya demi keseragaman, kecuali diminta.
