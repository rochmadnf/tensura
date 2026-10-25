# Templat Surat MVP 1 — Proses Bisnis

Status: proses bisnis yang disepakati, diselaraskan dengan kondisi aplikasi pada 25 Oktober 2026. Tahap teknis 1–5 dan tambahan 4A sudah diimplementasikan; publikasi, siklus versi, serta cleanup menunggu tahap 6–7.

Dokumen ini menjadi acuan proses bisnis fitur Templat Surat. Rancangan fondasi database dan tujuh tahap implementasi beserta tahap tambahan 4A dan aturan test/commit ada di [letter-template-proposed-plan.md](letter-template-proposed-plan.md). Pola implementasi mengikuti [FEATURE_WORKFLOW.md](../../FEATURE_WORKFLOW.md) dan [LARAVEL_PATTERNS.md](../../LARAVEL_PATTERNS.md) yang sudah diperbarui.

## 0. Kondisi fitur saat ini

| Bagian | Sudah tersedia | Masih perlu dikerjakan untuk Templat Surat |
| --- | --- | --- |
| Menu | Templat Surat di Data Master terhubung ke `lt.index`; halaman daftar/tambah/kelola dasar tersedia | Kontrol pengelolaan tahap berikutnya |
| Domain templat | Sembilan tabel relasional, katalog 25 atribut Pegawai, fallback dan format tanggal per atribut, model, factory, enum, audit, identitas draft, daftar, konfigurasi variabel/penandatangan, DOCX sumber/contoh, validasi, generate uji/unduhan hasil, konfirmasi visual, dan test tahap 1–5/4A | Publikasi, perubahan versi, dan nonaktifkan |
| Master pendukung | Penomoran Surat, Pegawai/Jabatan, Daftar Referensi, opsi konfigurasi, validasi relasi, dan perlindungan hapus master terikat | Pemeriksaan kelayakan saat publikasi |
| Arsip | Arsip pusat, arsip/pemulihan Reference, serta penolakan arsip grup yang dirujuk templat | Operasi arsip draft templat sebagai status versi |
| Komponen bersama | Pencarian, tabel berhalaman, filter, formulir, pemilih opsi/tanggal, dan tombol aksi | Komposisi halaman serta konfigurasi dan operasi khusus templat |
| DOCX | Upload/ganti sumber privat, validasi ZIP/XML/placeholder, unduh sumber/contoh, generate/unduh hasil uji dan konfirmasi visual melalui PHPWord yang dipin | Publikasi dan cleanup terjadwal |
| Database dan test | PostgreSQL untuk aplikasi maupun test database; runner terisolasi | Test perilaku templat dan konkurensi yang relevan |

Fondasi awal diperiksa melalui kode, route, skema database aktif, dan dependency. Implementasi tahap 1–5 dan 4A diverifikasi dengan PHPUnit pada PostgreSQL disposable. Tahap 3 tidak menambah migration; skema aktif yang diperiksa sudah memiliki tabel templat tahap 1. Ketersediaan library atau komponen bersama belum membuktikan alur Templat Surat sudah berjalan. Menu Surat juga masih menggunakan tujuan `#`.

## 1. Tujuan dan akses

- Admin menyiapkan DOCX beserta konfigurasi formulir agar dapat digunakan berulang kali pada fitur Surat.
- Seluruh pengguna yang sudah login dapat mengelolanya sebagai admin operasional MVP 1.
- Menu **Templat Surat** tetap berada di grup **Data Master**.
- Fitur Surat dibahas setelah fitur Templat Surat selesai.

## 2. Alur pengelolaan

| Tahap | Kegiatan |
| --- | --- |
| 1. Identitas | Isi nama dan pilih klasifikasi, lalu simpan draft awal |
| 2. Konfigurasi | Atur variabel dan penandatangan; tampilkan placeholder yang dapat disalin ke DOCX |
| 3. DOCX | Upload, validasi, isi data contoh, generate hasil uji, lalu periksa secara visual |
| 4. Publikasi | Publikasikan agar templat tersedia untuk pembuatan surat |

- Nama templat wajib unik, dengan panjang 3–100 karakter.
- Draft awal cukup berisi nama dan klasifikasi. Alurnya mengikuti tambah Daftar Referensi: simpan identitas dahulu, kemudian lanjutkan konfigurasi.
- Klasifikasi dipilih dari master `LetterNumber` sebagai rujukan penomoran saat surat dibuat.
- Pada fitur Templat Surat belum ada alokasi nomor.

### DOCX contoh dan panduan untuk pergantian admin

- Setelah identitas templat baru disimpan, header kartu **DOCX Sumber** menyediakan satu aksi **Unduh DOCX Contoh** di samping Edit, tanpa duplikat di kartu lain. Panduan tetap dapat diunduh kembali oleh admin berikutnya, bukan hanya ditampilkan sekali setelah pembuatan.
- DOCX contoh dibuat berdasarkan konfigurasi versi yang sedang dikelola dan memuat nama templat, nomor versi, daftar variabel beserta label/jenis/wajib atau opsional, sumber pilihan, serta petunjuk penggunaan placeholder.
- Sertakan contoh penempatan placeholder dalam paragraf, satu baris contoh untuk setiap tabel berulang beserta pemetaan kolomnya, dan placeholder slot penandatangan yang diaktifkan. Gunakan data fiktif untuk penjelasan; tidak perlu menyertakan data pribadi pegawai.
- Jika draft awal belum memiliki konfigurasi variabel/penandatangan, berkas berisi identitas dan petunjuk melengkapi konfigurasi. Setelah konfigurasi disimpan, unduhan berikutnya mengikuti konfigurasi terbaru versi itu. Panduan versi historis menggunakan konfigurasi versi historis yang dipilih.
- Panduan menjelaskan cara menyesuaikan tata letak, mengunggah DOCX sumber, menjalankan validasi, generate uji, dan pemeriksaan visual sebelum publikasi. Nama variabel dalam bagian penjelasan ditulis tanpa sintaks placeholder agar tidak dianggap sebagai isian tambahan, terutama pada tabel berulang.
- DOCX contoh berfungsi sebagai dokumentasi dan bahan awal penyusunan DOCX sumber. Mengunduhnya tidak menggantikan proses validasi, hasil uji, konfirmasi visual, atau publikasi.

## 3. Variabel

Admin mengatur nama placeholder, label, jenis, dan apakah wajib diisi.

| Jenis | Penggunaan |
| --- | --- |
| Teks pendek | Nama atau isian singkat |
| Teks panjang | Alamat atau keterangan |
| Tanggal | Isian tanggal |
| Combobox tunggal | Memilih satu item |
| Combobox multiple | Memilih beberapa item untuk mengisi baris tabel berulang |

Combobox tunggal maupun multiple dapat menggunakan sumber:

- Referensi: grup/item di tabel aktif. Data yang sudah dipindahkan ke Arsip tidak ditawarkan; pemulihan mengikuti fitur Reference yang sudah ada. MVP 1 tidak menambah status aktif pada master referensi.
- Pegawai aktif berdasarkan `AsnStatusEnum::AKTIF`, bukan status kontrak atau kelengkapan STR/SIP.

Pilihan referensi mengikuti urutan master `sort_order`, lalu `id`. UUID digunakan sebagai nilai publik pilihan; relasi database tetap menggunakan id numerik. Referensi tunggal menghasilkan isi item (`content`). Baris multiple mengikuti urutan pemilihan admin, bukan urutan master: pilihan B lalu A menghasilkan B lalu A untuk Pegawai maupun Referensi. Pegawai tunggal memakai satu pilihan orang untuk seluruh keluarga token atributnya; token dasar merupakan alias nama lengkap, misalnya `${pegawai}` dan `${pegawai_nama}`. Katalog serta pemetaan token lengkap mengikuti kontrak tahap 4A pada rencana teknis.

### Tabel berulang

Admin memilih kolom dan nama placeholder berdasarkan data existing.

| Sumber | Data yang tersedia |
| --- | --- |
| Pegawai | Nomor urut dan seluruh katalog 25 atribut Pegawai: identitas/gelar, NIK/NIP, jenis kelamin, email, jabatan/kelompok, pangkat/golongan, tanggal bergabung, masa kerja, status pegawai/kontrak, serta STR/SIP |
| Referensi | Nomor urut dan isi item |

Nama pegawai beserta gelar mengikuti susunan `front_title`, `name`, dan `back_title` seperti Resource Pegawai saat ini; jabatan berasal dari relasi `jobTitle`. Atribut kosong pada baris terpilih menghasilkan teks kosong; tidak ada fallback multiple. Katalog backend menjadi sumber pilihan atribut, bukan serialisasi seluruh kolom model. ID internal, UUID, dan timestamps tidak menjadi isian surat.

Contoh Surat Tugas Perjalanan:

| No | Nama | NIP | Jabatan |
| --- | --- | --- | --- |
| `${pegawai_no}` | `${pegawai_nama}` | `${pegawai_nip}` | `${pegawai_jabatan}` |

- Admin menyiapkan satu baris contoh dalam DOCX.
- PHPWord menggandakan baris sesuai jumlah pilihan dan mengisi setiap kolom.
- Nomor urut dibuat otomatis.
- Belum ada isian tambahan per baris atau perhitungan transaksi seperti qty, amount, atau total.
- Contoh 2–5 pegawai bukan batas wajib.

### Atribut Pegawai, fallback, dan format tanggal

- Penandatangan dan variabel Pegawai tunggal memakai katalog yang sama dengan kolom berulang. Contoh token: nama, NIP/NIK, pangkat, golongan, masa kerja, serta seluruh tanggal dan nomor STR/SIP. Setiap atribut pada satu variabel/slot mengambil data dari orang yang sama.
- Masa kerja ditampilkan sampai hari dengan bagian nol dihilangkan, misalnya `5 tahun 3 hari` atau `12 hari`. Komponen tahun, sisa bulan, dan sisa hari juga tersedia sebagai angka; `0` tetap nilai sah. Acuan adalah tanggal pemrosesan dalam zona waktu aplikasi; data kosong mengikuti fallback, tanggal bergabung hari ini/masa depan menghasilkan `0 hari`.
- Setiap atribut penandatangan dan variabel Pegawai tunggal memiliki fallback teks literal, default kosong. Variabel tunggal non-Pegawai memiliki satu fallback literal. Fallback bukan nilai awal form dan tidak menggantikan kewajiban pengisian variabel wajib.
- Multiple tidak memiliki fallback. Pada generate, pilihan kosong untuk multiple opsional menghapus baris contoh, mempertahankan header/baris statis; tabel dihapus hanya jika tidak memiliki isi lain. Multiple wajib tetap memerlukan pilihan.
- Format setiap atribut tanggal dapat diatur admin sendiri pada slot, variabel Pegawai tunggal, atau kolom berulang, dengan default `d-m-Y`. Pola mengikuti format tanggal PHP, tetapi nama hari/bulan memakai bahasa aplikasi (`config('app.locale')`, `id`). Tombol informasi di samping input menjelaskan kode, literal, dan contoh; contoh format berasal dari backend.
- Konfigurasi, resolver, panduan, serta validasi sudah tersedia pada tahap 4A. Penggantian token dengan data pilihan sudah tersedia melalui generate hasil uji tahap 5. Penggantian dilakukan satu kali dan mempertahankan sintaks placeholder yang berasal dari isian sebagai teks literal.

## 4. Penandatangan

- Form menyediakan satu slot secara default; admin dapat menonaktifkannya jika tidak diperlukan.
- Kandidat dan penandatangan yang digunakan untuk pengujian atau surat baru wajib berasal dari pegawai dengan status `AsnStatusEnum::AKTIF`.
- Jika kandidat yang sudah dikonfigurasi berubah menjadi tidak aktif, tampilkan peringatan kepada admin pada halaman kelola templat, termasuk nama pegawai dan slot terkait, dengan arahan untuk mengganti atau menghapus kandidat tersebut dari konfigurasi.
- Publikasi ditolak selama masih ada kandidat tidak aktif dalam konfigurasi. Kandidat tidak aktif tidak dapat dipilih atau digunakan untuk generate uji maupun surat baru, meskipun sebelumnya aktif atau masih tersimpan pada formulir lama. Status harus diperiksa kembali di backend saat operasi dijalankan.
- Jangan menghapus kandidat secara otomatis atau menimpa konfigurasi versi historis. Perbaikan oleh admin mengikuti aturan versi: edit versi yang belum digunakan atau buat draft versi baru jika versi asal sudah digunakan.
- Templat dapat memiliki beberapa slot, masing-masing dengan kandidat.
- Saat generate tersedia, setiap slot yang tokennya digunakan harus diisi orang berbeda; slot tanpa token tidak meminta pilihan.
- Satu slot menyediakan `${penandatangan_nama}`, `${penandatangan_pangkat}`, dan seluruh atribut katalog; `${penandatangan}` tetap alias nama lengkap.
- Beberapa slot memakai keluarga `${penandatangan_1_nama}`, `${penandatangan_2_pangkat}`, dan seterusnya, dengan token dasar bernomor tetap alias nama lengkap.
- Slot aktif wajib memiliki kandidat valid, tetapi seluruh tokennya opsional. DOCX boleh hanya menggunakan atribut non-nama atau tidak memakai token slot tersebut sama sekali.

## 5. Validasi dan pengujian DOCX

- Ukuran maksimal DOCX sumber default 10 MB, dibaca dari config.
- Placeholder yang tidak dikenal ditolak.
- DOCX boleh menggunakan sebagian variabel yang dikonfigurasi.
- Token slot penandatangan bersifat opsional; token yang dipakai wajib sesuai katalog dan konfigurasi. Benturan token otomatis dengan variabel/kolom ditolak.
- Pengujian tahap 4 membuktikan kemampuan PHPWord yang dipin untuk pengulangan baris, penggantian teks panjang/paragraf/header/footer, serta placeholder terpecah antar text run. Endpoint generate uji sudah tersedia pada tahap 5.
- Alur upload, validasi, unduhan sumber/contoh, dan fixture DOCX nyata sudah tersedia. Aturan struktur yang didukung/ditolak dirinci pada kontrak tahap 4 rencana teknis; validasi struktur belum menggantikan generate uji dan pemeriksaan visual.
- Struktur berbahaya atau tidak didukung harus ditolak dengan keterangan jelas.
- Publikasi memerlukan hasil uji generate dan konfirmasi pemeriksaan visual admin setelah membuka DOCX. Generate, unduhan hasil, serta pencatatan konfirmasi sudah tersedia; operasi publikasi menyusul tahap 6.
- Form data contoh hanya meminta variabel dan slot yang tokennya digunakan. Penandatangan yang dipakai wajib kandidat aktif dan berbeda antar-slot. Pilihan Referensi harus berasal dari grup yang dikonfigurasi; pilihan Pegawai harus aktif. Isian wajib tidak dapat diganti fallback.
- Hasil uji berlaku 24 jam dan terikat pada hash sumber, konfigurasi, serta berkas hasil. Generate sukses mengganti hasil sebelumnya dan membatalkan konfirmasi; token hasil lama tidak dapat dipakai mengonfirmasi atau mengunduh hasil baru. Sumber/hasil yang hilang atau berubah, konfigurasi/locale yang berubah, dan expiry membuat bukti tidak layak dipakai.
- Perubahan nama/jabatan/nilai master setelah generate tidak membatalkan hasil yang masih berlaku: berkas menyimpan nilai saat dibuat. Status aktif penandatangan terpilih tetap diperiksa saat generate, unduh, dan konfirmasi; publikasi juga wajib memeriksa kandidat sesuai aturan sebelumnya.
- Setelah mengunduh dan membuka DOCX, admin mencentang pernyataan sudah memeriksa isi/tata letak lalu mengonfirmasi. Waktu serta nama/UUID pelaku dicatat. Ini merupakan pernyataan admin, bukan pemeriksaan tata letak otomatis oleh aplikasi.
- Perubahan konfigurasi draft mempertahankan DOCX existing, tetapi membatalkan hasil validasi/uji dan konfirmasi visual terkait.
- Berkas divalidasi dan diuji ulang; upload ulang diperlukan jika DOCX sudah tidak cocok.

## 6. Versi dan timeline

Seluruh versi tetap terhubung ke satu identitas templat. Timeline versi ditampilkan pada halaman kelola.

| Kondisi | Perilaku perubahan |
| --- | --- |
| Draft | Edit versi yang sama |
| Publish, belum digunakan surat | Edit versi yang sama; publikasi dibatalkan, kemudian validasi, uji, dan publish ulang |
| Publish, sudah digunakan minimal satu surat | Pertahankan versi asal; perubahan dibuat dalam draft versi baru |
| Versi baru dipublikasikan | Digunakan untuk surat baru; surat lama tetap menggunakan versi asal |

- Maksimal satu draft berjalan per templat.
- Hanya satu versi terbit aktif untuk pembuatan surat baru.
- DOCX dan konfigurasi versi yang sudah digunakan tetap tersedia untuk generate ulang surat historis.
- Konfigurasi historis mencakup variabel, pemetaan tabel, klasifikasi, penandatangan, fallback, serta format tanggal per atribut/kolom. Tahap 6 menyalin seluruh pengaturan ke draft baru tanpa mengubah versi asal.
- Draft dapat diarsipkan dengan status Diarsipkan pada versi templat; baris versi dan konfigurasinya tetap disimpan dalam domain templat.
- Templat dapat dinonaktifkan tanpa menghapus riwayat.
- Tidak ada hapus permanen.
- Operasi penting dicatat dalam audit.
- Master klasifikasi, grup referensi, dan pegawai kandidat tidak dapat dihapus selama masih dirujuk, termasuk oleh versi historis atau arsip.

### Hubungan dengan Arsip dan perubahan master

- Arsip pusat saat ini menyimpan snapshot grup/item Reference, lalu menghapus record dari tabel aktif. Mengarsipkan grup yang dirujuk versi templat harus ditolak dengan pesan jelas dan tanpa meninggalkan paket arsip baru. Perlindungan ini diterapkan pada tahap 3, sebelum snapshot dibuat.
- Penghapusan klasifikasi dan pegawai tetap permanen; tahap 3 menolak penghapusan ketika masih dirujuk templat dengan pesan yang jelas. Record lain yang tidak dirujuk tetap mengikuti perilaku fitur masing-masing.
- Status Diarsipkan pada versi templat berbeda dari snapshot Arsip pusat. Rencana ini belum mencakup pemindahan templat ke Arsip pusat, pemulihan melalui halaman Arsip, atau hapus permanen templat.
- Menyimpan konfigurasi dan DOCX versi asal belum membekukan isi master. Nama/gelar/jabatan pegawai, isi referensi, dan nama/kode klasifikasi masih dapat berubah. Agar generate ulang surat historis mempertahankan data asal, kontrak snapshot nilai terpilih harus dirinci pada fitur Surat nanti.

## 7. Konfigurasi terpusat

Rencanakan `config/letter-templates.php` sebagai sumber aturan teknis.

| Kelompok | Pengaturan |
| --- | --- |
| Upload | Ukuran maksimal DOCX sumber; default 10 MB |
| Storage | Disk privat dan path sumber, hasil uji, serta berkas sementara |
| Hasil uji | Masa simpan 24 jam, maksimal 20 MB, hash hasil, dan token pemeriksaan terbaru |
| Keamanan ZIP | Batas jumlah entry, total ukuran dekompresi, dan rasio kompresi |
| Variabel | Placeholder sesuai profil, teks pendek 255 karakter, teks panjang 10.000 karakter, maksimal 100 pilihan per multiple/500 baris total |
| Generate | Anggaran proses 30 detik dengan checkpoint dan batas ekspansi XML sesuai profil DOCX |

Usulan direktori di disk privat:

```text
letter-templates/
├── sources/
├── previews/
└── temporary/
```

- Backend menggunakan config yang sama untuk validasi, generate, dan cleanup.
- Frontend menerima batas yang diperlukan dari backend agar tidak menduplikasi nilainya.
- `env()` hanya dibaca melalui file config untuk pengaturan yang berbeda antar lingkungan.
- Path berkas ditentukan aplikasi, bukan input admin.
- DOCX sumber historis tidak ikut cleanup hasil uji atau berkas sementara.
- Batas upload/ZIP/XML/placeholder sudah ditetapkan pada `config/letter-templates.php` dan dirinci dalam kontrak tahap 4 rencana teknis. Batas generate dan masa simpan hasil uji sudah ditetapkan tahap 5; mekanisme cleanup terjadwal menyusul tahap 7.
- File config, penyimpanan privat sumber/hasil uji, dan berkas contoh sementara sudah tersedia. Cleanup terjadwal hasil uji/berkas sementara mengikuti tahap 7.
- Pengaturan dokumen fitur Surat akan dibahas pada fitur tersebut.

## 8. Halaman daftar dan kelola

- Kolom daftar: Nama, Klasifikasi, Versi Aktif, Status Aktif/Nonaktif, penanda Ada Draft, dan Aksi.
- Pencarian berdasarkan nama templat serta kode/nama klasifikasi pada versi yang ditampilkan. Implementasi tahap 2 memilih versi aktif, lalu draft berjalan, lalu versi terbaru bila keduanya tidak ada; aturan yang sama dipakai kolom, pencarian, dan filter.
- Pencarian mengikuti perilaku bersama: trim, minimum dua karakter, tanpa membedakan kapital, dan memperlakukan karakter khusus sebagai teks literal.
- Urutan default nama A–Z dengan id sebagai penentu akhir agar pagination stabil.
- Filter status dan klasifikasi, masing-masing pilihan tunggal.
- Paginasi server mengikuti pola repository: default 10, pilihan 5/10/15/20/50/100, serta mempertahankan keyword dan filter.
- Halaman kelola memuat empat tahap pengelolaan serta timeline versi yang menyusul tahap 6. Kartu Generate dan Pemeriksaan DOCX memuat isian sesuai token, urutan baris pilihan, unduhan hasil, status/expiry, dan konfirmasi visual; operasinya diblokir selama kartu lain sedang diedit atau request berjalan. Kartu awal dalam mode baca; hanya satu editor kartu/item dapat aktif. Simpan/Batal hanya tampil saat edit, dan Batal memulihkan nilai/galat/file tanpa navigasi. Form tambah identitas tetap langsung dapat diisi. Tombol naik/turun mengikuti Reference, hapus memakai destructive, dan tombol berteks memiliki ikon di depan.

### Perapihan editor setelah tahap 5

- Urutan kartu: Identitas Templat, Konfigurasi Formulir Surat, Penandatangan, DOCX Sumber, lalu Generate dan Pemeriksaan DOCX. Identitas tidak diduplikasi dalam ringkasan lain dan tetap tampil hanya-baca bila tidak ada draft.
- Konfigurasi Formulir Surat berbentuk tabel Nomor urut, Nama Placeholder, Label Variabel, Jenis Variabel, Fallback teks, dan Aksi. Naik/turun, Edit, dan Hapus berlaku per variabel; perubahan langsung disimpan per item. Edit baris menempatkan Wajib/Opsional di samping jenis. Rincian sumber, referensi, pilihan multiple, dan kolom berulang terbuka di bawah item tersebut.
- Penandatangan memiliki kartu tersendiri dengan kandidat dan Edit/Hapus per slot. Menambah variabel/slot hanya membuka baris baru lokal; Batal tidak membuat atau menghapus record server. Hapus memerlukan konfirmasi, termasuk pemberitahuan bahwa nomor/token slot dapat berubah.
- Atribut Pegawai disusun sebagai tabel Atribut, Placeholder, Fallback teks, Format tanggal, dan Aksi. Tanpa naik/turun, tambah, atau hapus atribut; Edit hanya untuk fallback, serta format dan bantuan pada atribut tanggal. Bedakan Nama beserta gelar dan Nama tanpa gelar. Token tidak dapat diedit dan pemetaan nama tidak berubah.
- Simpan setiap pemilik sebelum mengatur atribut. Edit variabel/kandidat tidak menimpa fallback atribut terbaru. Endpoint per item memeriksa namespace seluruh versi di dalam transaksi; kandidat slot lain yang sudah tidak aktif tidak memblokir perubahan variabel/atribut. Semua mutasi konfigurasi membatalkan bukti pemeriksaan tanpa menghapus DOCX sumber atau konfigurasi item lain.
- Galat mempertahankan editor dan input. Batal/Escape memulihkan tampilan tersimpan tanpa navigasi; Simpan/Batal hanya tampil saat edit. Pertahankan fokus/scroll dan key unik kartu/baris. Tabel dapat digeser mendatar pada layar sempit. Generate/validasi dan edit lain diblokir selama ada editor/request aktif.

### Komponen dan interaksi yang digunakan kembali

- Daftar memakai `SectionData`, `ResourceDataTable`, dan kolom khusus fitur berbasis TanStack Table v9. Filter memakai `DataFilterDialog` dan `OptionSelect`; keputusan filter tunggal tetap berlaku meskipun komponen mendukung pilihan ganda.
- Gunakan pencarian bersama melalui `SectionData`/`useListingSearch` dengan debounce 500 ms, submit/reset, retry, dan sinkronisasi Back/Forward. Perubahan keyword/filter kembali ke halaman pertama; reset filter mempertahankan keyword dan ukuran halaman.
- Pembaruan daftar memakai `buildListingQuery`, partial reload `resources`/`filters`, serta loading tabel tanpa overlay modal yang mengambil fokus pencarian.
- Form memakai `FormCard`, `FormActions`, `useValibotForm`, dan schema `defineRequest`, dengan Form Request backend sebagai validasi utama. `OptionSelect` melayani pilihan tunggal/multiple; `DatePickerInput` melayani tanggal contoh dengan tampilan Indonesia dan nilai ISO.
- Gunakan `TableActionButton` untuk aksi ikon, feedback melalui wrapper `gooeyToast`, dan route helper Wayfinder dari `@/routes/lt`. Route helper baru tersedia setelah route dibuat dan digenerate; konfigurasi Vite saat ini tidak menghasilkan helper controller `@/actions` atau `.form()`.
- Operasi publikasi, arsip draft, dan nonaktifkan memerlukan aturan serta konfirmasi domain sendiri. Jangan memakai alur hapus permanen CRUD untuk menggantikannya.
- Jangan menampilkan tautan Arsip pusat melalui `archiveUrl` hanya karena versi templat memiliki status Diarsipkan. Tujuan arsip templat belum diimplementasikan.

## 9. Ketentuan implementasi

| Bagian | Keputusan |
| --- | --- |
| Model / domain | `LetterTemplate` / `MasterData` |
| URL | `/letter-templates` |
| Binding / alias route | `letterTemplate` menggunakan UUID / `lt` |
| Nama halaman | Templat Surat, Tambah Templat Surat, Ubah Templat Surat |
| Menu / pageId | UUID existing `a3063dc3-7414-4385-bf3f-0cf0742e3980` |
| Database aplikasi / test | PostgreSQL 18 / PostgreSQL dengan database disposable; SQLite bukan target test |
| Acuan implementasi | JobTitle untuk CRUD, Reference untuk simpan identitas lalu kelola, Employee untuk relasi/filter, serta komponen bersama; kontrak mengikuti `FEATURE_WORKFLOW.md` dan `LARAVEL_PATTERNS.md` |
| Seeder | Tidak diperlukan |
| Changelog | Ditambahkan dalam bahasa Indonesia saat fitur diimplementasikan |
| Penyimpanan konfigurasi versi | Tabel relasional terpisah, bukan JSON untuk variabel, kolom, slot, dan kandidat |
| PHPWord | Sudah terpasang; ikuti pin `dev-master#91a1d0d978ff2cff382134c5d0f80c4a03dffd2e` pada `composer.json` |

Route identitas/daftar/konfigurasi/DOCX `lt.*`, binding `letterTemplate`, model, dan halaman dasar sudah tersedia. Semua route tersebut memerlukan autentikasi; relasi versi/variabel/slot harus dibatasi ke templat induknya.

## 10. Verifikasi dan rincian yang masih terbuka

Implementasi dibagi menjadi tujuh tahap teknis: fondasi database/domain; identitas/daftar; variabel/penandatangan; upload/validasi DOCX; generate/konfirmasi; publikasi/versi; serta cleanup/integrasi. Setiap tahap diselesaikan dan di-commit setelah test PHPUnit tahap tersebut beserta regresi yang terdampak lulus. Test browser dan pemeriksaan visual dilakukan setelah seluruh tahap implementasi selesai, menggunakan Chromium yang sudah disediakan pengguna. Rincian cakupan, kriteria kelulusan, dan pencatatan commit mengikuti rencana teknis. Tahap tambahan 4A sudah melengkapi katalog, fallback, format tanggal, dan UX sebelum tahap generate. Konfirmasi visual DOCX oleh admin tetap merupakan persyaratan proses publikasi, terpisah dari jadwal pengujian browser/visual oleh pengembang.

Implementasi perlu membuktikan alur empat tahap, invalidasi hasil uji, keunikan dan isolasi versi, penolakan penghapusan/arsip master yang dirujuk, UUID dan autentikasi, serta pencarian/filter/pagination. Verifikasi juga peringatan kandidat tidak aktif, penolakan publikasi, dan penolakan penggunaan penandatangan yang menjadi tidak aktif setelah formulir dibuka. Jalankan test database melalui runner PostgreSQL terisolasi; uji konkurensi antarkoneksi secara khusus dan laporkan bila belum dijalankan. Verifikasi tahap 5 beserta seluruh regresi Templat Surat lulus 265 test dan 1.753 assertion pada PostgreSQL disposable. Migration tahap 4A dan metadata hasil uji tahap 5 sudah diterapkan pada database aplikasi lokal. Delapan test frontend terarah untuk urutan pilihan, key kartu, dan transisi form setelah validasi juga lulus. Perintah serta commit dicatat pada tabel progres rencana teknis. Pemeriksaan TypeScript, formatter, lint terkait, dan build frontend lulus; browser/visual, test frontend selain regresi terbatas tersebut, serta konkurensi belum dijalankan sesuai jadwal.

Perapihan editor 5A berikutnya lulus 282 test PHPUnit/1.873 assertion dan 24 test frontend terarah, beserta TypeScript, lint terkait, Pint, dan build produksi. Browser/screenshot belum dapat dijalankan karena browser atau alat otomasi tidak tersedia pada sesi tersebut. Pemeriksaan manual tampilan/keyboard/fokus tetap diperlukan; visual DOCX dan konkurensi mengikuti tahap akhir. Tidak ada perubahan skema atau dependency pada perapihan ini.

Sebelum pekerjaan terkait dimulai, rincikan:

- Endpoint operasi, props/payload/error, dan aturan publikasi/perubahan versi/arsip/nonaktifkan tahap 6; kontrak generate/unduh hasil/konfirmasi tahap 5 sudah dirinci pada rencana teknis.
- Jadwal dan mekanisme cleanup hasil uji kedaluwarsa/yatim serta runner konkurensi tahap 7; timeout, expiry, dan batas hasil uji sudah ditetapkan tahap 5.
- Snapshot surat historis nanti harus mempertahankan urutan pilihan, atribut, tanggal acuan masa kerja, serta locale/format efektif. Keluaran Referensi/Pegawai, fallback, format, dan masa kerja sudah diterapkan pada generate uji; tidak perlu mengulang keputusan tersebut.
- Rincian publikasi saat seluruh prasyarat terpenuhi; perubahan nilai master tidak membatalkan hasil uji yang masih berlaku, tetapi status aktif penandatangan tetap wajib diperiksa. Pemilihan klasifikasi daftar sudah dirinci pada bagian 8.
- Snapshot nilai surat historis pada fitur Surat, termasuk atribut Pegawai, tanggal acuan masa kerja, serta locale/format efektif; tanpa menambahkan tabel Surat pada tahap ini.

Alokasi nomor, pembuatan surat sebenarnya, revisi surat, dan penyerahan kepada pemohon belum masuk pekerjaan ini. Rincian teknis yang belum diputuskan tetap harus dirinci sebelum implementasi terkait.
