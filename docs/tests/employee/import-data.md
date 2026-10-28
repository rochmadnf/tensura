# Rencana Impor Pegawai melalui Excel

Status: implementasi impor awal selesai dan seluruh checklist visual awal telah diperiksa pengguna secara mandiri. Revisi UI/UX dan ekspor telah diimplementasikan; checklist tambahan untuk revisi ini masih menunggu pemeriksaan manual pengguna.

## Tujuan

Memudahkan admin mengisi ulang pegawai setelah refresh database melalui Halaman Pegawai. Admin mengunduh template, mengisi data dengan dropdown jabatan, lalu mengunggah file. Baris valid disimpan; duplikat dan baris tidak valid dilewati dengan alasan yang jelas. Data yang sudah tersedia tidak diperbarui.

## Library dan batas impor

- Gunakan `maatwebsite/excel:^4.0.6`, dengan PhpSpreadsheet sebagai dependensi. Penambahan library sudah disetujui.
- Verifikasi kompatibilitas Composer, ekstensi PHP, dan API versi yang terpasang sebelum implementasi.
- Terima `.xlsx` maksimal 2 MB dan 1.000 baris data.
- Proses langsung tanpa antrean.

Referensi:

- [Persyaratan Laravel Excel 4.0.6](https://github.com/SpartnerNL/Laravel-Excel/blob/4.0.6/composer.json)
- [Data Validation PhpSpreadsheet](https://phpspreadsheet.readthedocs.io/en/latest/topics/recipes/#setting-data-validation-on-a-cell)

## Template dan prasyarat jabatan

- Minimal satu master jabatan harus tersedia sebelum template dapat diunduh.
- Saat master kosong, nonaktifkan unduh dan unggah. Tampilkan pesan: **“Tambahkan minimal satu jabatan sebelum mengunduh template dan mengimpor pegawai.”** Sertakan tombol menuju tambah jabatan.
- Backend memeriksa prasyarat yang sama pada endpoint unduh dan impor.
- Template memiliki sheet `Pegawai`, `Petunjuk`, dan sheet tersembunyi `Referensi`.
- Kolom Jabatan memiliki dropdown untuk 1.000 baris pengisian, menggunakan named range pada sheet referensi. Daftar berisi nama jabatan dari database, diurutkan alfabetis, tanpa ID/UUID.
- Dropdown menampilkan petunjuk dan menolak pengetikan nilai di luar daftar.
- Daftar merupakan snapshot saat unduh. Petunjuk meminta admin mengunduh ulang setelah master jabatan berubah.
- Saat impor, cocokkan nama dengan master terbaru, mengabaikan kapital dan spasi awal/akhir. Jabatan tidak ditemukan atau ambigu membuat baris dilewati; tidak membuat jabatan otomatis.
- Backend tetap memvalidasi jabatan karena salin-tempel dapat melewati validasi Excel.

## Kolom dan validasi

- Template mencakup seluruh data formulir: NIK, NIP, nama, jenis kelamin, gelar depan/belakang, jabatan, pangkat, golongan, tanggal bergabung, email, status kontrak, status pegawai, serta nomor/status/tanggal STR dan nomor/tanggal SIP.
- Sheet petunjuk menjelaskan kolom wajib, pilihan label status dan jenis kelamin, serta aturan kelengkapan STR/SIP.
- Format NIK, NIP, STR, dan SIP sebagai teks. Tolak identitas numerik yang berisiko kehilangan digit dengan petunjuk memperbaiki data; jangan menebak digit yang hilang.
- Terima tanggal Excel atau teks `YYYY-MM-DD`.
- Tolak sel formula pada data pegawai. Formula named range untuk dropdown template tetap diperbolehkan.
- Abaikan baris kosong.
- Gunakan aturan validasi bersama dengan formulir pegawai agar batas panjang, tanggal, pilihan status, dan kelengkapan STR/SIP tetap konsisten.

## Backend dan hasil impor

- Tambahkan route terautentikasi `empl.import.create`, `empl.import.template`, dan `empl.import.store` sebelum route resource pegawai.
- Frontend memanggil route melalui Wayfinder.
- Endpoint unggah menerima multipart `file` dan mengembalikan JSON berisi jumlah diproses, berhasil, duplikat, tidak valid, serta rincian masalah: nomor baris Excel, nama pegawai, kolom, dan pesan.
- Pisahkan pembacaan/pemetaan Excel dari validasi dan penyimpanan pegawai.
- Tolak file rusak, header tidak sesuai, sheet wajib hilang, atau batas terlampaui sebelum penyimpanan dimulai.
- Periksa duplikat NIK, NIP, email, STR, dan SIP terhadap database serta baris sebelumnya yang berhasil disimpan. Nilai opsional kosong tidak dianggap duplikat.
- Proses berurutan dan gunakan transaksi per baris. Konflik unik saat penyimpanan dilaporkan sebagai duplikat.
- Gangguan infrastruktur menghentikan proses dengan penjelasan bahwa sebagian baris mungkin sudah tersimpan. Unggah ulang aman karena data yang sudah masuk dilewati.
- Tampilkan status proses, ringkasan, serta tabel masalah dengan pagination, bukan hanya toast.
- Contoh pesan: **“Baris 8 — Siti: NIP sudah digunakan. Pegawai dilewati.”**
- Contoh pesan: **“Baris 12 — Budi: Jabatan belum tersedia. Tambahkan pada Master Jabatan lalu impor ulang.”**

## Batas implementasi

- Tidak mengubah skema database.
- Tidak menyediakan pembuatan jabatan otomatis, pembaruan pegawai lama, riwayat impor permanen, atau perintah Artisan. Ekspor ditambahkan pada revisi UI/UX di bawah.
- Laporan tersedia selama halaman tetap terbuka. File unggahan tidak disimpan permanen.
- Gunakan komponen dan konvensi aplikasi yang sudah tersedia.

## Pengujian otomatis nonvisual

- PHPUnit: autentikasi, prasyarat jabatan pada unduh/impor, template nyata beserta referensi dan konfigurasi dropdown, serta unggahan nyata.
- Uji preservasi identitas termasuk nol di depan, tanggal Excel/teks, status, STR/SIP, formula, baris kosong, file rusak, header salah, dan batas unggahan.
- Uji duplikat setiap kolom unik terhadap database dan dalam file, jabatan hilang/ambigu, serta campuran baris valid dan gagal.
- Pastikan baris gagal tidak tersimpan, data lama tidak berubah, dan unggah ulang tidak menambah duplikat.
- Jalankan tes pegawai dan impor yang terdampak, pemeriksaan TypeScript, build frontend, serta Pint untuk perubahan PHP.
- Tidak menjalankan uji visual otomatis. Pengujian visual dilakukan pengguna secara mandiri menggunakan checklist berikut.

## Checklist pengujian visual manual

Gunakan database pengujian dengan data sintetis. Untuk setiap skenario, catat hasil aktual atau temuan di bawahnya jika berbeda dari hasil yang diharapkan.

### Akses dan prasyarat jabatan

- [x] Buka Halaman Pegawai dan pilih Impor Excel. **Harapan:** halaman impor terbuka; navigasi kembali menuju daftar pegawai berfungsi.
- [x] Buka impor saat master jabatan kosong. **Harapan:** pesan prasyarat tampil jelas; unduh dan unggah nonaktif; tombol tambah jabatan tersedia.
- [x] Pilih tombol tambah jabatan, tambahkan satu jabatan, lalu buka ulang halaman impor. **Harapan:** unduh dan unggah aktif.

### Template dan dropdown Excel

- [x] Unduh template dengan satu jabatan. **Harapan:** file `.xlsx` terbuka tanpa peringatan perbaikan; sheet Pegawai dan Petunjuk tersedia, Referensi tersembunyi.
- [x] Pilih sel Jabatan pada baris pertama pengisian. **Harapan:** dropdown berisi satu jabatan yang tersedia dan pilihan dapat dipilih.
- [x] Unduh template dengan beberapa jabatan. **Harapan:** dropdown memuat daftar sesuai master, terurut alfabetis, tanpa ID/UUID.
- [x] Pilih Jabatan pada baris pengisian ke-1.000. **Harapan:** dropdown tetap tersedia dan berfungsi.
- [x] Ketik nama jabatan di luar pilihan. **Harapan:** Excel menampilkan pesan penolakan dan petunjuk memilih daftar.
- [x] Periksa template di Microsoft Excel dan LibreOffice bila tersedia. **Harapan:** dropdown, label, petunjuk, dan format sel dapat digunakan.
- [x] Periksa kolom dan sheet Petunjuk. **Harapan:** kolom wajib, pilihan status/jenis kelamin, format tanggal, serta aturan STR/SIP mudah dipahami.
- [x] Isi NIK/NIP dengan nol di depan dan simpan/buka ulang file. **Harapan:** seluruh digit tetap utuh, tidak menjadi notasi ilmiah.
- [x] Tambahkan jabatan setelah template diunduh. **Harapan:** template lama tetap berisi snapshot lama; template yang diunduh ulang memuat jabatan baru.

### Unggah dan status proses

- [x] Kirim tanpa file, lalu coba tipe file selain `.xlsx` dan file melebihi 2 MB. **Harapan:** pesan kesalahan mudah dipahami dan tidak ada data tersimpan.
- [x] Pilih file lalu ganti dengan file lain sebelum mengirim. **Harapan:** nama file yang akan diproses sesuai pilihan terbaru.
- [x] Kirim file valid. **Harapan:** status proses terlihat dan tombol kirim mencegah pengiriman berulang selama request berjalan.
- [x] Unggah file rusak, header salah, sheet wajib hilang, atau lebih dari 1.000 baris data. **Harapan:** pesan menjelaskan penyebab dan tidak ada data tersimpan.

### Hasil dan pesan per baris

- [x] Impor file yang seluruhnya valid. **Harapan:** jumlah berhasil sesuai baris tidak kosong dan tidak ada masalah palsu.
- [x] Impor file yang seluruhnya tidak valid. **Harapan:** jumlah berhasil nol dan alasan setiap baris tersedia.
- [x] Impor file yang seluruhnya duplikat. **Harapan:** semua dilewati dengan alasan, tanpa perubahan data lama.
- [x] Impor campuran baris valid, duplikat, tidak valid, dan kosong. **Harapan:** baris valid tersimpan; baris kosong diabaikan; ringkasan sesuai hasil.
- [x] Periksa duplikat NIK, NIP, email, STR, dan SIP, termasuk duplikat dalam file. **Harapan:** pesan menyebut kolom penyebab dengan bahasa yang jelas.
- [x] Ubah master sehingga jabatan dalam template lama tidak tersedia, lalu unggah. **Harapan:** baris dilewati dengan petunjuk memperbaiki master/pilihan jabatan.
- [x] Salin-tempel nama jabatan tidak valid untuk melewati dropdown. **Harapan:** server tetap menolak baris dengan pesan jabatan tidak tersedia.
- [x] Isi identitas tidak valid, tanggal tidak valid, atau STR/SIP tidak lengkap. **Harapan:** pesan menyebut kolom dan tindakan perbaikan yang relevan.
- [x] Cocokkan nomor baris laporan dengan file, termasuk setelah baris kosong. **Harapan:** nomor menunjukkan baris Excel yang benar.
- [x] Buat cukup banyak masalah untuk beberapa halaman laporan. **Harapan:** pagination bekerja dan rincian tetap terbaca.

### Data tersimpan dan pemulihan

- [x] Kembali ke daftar/detail pegawai setelah impor. **Harapan:** pegawai berhasil muncul dengan identitas, jabatan, status, dan tanggal sesuai file; baris gagal tidak muncul.
- [x] Unggah ulang file yang sama. **Harapan:** pegawai yang sudah tersimpan dilewati, tidak ada duplikat tambahan atau pembaruan data lama.
- [x] Simulasikan kegagalan jaringan saat unggah. **Harapan:** pesan kegagalan dan status tombol kembali jelas; pengguna dapat memeriksa hasil atau mencoba ulang.
- [x] Jika terjadi gangguan server setelah sebagian penyimpanan, periksa pesan dan coba ulang. **Harapan:** kemungkinan hasil parsial dijelaskan; unggah ulang tidak menduplikasi data yang sudah masuk.

### Tata letak dan aksesibilitas

- [x] Periksa halaman pada desktop dan layar seluler. **Harapan:** label, tombol, petunjuk, ringkasan, dan pesan panjang terbaca; tabel dapat diakses tanpa merusak tata letak.
- [x] Gunakan keyboard untuk navigasi halaman, pemilihan file, kirim, dan pagination. **Harapan:** fokus terlihat dan urutan navigasi masuk akal.
- [x] Periksa tampilan sebelum impor dan setelah hasil tanpa masalah. **Harapan:** keadaan kosong informatif dan tidak menampilkan tabel atau pesan kegagalan yang membingungkan.

## Hasil verifikasi implementasi

| Pemeriksaan                                                                                                                      | Hasil                                                                                                                                                                      |
| -------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `vendor/bin/phpunit tests/Feature/EmployeeImportTest.php tests/Feature/EmployeeStoreTest.php tests/Feature/EmployeeEditTest.php` | Lulus: 199 tes, 858 assertion. Termasuk impor 1.000 pegawai, template unduhan, kalender tanggal Excel 1900/1904, dan gangguan penyimpanan setelah sebagian baris berhasil. |
| `pnpm run types:check`                                                                                                           | Lulus.                                                                                                                                                                     |
| `pnpm exec vp lint resources/js/pages/employee/import.tsx resources/js/pages/employee/index.tsx`                                 | Lulus.                                                                                                                                                                     |
| `pnpm run build`                                                                                                                 | Lulus; terdapat peringatan ukuran chunk aplikasi di atas 500 kB.                                                                                                           |
| `vendor/bin/pint --dirty --format agent`                                                                                         | Lulus.                                                                                                                                                                     |
| Prettier untuk kedua halaman pegawai yang diubah                                                                                 | Lulus.                                                                                                                                                                     |
| Pengujian visual otomatis                                                                                                        | Tidak dijalankan sesuai permintaan.                                                                                                                                        |
| Checklist visual manual versi awal                                                                                               | Selesai diperiksa pengguna secara mandiri.                                                                                                                                 |

Versi terpasang: Laravel Excel 4.0.6 dan PhpSpreadsheet 5.10.0. Tidak ada perubahan versi paket lama atau migrasi database.

## Revisi UI/UX dan ekspor pegawai

- Petunjuk tersedia langsung di halaman impor melalui dialog, menggunakan sumber panduan backend yang sama dengan worksheet. Tombol unduh pada halaman membuka dialog dahulu; unduhan hanya dimulai melalui aksi Unduh Template di dalam dialog.
- Keterangan template memakai alert info biru, sedangkan master jabatan kosong memakai alert danger/destructive. Petunjuk tetap tersedia saat master kosong; template dan impor tetap diblokir.
- Upload mengadaptasi contoh `c-file-upload-3.tsx` dan hook yang tersedia: satu `.xlsx`, maksimal 2 MB, pilih atau drag-and-drop, nama/ukuran file, ganti/hapus, dan penolakan banyak file sekaligus. File lama dipertahankan jika pengganti tidak valid.
- Tombol utama memakai HugeIcons. Daftar pegawai menyediakan satu dropdown Impor/Ekspor dengan dua pilihan aksi.
- Ekspor menyediakan pilihan seluruh pegawai atau hasil pencarian/filter aktif di seluruh halaman. Hasil berupa satu `.xlsx` yang mengikuti kolom impor, label jabatan/status, format teks identitas, petunjuk, dan dropdown referensi.
- Ekspor lebih dari 1.000 baris tetap lengkap. Bagi file menjadi maksimal 1.000 baris dan 2 MB sebelum impor ulang. Pegawai tanpa jabatan tetap diekspor dengan jabatan kosong dan perlu dilengkapi sebelum impor.
- Tidak menambah dependensi atau mengubah skema database. Validasi dan proses penyimpanan impor tetap menggunakan mekanisme sebelumnya.

### Checklist visual manual revisi

Checklist lama di atas tetap menjadi catatan pengujian yang telah diselesaikan pengguna. Daftar berikut khusus perubahan terbaru dan belum dijalankan secara visual otomatis.

- [ ] Buka Petunjuk pada halaman impor, tutup, lalu buka kembali. **Harapan:** panduan dapat dibaca kapan saja tanpa memulai unduhan.
- [ ] Pilih Unduh Template pada halaman. **Harapan:** dialog terbuka terlebih dahulu dan file belum diunduh; hanya aksi Unduh Template di dalam dialog memulai unduhan.
- [ ] Tutup dialog melalui tombol Tutup, Escape, dan tombol silang. **Harapan:** tidak ada unduhan dan fokus kembali ke kontrol yang relevan.
- [ ] Baca panduan di dialog dan worksheet. **Harapan:** aturan kolom wajib, teks identitas, tanggal, STR/SIP, dropdown, batas unggahan, dan duplikat konsisten.
- [ ] Periksa dialog pada layar kecil atau tinggi layar terbatas. **Harapan:** isi panduan dapat digulir dan aksi unduh/tutup tetap dapat dijangkau.
- [ ] Periksa informasi Template Pegawai pada mode terang dan gelap. **Harapan:** alert berwarna biru lembut, ikon informasi jelas, dan isi mudah dibaca.
- [ ] Buka impor dengan master jabatan kosong. **Harapan:** alert merah lembut menjelaskan tindakan Tambah Jabatan, bukan kesan kerusakan aplikasi; unggah/unduh nonaktif tetapi Petunjuk tetap aktif.
- [ ] Pilih atau seret satu file `.xlsx`. **Harapan:** nama dan ukuran tampil jelas, termasuk nama yang panjang; file dapat dikirim melalui proses impor biasa.
- [ ] Pilih Ganti File, Hapus File, dan pilih kembali file yang sama. **Harapan:** pilihan dan payload sinkron, aksi hapus mengosongkan pilihan, dan pemilihan ulang berfungsi.
- [ ] Ganti file dengan tipe selain `.xlsx`, file di atas 2 MB, atau seret beberapa file sekaligus. **Harapan:** pesan bahasa Indonesia jelas dan file lama tetap dipertahankan.
- [ ] Mulai impor lalu coba memilih, mengganti, menghapus, atau menyeret file. **Harapan:** seluruh perubahan file diblokir selama pemrosesan dan laporan hasil tetap berfungsi.
- [ ] Periksa tombol utama dan dropdown Impor/Ekspor. **Harapan:** ikon konsisten, teks tetap terlihat, menu hanya berisi Impor Data Pegawai dan Ekspor Data Pegawai, tanpa tombol impor/ekspor terpisah yang berulang.
- [ ] Pilih ekspor seluruh pegawai saat pencarian/filter aktif. **Harapan:** satu file berisi seluruh data, melampaui pagination dan mengabaikan filter.
- [ ] Pilih ekspor sesuai pencarian/filter aktif. **Harapan:** file memuat semua hasil yang cocok pada seluruh halaman, dengan urutan sesuai daftar pegawai.
- [ ] Ekspor tanpa hasil atau tanpa data sama sekali. **Harapan:** workbook tetap dapat dibuka dengan header dan petunjuk, tanpa pegawai tambahan atau pesan perbaikan file.
- [ ] Periksa file ekspor di Excel/LibreOffice. **Harapan:** identitas dan nol di depan tetap utuh; nama jabatan/status, tanggal, petunjuk, dan dropdown terbaca. Teks yang menyerupai formula tetap tampil sebagai teks.
- [ ] Ekspor lebih dari 1.000 pegawai. **Harapan:** satu file berisi seluruh hasil tanpa pemotongan dan halaman menjelaskan batas impor ulang.
- [ ] Impor ulang hasil ekspor dalam batas ukuran/baris pada database pengujian setelah pegawai dihapus dan master jabatan tetap tersedia. **Harapan:** data tersimpan sesuai file; unggah ulang berikutnya melewati duplikat.
- [ ] Gunakan keyboard pada dropdown, dialog, upload, dan tombol. **Harapan:** fokus terlihat, urutan navigasi masuk akal, dan setelah dialog ekspor ditutup fokus kembali ke tombol Impor/Ekspor.
- [ ] Periksa daftar, halaman impor, dialog, dan laporan pada desktop/mobile serta mode terang/gelap. **Harapan:** tidak ada kontrol terpotong, pesan tidak terbaca, atau regresi pada alur impor sebelumnya.

### Verifikasi nonvisual revisi

| Pemeriksaan                                                              | Hasil                                                             |
| ------------------------------------------------------------------------ | ----------------------------------------------------------------- |
| PHPUnit: `EmployeeIndexTest`, `EmployeeImportTest`, `EmployeeExportTest` | Lulus: 110 tes, 1.132 assertion.                                  |
| Tes interaksi `employee-transfer.test.jsx` melalui `vp test run`         | Lulus: 9 tes. Tidak menggunakan browser atau screenshot.          |
| TypeScript                                                               | Lulus.                                                            |
| Lint file frontend yang terdampak                                        | Lulus.                                                            |
| Build frontend                                                           | Lulus; peringatan ukuran chunk aplikasi di atas 500 kB masih ada. |
| Pint                                                                     | Lulus.                                                            |
| Pengujian visual otomatis                                                | Tidak dijalankan sesuai permintaan.                               |
| Checklist visual revisi                                                  | Menunggu pemeriksaan manual pengguna.                             |
