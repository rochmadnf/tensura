# Perbaikan Pencarian, Komponen Reusable, dan Konsistensi UI Arsip

Tanggal: 25 Oktober 2026.

Status: sudah diimplementasikan dan lolos verifikasi otomatis pada 25 Oktober 2026. Pemeriksaan visual serta interaksi langsung di browser belum dilakukan karena Chrome/Chromium dan alat browser tidak tersedia di lingkungan kerja.

## 1. Ringkasan

Perbaiki bug pencarian, fokus input, pagination, dan sinkronisasi navigasi; kurangi pengulangan komponen; serta samakan tampilan halaman list arsip dengan modul yang sudah ada. Gunakan komponen dan pola proyek serta skill yang relevan sebagai acuan. Berdasarkan keputusan pengguna, PostgreSQL menjadi satu-satunya target database aplikasi dan tes database; integrasi Turso akan dihapus.

## 2. Temuan dan rencana perbaikan pencarian

- JobTitle dan Employee menggunakan `LIKE` yang membedakan huruf besar/kecil di PostgreSQL. Query baca pada database aktif dalam pemeriksaan awal membuktikan `dokter` tidak cocok dengan `Dokter Ahli Pertama`. LetterNumber juga menggunakan `LIKE`, sedangkan ReferenceGroup sudah menggunakan `LOWER(...) LIKE ... ESCAPE '!'`. Samakan pencarian pada keempat modul agar tidak membedakan huruf besar/kecil.
- Gunakan pola escape literal Reference yang sudah tersedia (`ESCAPE '!'`) untuk pencarian keempat modul. Pertahankan pencarian literal untuk `%`, `_`, `!`, backslash, dan tanda kutip. Kompatibilitas MySQL tidak lagi menjadi target rencana ini.
- `SectionData` memperbarui `appliedKeyword` sebelum request berhasil. Setelah gagal atau dibatalkan, debounce melewati keyword yang sama walaupun hasil belum diperbarui. Pisahkan draft keyword, request yang berjalan, dan keyword yang berhasil diterapkan agar percobaan ulang tetap dimungkinkan dan request ganda dapat dicegah. Respons lama tidak boleh menimpa ketikan baru; kegagalan atau pembatalan tidak boleh memicu loop retry otomatis.

Hasil verifikasi awal yang sudah tercatat: 38 tes `JobTitleIndexTest` lulus dengan 437 assertion menggunakan SQLite. Hasil ini merupakan riwayat pemeriksaan, bukan bukti kompatibilitas PostgreSQL; tes tersebut tidak dijalankan ulang saat menyimpan rekap ini.

### Rekap verifikasi tujuh issue sebelum implementasi

| Issue                                                     | Hasil verifikasi kode saat ini                                                                                                                                                                                                                                                                                             | Rencana perbaikan                                                                                                                                                                                                                    |
| --------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Overlay loading mencuri fokus                             | Sesuai. `PageLoadingOverlay` langsung memanggil `dialog.showModal()` saat visit yang mengaktifkan progress dimulai. Jeda 400 ms hanya menunda tampilannya, bukan sifat modalnya. Request pencarian dengan debounce 500 ms, pagination, dan filter list belum memakai `showProgress: false`; item-manager sudah memakainya. | Tambahkan `showProgress: false` pada request pencarian, pagination, dan filter list, termasuk arsip. Pertahankan indikator loading tabel dan overlay untuk navigasi biasa.                                                           |
| Pagination LetterNumber tidak deterministik               | Sesuai. `LetterNumberController::index` belum memiliki pengurutan. Kolom `classification_code` sudah memiliki constraint unik.                                                                                                                                                                                             | Tambahkan `orderBy('classification_code')` sebelum pagination. Urutan ini menjamin hasil deterministik pada dataset yang tetap; perubahan data antar-request tetap dapat memengaruhi offset pagination.                              |
| Query Employee berulang dan guard masa kerja kurang jelas | Sebagian sesuai. Dua `whereHas('jobTitle')` memfilter kondisi berbeda: kelompok jabatan dan UUID jabatan. `join_date` bertipe `date`, tetapi masih dibandingkan memakai `whereDate`. Guard terhadap tahun kalender melindungi perhitungan tanggal dari nilai ekstrem; tes nilai ekstrem dan tahun kabisat sudah tersedia.  | Gabungkan kedua kondisi relasi dalam satu `whereHas` dengan semantik AND. Gunakan perbandingan tanggal langsung pada `join_date`. Perjelas maksud guard tanpa mengubah hasil filter maupun menambahkan batas domain masa kerja baru. |
| Keyword array dapat menyebabkan error                     | Sesuai. JobTitle, LetterNumber, dan ReferenceGroup langsung menggunakan `$request->string('keyword')`. Reproduksi pada Laravel terpasang menghasilkan `Array to string conversion` untuk keyword berbentuk array. Employee sudah melakukan pengecekan tipe.                                                                | Ikuti pola Employee: baca query, cek `is_string`, trim, lalu terapkan minimum dua karakter. Input non-string diperlakukan sebagai keyword kosong.                                                                                    |
| Konfigurasi database tidak konsisten                      | Perlu koreksi. Koneksi aplikasi aktif terkonfirmasi `pgsql`, sedangkan runner tes utama memakai SQLite. Turso masih memiliki konfigurasi dan `turso-sync.mjs` yang mengimpor `@libsql/client`, sehingga dependency tersebut tidak tepat disebut sama sekali tidak dipakai.                                                 | Sesuai keputusan pengguna, gunakan PostgreSQL saja untuk aplikasi dan tes database serta hapus dependency, konfigurasi, dan skrip Turso.                                                                                             |
| Keyword SectionData tidak sinkron saat Back/Forward       | Sesuai. Input dan `appliedKeyword` mengambil nilai awal dari `defaultKeyword`, tanpa sinkronisasi saat `filters.keyword` berubah pada navigasi history.                                                                                                                                                                    | Sinkronkan input dan keyword yang diterapkan dengan hasil navigasi Back/Forward. Cegah debounce lama mengirim pencarian kembali setelah navigasi.                                                                                    |
| Flicker layout mobile                                     | Sesuai dengan koreksi. State awal `useIsMobile` adalah `undefined`, tetapi nilai kembaliannya `!!isMobile` menghasilkan `false` pada render pertama. Pembacaan viewport baru dilakukan di effect.                                                                                                                          | Inisialisasi status dari `matchMedia` pada render browser pertama, pertahankan listener perubahan breakpoint 768 px, dan pastikan aman untuk rendering server.                                                                       |

## 3. Komponen yang layak digunakan bersama

| Prioritas | Pengulangan                                                     | Arah refactor                                                                                                                                                                                          |
| --------- | --------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Tinggi    | Aksi hapus pada JobTitle, Employee, Reference, dan LetterNumber | Gunakan komponen bersama untuk dialog, status processing, pencegahan klik ganda, request, dan toast. URL serta teks tetap diberikan oleh modul. Reference harus tetap menjelaskan pemindahan ke arsip. |
| Tinggi    | Pengaturan tabel pada empat modul tersebut                      | Gunakan komponen di atas `DataTable` yang menyatukan pagination server, loading, penyusunan query, dan request Inertia. Definisi kolom tetap dimiliki tiap modul.                                      |
| Tinggi    | Form tambah dan ubah JobTitle                                   | Satukan menjadi `JobTitleForm`, mengikuti pola `EmployeeForm` dan `ReferenceGroupForm`.                                                                                                                |
| Sedang    | Form tambah dan ubah LetterNumber                               | Satukan field klasifikasi serta kerangka form. Nomor halaman dan nomor urut tetap dapat diisi hanya saat tambah dan ditampilkan sebagai disabled saat ubah.                                            |
| Sedang    | Kerangka form dan tombol Simpan/Batal                           | Ekstrak kerangka kartu dan aksi form tanpa memindahkan field maupun validasi domain ke komponen generik.                                                                                               |
| Tinggi    | Tombol aksi tabel                                               | Gunakan pola bersama untuk ukuran, varian, ikon, tooltip, dan aksesibilitas agar konsisten antarhalaman.                                                                                               |

Pengaturan pagination LetterNumber saat ini berbeda: menggunakan `Object.fromEntries(URLSearchParams)` dan hanya memuat ulang `resources`. Tiga modul lainnya menggunakan `buildListingQuery` dan memuat ulang `resources` serta `filters`. Satukan kebijakan request list melalui refactor tabel: penyusunan query, partial reload `resources` dan `filters`, preservasi state/scroll, serta `showProgress: false`. Definisi kolom dan aturan domain tetap dimiliki tiap modul.

Nama komponen baru seperti `DeleteResourceAction`, `ResourceDataTable`, `FormCard`, dan `FormActions` merupakan usulan; ikuti konvensi proyek saat implementasi. Tetap gunakan komponen bersama yang tersedia: `SectionData`, `DataFilterDialog`, `OptionSelect`, dan `DataTable`.

## 4. Konsistensi halaman list arsip

### Pencarian dan filter

- Ganti form pencarian khusus arsip dengan `SectionData`, sehingga posisi tombol cari, debounce, reset pencarian, spacing, dan susunan toolbar mengikuti modul lain.
- Sesuaikan `SectionData` agar tombol tambah tidak dirender jika tidak disediakan. Halaman arsip tidak memerlukan tombol tambah.
- Gunakan `DataFilterDialog` melalui slot `filterAction`, dengan pilihan Modul dan Jenis menggunakan komponen pilihan yang sudah ada.
- Sembunyikan pilihan Modul pada konteks arsip Reference. Pilihan Jenis tetap mencakup semua jenis, grup, dan item.
- Pertahankan keyword dan ukuran halaman saat menerapkan filter; reset pagination ke halaman pertama. Reset pada dialog mengosongkan pilihan filter dan berlaku setelah Terapkan, mengikuti perilaku dialog bersama.
- Pertahankan route arsip umum dan arsip Reference melalui fungsi Wayfinder yang sudah ada.
- Tombol menuju arsip pada halaman list tiap modul menggunakan ikon `Archive01Icon` saja, varian `dark`, tooltip dan label aksesibel “Arsip”. Render melalui prop opsional `archiveUrl` di `SectionData`, sebelum input pencarian, termasuk pada layar kecil. Modul Reference memakai route arsip Reference; modul lain dapat menggunakan prop yang sama saat memiliki tujuan arsip.

### Tombol aksi

Semua tombol aksi baris menggunakan `size="icon-sm"`, tooltip, `aria-label` yang menyertakan nama data, dan jarak mengikuti modul lain.

| Aksi           | Ikon               | Varian tombol | Tooltip        |
| -------------- | ------------------ | ------------- | -------------- |
| Detail         | `InfoIcon`         | `info`        | Detail         |
| Pulihkan       | `DataRecoveryIcon` | `approve`     | Pulihkan       |
| Hapus permanen | `ShredderIcon`     | `destructive` | Hapus Permanen |

Gunakan Hugeicons yang terpasang dan verifikasi ekspor ikon serta varian tombol sebelum implementasi. Pertahankan kondisi tampil aksi Pulihkan, tujuan navigasi, anchor pemulihan, status disabled, penguncian operasi, dan dialog konfirmasi hapus permanen.

Skill yang relevan untuk implementasi UI: `shadcn`, `tailwindcss-development`, `hugeicons`, `inertia-react-development`, `design-taste-frontend`, `ui-styling`, `ui-ux-pro-max` dan `wayfinder-development`. Pola modul yang sudah ada menjadi acuan konsistensi tampilan.

## 5. Penyelarasan database dan dependency

- PostgreSQL menjadi satu-satunya target database aplikasi dan tes database. SQLite dan MySQL tidak lagi menjadi target verifikasi rencana ini.
- Hapus `mis3085/turso-laravel`, `@libsql/client`, koneksi Turso, konfigurasi paket Turso, dan `turso-sync.mjs`. Perbarui `composer.lock` dan `pnpm-lock.yaml` melalui package manager, lalu pastikan tidak ada referensi runtime Turso yang tertinggal.
- Alihkan runner tes utama dari SQLite in-memory ke PostgreSQL khusus pengujian. Konfigurasi tes tidak boleh mengambil database aplikasi sebagai fallback.
- Pertahankan perlindungan database disposable pada runner arsip yang sudah ada. Selaraskan runner utama, runner arsip, dan ekspektasi tes kompatibilitas dengan target PostgreSQL; pertahankan cakupan perilaku arsip dan konkurensi yang bermakna.
- Perubahan ini tidak memerlukan pemindahan data aplikasi karena koneksi aktif sudah PostgreSQL. Penyediaan database pengujian dan perpindahan runner dilakukan saat implementasi, bukan saat menyimpan dokumen ini.

## 6. Urutan dan verifikasi

1. Selaraskan runner tes dengan PostgreSQL khusus pengujian dan hapus integrasi Turso.
2. Perbaiki pencarian, fokus input, sinkronisasi history, pagination LetterNumber, dan query Employee beserta regresi yang bermakna.
3. Satukan perilaku berulang pada tombol aksi dan tabel.
4. Gunakan komponen bersama untuk toolbar serta aksi list arsip dan perbaiki inisialisasi layout mobile.
5. Refactor form yang berulang tanpa mengubah aturan domain.

Verifikasi yang diperlukan:

- Pencarian pada PostgreSQL dengan variasi huruf besar/kecil, karakter literal `%`, `_`, `!`, backslash, dan tanda kutip, filter gabungan, serta pagination.
- Keyword array dan nested array tidak menyebabkan error; keyword non-string menjadi kosong. Trim dan minimum dua karakter tetap berlaku.
- Request pencarian gagal atau dibatalkan dapat dicoba ulang dengan keyword yang sama tanpa loop request.
- Fokus input dan ketikan tetap terjaga selama request lambat. Respons lama tidak menimpa draft keyword baru. Pencarian, pagination, dan filter list tidak membuka overlay modal; loading tabel tetap tersedia.
- Back/Forward menyelaraskan input, filter, dan hasil tabel tanpa debounce lama yang mengirim request balik.
- Pagination LetterNumber mengikuti urutan kode klasifikasi tanpa duplikasi atau kehilangan baris antarhalaman pada dataset tetap.
- Filter Employee mempertahankan semantik AND antara jabatan dan kelompok jabatan. Jalankan tes batas masa kerja inklusif, tahun kabisat, rentang terbalik, dan angka ekstrem.
- Pagination dan penerapan filter mempertahankan parameter yang diperlukan; reset filter dan reset keyword bekerja sesuai fungsi masing-masing.
- Toolbar arsip umum dan arsip Reference mengikuti pola modul lain pada desktop maupun mobile, tanpa tombol tambah kosong.
- Sidebar memakai layout mobile sejak render browser pertama pada viewport mobile, merespons perubahan breakpoint, dan tetap aman pada rendering server.
- Ikon, varian warna, tooltip, fokus keyboard, dan label aksesibel sesuai kesepakatan.
- Navigasi detail dan pemulihan serta konfirmasi hapus permanen tetap bekerja; pencegahan operasi ganda tetap berlaku.
- Jalankan tes yang terdampak, tes frontend yang relevan dengan perangkat pengujian yang sudah terpasang, dan pemeriksaan TypeScript. Jalankan suite database lengkap pada PostgreSQL setelah perpindahan runner. Perubahan styling murni tidak memerlukan tes baru; periksa hasilnya di browser. Jalankan Pint jika ada perubahan PHP.

## 7. Hasil implementasi dan verifikasi

- Pencarian JobTitle, Employee, LetterNumber, dan ReferenceGroup sudah memakai normalisasi keyword non-string serta pencarian tanpa membedakan kapital dengan escape literal `!`. LetterNumber diurutkan berdasarkan kode klasifikasi; JobTitle juga memiliki pengurutan `id` sebagai penentu akhir.
- Kondisi relasi jabatan Employee sudah digabung dengan semantik AND. Perbandingan `join_date` memakai `where` langsung; guard tahun positif tetap dipertahankan dan diperjelas tanpa mengubah aturan masa kerja.
- `SectionData` memakai `useListingSearch` dan state request `ListingSearch`: keyword diterapkan setelah sukses, request lama tidak mengubah status request baru, retry keyword yang sama tetap tersedia, dan navigasi history menyinkronkan input sambil membatalkan request sebelumnya. Tombol tambah kini hanya dirender jika disediakan.
- `ResourceDataTable` menyatukan pengaturan tabel empat modul dan list arsip. `DeleteResourceAction` menyatukan dialog, penguncian operasi hapus, request, dan toast; pesan pemindahan Reference ke arsip tetap dipertahankan. `TableActionButton` menyatukan tombol ikon, ukuran, tooltip, dan label aksesibel.
- List arsip memakai `SectionData`, `DataFilterDialog`, dan `OptionSelect`. Aksi Detail/Pulihkan/Hapus Permanen memakai ikon `InfoIcon`/`DataRecoveryIcon`/`ShredderIcon` dari Hugeicons Free Stroke Rounded, dengan varian `info`/`approve`/`destructive`. Kondisi tampil pemulihan, anchor, dan flow konfirmasi hapus tetap dipertahankan.
- Request pencarian, filter, dan pagination list memakai `showProgress: false` serta tetap menampilkan loading tabel. Pencarian grup tujuan dan pagination item pada detail arsip juga memakai opsi ini. Pelacakan loading tabel kini memperhitungkan request yang bertumpang tindih.
- Form tambah/ubah JobTitle memakai `JobTitleForm`; LetterNumber memakai `LetterNumberForm`. `FormCard` dan `FormActions` juga digunakan Employee dan Reference. Saat ubah LetterNumber, kedua counter tetap disabled dan validator update hanya mengirim field klasifikasi.
- `useIsMobile` membaca `matchMedia` sejak render browser pertama, mengikuti perubahan breakpoint 768 px, dan aman dirender tanpa `window`.
- Dependency, konfigurasi, dan skrip Turso sudah dihapus. Kedua lockfile diperbarui melalui package manager dan dicatat ke Git agar versi dependency konsisten antardevice. Artefak build SSR diabaikan Git. Default koneksi aplikasi dan queue memakai PostgreSQL; langkah pembuatan file SQLite pada setup Composer sudah dihapus.
- Runner utama memakai `tests/bootstrap.php` dengan koneksi PostgreSQL khusus pengujian, nama database disposable, penolakan config cache, dan tanpa fallback ke database aplikasi. Runner membaca konfigurasi database dari `.env.testing`; variabel proses `TEST_DB_*` memiliki prioritas tertinggi. `.env.testing.example` tersedia sebagai template, sedangkan konfigurasi lokal `.env.testing` diabaikan Git. Locale `id` dan zona waktu `Asia/Makassar` ditetapkan di PHPUnit agar tes tidak bergantung pada `.env` aplikasi. Runner arsip tetap terpisah dan kini khusus PostgreSQL. Baseline snapshot tanggal pada tes Employee dibaca ulang dari database agar perbandingan tidak bergantung pada format tanggal objek factory sebelum hidrasi PostgreSQL.

Hasil pemeriksaan:

| Pemeriksaan                                                                             | Hasil                                                                                                                                                                  |
| --------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Suite Laravel utama pada PostgreSQL melalui `php artisan test` tanpa variabel tambahan  | 686 tes: 675 berhasil, 11 tes konkurensi dilewati karena dijalankan melalui runner arsip terpisah; 4.888 assertion                                                     |
| Suite integrasi arsip pada PostgreSQL, termasuk konkurensi                              | 273 tes berhasil; 2.186 assertion                                                                                                                                      |
| Seluruh tes frontend                                                                    | 253 tes berhasil pada 17 file                                                                                                                                          |
| Pemeriksaan ulang frontend arsip, state pencarian, dan mobile setelah penyesuaian akhir | 33 tes berhasil pada 5 file                                                                                                                                            |
| TypeScript, lint file frontend yang diubah, dan Pint                                    | Berhasil                                                                                                                                                               |
| Build produksi dan SSR                                                                  | Berhasil                                                                                                                                                               |
| Composer audit                                                                          | Tidak menemukan advisory kerentanan                                                                                                                                    |
| Regresi konfigurasi dan guard database tes                                              | 6 tes berhasil: pemuatan `.env.testing`, prioritas override termasuk password kosong, default terisolasi, penolakan nama database aplikasi, dan penolakan config cache |
| Pemeriksaan visual, fokus input, Back/Forward, dan perubahan viewport di browser nyata  | Belum dilakukan; browser tidak tersedia                                                                                                                                |

Perintah pengujian database yang digunakan di lingkungan lokal ini:

```bash
php artisan test
ARCHIVE_TEST_DRIVER=pgsql ARCHIVE_TEST_DATABASE=tensura_archive_test_search_ui ARCHIVE_TEST_PORT=5433 vendor/bin/phpunit -c phpunit.archive.xml
```

Konfigurasi lokal `.env.testing` memakai database disposable `tensura_test_search_ui` pada port 5433, sehingga perintah biasa tidak memerlukan variabel tambahan. Sebelumnya hanya perintah dengan override eksplisit yang diverifikasi; perintah biasa gagal karena default port 5432 dan database `tensura_test` tidak sesuai lingkungan lokal. Masalah tersebut sudah direproduksi dan diperbaiki, termasuk pengaturan locale serta zona waktu tes.

Kedua database disiapkan khusus untuk pengujian. Untuk lingkungan lain, salin `.env.testing.example` ke `.env.testing`, buat application key pengujian melalui `php artisan key:generate --env=testing`, lalu sesuaikan `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD` dengan database disposable yang tersedia. Variabel `TEST_DB_*` tetap dapat digunakan untuk override runner utama; runner arsip memakai prefiks `ARCHIVE_TEST_`. Runner utama menggunakan nama default `tensura_test` jika nama database tidak diberikan melalui file maupun override. Data aplikasi tidak dimigrasikan atau diubah oleh pekerjaan ini.
