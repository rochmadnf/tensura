# Rencana Teknis Bertahap Templat Surat MVP 1

Status: implementasi bertahap; tahap 1–4 selesai; tahap tambahan 4A serta tahap 5–7 belum dimulai. Tahap 4A menjadi prasyarat tahap 5. Ditinjau ulang pada 25 Oktober 2026 terhadap kode, route, skema database aktif, pola aplikasi terbaru, dan keputusan lanjutan pengguna. Fondasi database dan pembagian pekerjaan ditetapkan di sini; rincian kontrak yang masih terbuka tercatat pada bagian 6.

Acuan proses bisnis: [letter-template-management-probis.md](letter-template-management-probis.md). Pilihan tabel relasional dan pemblokiran penghapusan master yang dirujuk, termasuk oleh versi historis atau arsip, sudah disepakati. Rincian kolom dan constraint di bawah merupakan rancangan teknis terakhir.

Keputusan lanjutan pada tahap 4A memperbarui cakupan atribut Pegawai, kewajiban token penandatangan, fallback, format tanggal, dan UX halaman kelola. Jika berbeda dengan batas atribut atau kewajiban placeholder pada dokumen proses bisnis sebelumnya, gunakan keputusan terbaru dalam rencana ini. Catatan kontrak tahap 1–4 di bagian 5 mempertahankan riwayat implementasi; perubahan tahap 4A belum diterapkan dan belum memiliki bukti pengujian.

## 1. Ringkasan

Fondasi tahap 1 memakai tujuh tabel relasional; tahap 4A menambah dua tabel pengaturan atribut Pegawai serta kolom fallback/format pada tabel existing. Identitas templat tetap satu; DOCX dan seluruh konfigurasi berada pada versi masing-masing. Migration menggunakan Schema Builder Laravel dengan target PostgreSQL 18 untuk aplikasi dan pengujian database. SQLite bukan lagi target pengujian repository.

### Fondasi saat peninjauan sebelum implementasi

| Bagian | Kondisi saat peninjauan | Dampak pada rencana |
| --- | --- | --- |
| Templat Surat | Menu sudah ada dengan URL `#`; belum ada route, model, tabel, halaman, atau test fitur | Seluruh implementasi domain templat masih perlu dikerjakan |
| Penomoran Surat | `App\Models\LetterNumber`, klasifikasi berkode string, serta counter halaman/urutan | Gunakan sebagai relasi klasifikasi; jangan mengubah counter dari fitur templat |
| Pegawai | `Employee`, relasi `jobTitle`, dan status `AsnStatusEnum` | Pilihan baru hanya pegawai dengan status `AKTIF`; tahap 4A memetakan seluruh atribut bisnis existing dan masa kerja melalui satu katalog bersama |
| Daftar Referensi | Grup/item dengan UUID, urutan `sort_order`, dan alur arsip/pemulihan | Pilih grup/item dari tabel aktif; arsip grup yang dirujuk versi templat harus diblokir |
| Arsip pusat | Snapshot `Archive`/`ArchiveRecord`; handler saat ini hanya Reference | Status Diarsipkan pada versi templat tetap berada dalam tabel versi; belum ada integrasi arsip pusat untuk templat |
| Infrastruktur bersama | `Searchable`, komponen list/form, Wayfinder, runner PostgreSQL | Gunakan fondasi yang ada; rincian mengikuti [LARAVEL_PATTERNS.md](../../LARAVEL_PATTERNS.md) dan [FEATURE_WORKFLOW.md](../../FEATURE_WORKFLOW.md) |
| PHPWord | Terpasang pada commit `91a1d0d978ff2cff382134c5d0f80c4a03dffd2e` | Tidak perlu memasang dependency baru; kemampuan DOCX tetap perlu dibuktikan dengan test |

Daftar ini mempertahankan kondisi sebelum implementasi; hasil tahap 1–4 tercatat pada tabel progres. Fitur Surat juga belum tersedia; menu masih menggunakan URL `#`.

## 2. Tabel dan relasi

Semua entitas direncanakan memakai primary key numerik, UUID publik, dan timestamps. Foreign key memakai id numerik; Resource dan URL memakai UUID. Gunakan constraint unik UUID seperti master Reference terbaru. Nama enum mengikuti pola `*Enum` dengan nilai integer, `label()`, dan `options()`.

| Tabel | Data utama |
| --- | --- |
| `letter_templates` | `name` unik maksimal 100 karakter, `is_active`, `active_version_id` nullable |
| `letter_template_versions` | `letter_template_id`, nomor versi, `letter_number_id`, status, metadata DOCX, bukti validasi/uji/pemeriksaan, `published_at`, `first_used_at` |
| `letter_template_variables` | `version_id`, nama variabel, label, jenis, wajib/tidak, urutan, sumber pilihan, `reference_group_id` nullable, pilihan tunggal/multiple; tahap 4A menambah `default_text` nullable untuk fallback variabel tunggal non-Pegawai |
| `letter_template_variable_columns` | `variable_id`, atribut sumber, nama placeholder kolom, urutan; tahap 4A menambah `date_format` nullable untuk atribut tanggal Pegawai |
| `letter_template_signer_slots` | `version_id`, urutan slot; hanya menyimpan slot yang diaktifkan |
| `letter_template_signer_candidates` | `slot_id`, `employee_id` |
| `letter_template_audits` | Templat, versi terkait bila ada, pelaku, waktu, operasi, dan keterangan |
| `letter_template_variable_employee_attributes` — tambahan tahap 4A | `variable_id`, `source_attribute`, `default_text` nullable, `date_format` nullable; pengaturan per atribut variabel Pegawai tunggal |
| `letter_template_signer_employee_attributes` — tambahan tahap 4A | `slot_id`, `source_attribute`, `default_text` nullable, `date_format` nullable; pengaturan per atribut slot penandatangan |

Ketentuan:

- Klasifikasi berada pada versi agar perubahan klasifikasi tidak mengubah versi historis.
- Metadata DOCX menyimpan disk, path relatif, nama asli, ukuran, dan SHA-256. Berkas disimpan di storage privat.
- Bukti pengujian terikat hash DOCX dan konfigurasi, beserta lokasi/expiry hasil uji dan waktu/pelaku pemeriksaan visual.
- Status versi memakai enum integer: Draft = 1, Terbit = 2, Diarsipkan = 3. Versi terbit terdahulu tetap Terbit; `active_version_id` menentukan versi yang digunakan untuk surat baru.
- Jenis variabel memakai enum integer: teks pendek, teks panjang, tanggal, combobox. Mode multiple hanya berlaku untuk combobox.
- Sumber combobox memakai enum integer: referensi atau pegawai.
- Tidak menambah tabel Surat, penghitung nomor, atau alokasi nomor pada tahap ini.
- Tahap 4A menyediakan seluruh atribut bisnis Pegawai yang tercantum dalam katalog di bawah untuk penandatangan, variabel Pegawai tunggal, dan kolom berulang. Penambahan atribut berikutnya harus melalui katalog backend dan test; jangan mengekspos seluruh kolom model secara otomatis atau membuat jenis variabel baru untuk setiap atribut.
- DOCX contoh/panduan dihasilkan dari konfigurasi versi saat diminta, tanpa tabel tambahan. Panduan dapat diunduh ulang dan tidak menjadi bukti validasi/uji maupun pengganti DOCX sumber.

### Katalog Pegawai bersama — target tahap 4A

Katalog backend memiliki key atribut, label, sufiks token, jenis nilai, dan pemetaan nilai. Katalog yang sama dipakai Resource opsi, UI konfigurasi, validasi namespace, resolver, formatter, dan panduan DOCX. ID database, UUID, dan timestamps bukan atribut surat; `row_number` tetap khusus kolom tabel berulang.

| Kelompok | Key atribut sumber → sufiks token |
| --- | --- |
| Identitas | `name` → `nama` (dengan gelar), `raw_name` → `nama_tanpa_gelar`, `front_title` → `gelar_depan`, `back_title` → `gelar_belakang`, `nik` → `nik`, `nip` → `nip`, `gender` → `jenis_kelamin`, `email` → `email` |
| Kepegawaian | `job_title` → `jabatan`, `job_title_group` → `kelompok_jabatan`, `rank` → `pangkat`, `grade` → `golongan`, `join_date` → `tanggal_bergabung`, `contract_status` → `status_kontrak`, `status` → `status_pegawai` |
| Masa kerja | `tenure` → `masa_kerja`, `tenure_years` → `masa_kerja_tahun`, `tenure_months` → `masa_kerja_bulan`, `tenure_days` → `masa_kerja_hari` |
| STR | `str_number` → `nomor_str`, `str_status` → `status_str`, `str_published_date` → `tanggal_terbit_str` |
| SIP | `sip_number` → `nomor_sip`, `sip_published_date` → `tanggal_terbit_sip`, `sip_expired_date` → `tanggal_kedaluwarsa_sip` |

- Satu pilihan pada variabel Pegawai tunggal menyediakan seluruh token otomatis dengan awalan nama variabel: variabel `pegawai` menyediakan `${pegawai_nama}`, `${pegawai_pangkat}`, `${pegawai_golongan}`, dan seterusnya. Semua atribut merujuk orang yang sama. Token dasar `${pegawai}` tetap alias nama lengkap.
- Satu slot penandatangan menyediakan `${penandatangan_nama}`, `${penandatangan_nip}`, dan seterusnya. Beberapa slot menyediakan `${penandatangan_1_nama}`, `${penandatangan_2_pangkat}`, dan seterusnya. Token dasar `${penandatangan}` atau `${penandatangan_1}` tetap alias nama lengkap dengan fallback atribut `name` yang sama.
- Variabel Pegawai multiple tetap memakai pemetaan kolom dan placeholder buatan admin; seluruh key katalog tersedia bersama `row_number`. Pertahankan key `name`, `nip`, `job_title`, dan placeholder kolom existing. Referensi tetap menyediakan `row_number` dan `content`.
- Semua token penandatangan opsional, termasuk token dasar/nama. Slot boleh tidak memiliki token apa pun dalam DOCX; kandidat aktif pada konfigurasi tetap diwajibkan. Namespace global mencakup semua token otomatis, alias, variabel, dan kolom; benturan ditolak dengan galat yang jelas tanpa penggantian nama otomatis. Panjang token hasil perlu memenuhi batas placeholder existing.
- Nama lengkap mengikuti susunan gelar aplikasi, jabatan/kelompok berasal dari relasi `jobTitle`, dan enum menghasilkan label. Setiap atribut dibaca dari data sumbernya sendiri; nilai null/relasi kosong mengikuti fallback, bukan angka kode enum atau id relasi.
- Masa kerja dihitung sebagai selisih kalender dari tanggal bergabung sampai tanggal acuan pemrosesan, dalam zona waktu aplikasi. Resolver menerima tanggal acuan eksplisit agar satu proses konsisten dan dapat diuji. Teks sampai hari, bagian nol dihilangkan: `5 tahun 3 hari`, `2 bulan 7 hari`, atau `12 hari`. Komponen tahun/bulan/hari adalah bagian selisih tersebut, bukan total bulan/hari; angka `0` tetap sah. Tanggal bergabung hari ini atau masa depan menghasilkan `0 hari`; tanggal kosong mengikuti fallback.

### Fallback dan format tanggal — target tahap 4A

- Penandatangan dan variabel Pegawai tunggal memiliki fallback teks literal per atribut, default kosong. Atribut yang bernilai kosong memakai fallback; tanpa fallback hasilnya teks kosong. Angka `0` bukan nilai kosong. Alias dasar memakai fallback nama lengkap yang sama.
- Variabel tunggal teks pendek/panjang, tanggal, dan Referensi memakai `default_text` sebagai fallback literal pada DOCX, bukan nilai awal form. Variabel Pegawai tunggal memakai pengaturan per atribut, tanpa fallback induk tambahan. Fallback tidak melewati validasi variabel wajib; data wajib tetap harus diisi.
- Variabel multiple tidak memiliki fallback, termasuk per kolom. Pilihan kosong pada multiple opsional menghapus baris templat saja, mempertahankan header/baris statis; hapus tabel jika tidak memiliki isi lain. Multiple wajib tetap memerlukan minimal satu pilihan. Atribut kosong pada baris terpilih menghasilkan teks kosong.
- Setiap atribut tanggal pada slot, variabel Pegawai tunggal, atau kolom multiple memiliki format sendiri dengan default `d-m-Y`. Format mengikuti [pola PHP DateTimeInterface::format](https://www.php.net/manual/en/datetime.format.php), dengan nama hari/bulan mengikuti `config('app.locale')` (`id` pada aplikasi ini); `d F Y` menghasilkan `25 Oktober 2026`. Pertahankan karakter literal yang di-escape; jangan menerjemahkan teks literal milik admin. Pengaturan ini tidak mengubah format penyimpanan tanggal atau isian tanggal biasa.
- Input format memiliki tombol informasi di sampingnya untuk membuka penjelasan kode umum (`d`, `j`, `m`, `n`, `M`, `F`, `Y`, `y`, `D`, `l`), contoh hasil, cara escape teks literal, penyesuaian bahasa, dan tautan dokumentasi PHP. Gunakan formatter backend bersama untuk contoh dan hasil DOCX, tanpa aturan format tandingan di frontend.

## 3. Constraint dan perilaku penyimpanan

- Unique: nama templat; pasangan templat–nomor versi; nama variabel per versi; urutan slot per versi; pasangan slot–pegawai; nama placeholder kolom per variabel.
- Tahap 4A menambah unique pemilik–atribut pada masing-masing tabel pengaturan Pegawai dan foreign key pemilik dengan `restrictOnDelete`. Saat mengganti snapshot konfigurasi, hapus anak pengaturan sebelum variabel/slot induknya dalam transaksi yang sama; UUID pemilik dan riwayat versi lain tetap dipertahankan.
- Foreign key ke templat, versi, klasifikasi, grup referensi, dan pegawai menggunakan `restrictOnDelete`. Tidak ada cascade yang menghapus riwayat.
- `active_version_id` harus menunjuk versi Terbit milik templat yang sama. Validasi transaksi juga memastikan konfigurasi dan operasi versi tidak dapat memakai UUID anak milik templat lain.
- Identitas pelaku audit tetap disimpan sebagai salinan nama/identitas; akun pelaku yang dihapus tidak menghapus audit.
- Validasi aplikasi memastikan seluruh placeholder variabel, kolom tabel, dan penandatangan tidak bertabrakan dalam satu versi.
- Status kelayakan penandatangan mengacu pada `Employee.status = AsnStatusEnum::AKTIF`; tidak menambah kolom status penandatangan tersendiri. Backend memeriksa status saat menyimpan kandidat, publikasi, dan penggunaan penandatangan untuk generate uji; fitur Surat nanti wajib memeriksanya lagi untuk surat baru.
- Kandidat existing yang menjadi tidak aktif tetap terbaca agar halaman kelola dapat memperingatkan admin dengan nama pegawai dan slot terkait. Publikasi ditolak sampai admin mengganti atau menghapus kandidat tidak aktif. Penandatangan tidak aktif ditolak saat digunakan, termasuk dari payload formulir lama.
- Perubahan status pegawai tidak otomatis menghapus kandidat atau mengubah versi historis. Perbaikan konfigurasi mengikuti aturan perubahan versi di bawah; slot aktif tetap wajib memiliki kandidat yang valid.
- Pembuatan draft dan publikasi mengunci baris templat dalam transaksi agar maksimal satu draft berjalan dan satu versi terbit aktif.
- Versi terbit dengan `first_used_at` kosong dapat dikembalikan menjadi draft; pointer aktif dan bukti pemeriksaan dibatalkan.
- Jika `first_used_at` terisi, perubahan menyalin konfigurasi ke versi baru. Sumber DOCX versi asal tidak ditimpa.
- Fitur Surat nanti wajib mengisi `first_used_at` saat penggunaan pertama dalam transaksi yang berkoordinasi dengan penguncian versi.
- Perubahan draft mempertahankan DOCX, tetapi membatalkan bukti validasi/uji/pemeriksaan terkait.
- Fingerprint konfigurasi tahap 4A mencakup fallback, format tanggal, revisi kontrak katalog/resolver, serta locale efektif untuk format tanggal. Bukti lama yang tidak cocok ditandai stale dan harus divalidasi/diuji ulang; sumber existing tidak dihapus atau diganti. Revisi kontrak mencegah bukti tahap 4 dianggap cocok dengan semantik baru. Ini tetap bukan snapshot nilai data master.

### Integrasi dengan penghapusan master saat ini

- `LetterNumber` dan `Employee` saat ini dihapus permanen. Implementasi templat harus menambahkan penolakan yang jelas ketika master masih dirujuk, termasuk oleh versi historis atau Diarsipkan; jangan hanya menampilkan error foreign key.
- `ArchiveReference::group()` saat ini membuat snapshot lalu menghapus grup dari tabel aktif dalam satu transaksi. Arsip bukan SoftDeletes. Jika grup dirujuk variabel templat, operasi ini harus ditolak dan seluruh transaksi dibatalkan tanpa meninggalkan paket arsip baru.
- Item referensi dapat diarsipkan dan dipulihkan secara terpisah. Fondasi templat merujuk grup, bukan menyimpan pilihan item surat. Penguncian nilai item yang sudah dipilih pada surat historis perlu kontrak snapshot pada fitur Surat nanti.
- Status Diarsipkan pada `letter_template_versions` tidak memindahkan baris ke `archives`/`archive_records`, tidak melepas relasi master, dan tidak mengaktifkan pemulihan atau hapus permanen lewat Arsip pusat.
- DOCX dan konfigurasi versi historis dipertahankan, tetapi relasi ke master saja tidak membekukan nama pegawai, jabatan, klasifikasi, atau isi referensi. Kontrak data surat historis masih harus dirinci pada fitur Surat.

## 4. Tahap implementasi dan commit

Pembagian di bawah adalah tahap pengerjaan teknis, berbeda dari empat tahap pengelolaan yang dilihat admin. Status pelaksanaan tercatat pada tabel progres di bawah. Pencatatan tahap hanya dilakukan setelah implementasi dan test yang diwajibkan lulus.

### Aturan penyelesaian setiap tahap

- Kerjakan berurutan. Selesaikan cakupan tahap, tambahkan test PHPUnit yang bermakna, lalu jalankan test tahap tersebut beserta regresi fitur existing yang terdampak.
- Pengujian setiap tahap cukup PHPUnit pada PostgreSQL terisolasi. Test browser, pemeriksaan visual, dan test frontend ditunda sampai seluruh tahap implementasi selesai. Pint tetap dijalankan bila PHP berubah; ini pemformatan, bukan tambahan jenis test.
- Setelah test yang diwajibkan tahap tersebut lulus, periksa diff dan buat satu commit untuk tahap itu sebelum melanjutkan. Sertakan kode, migration, test, dan pembaruan changelog yang terkait; jangan memasukkan perubahan lain milik pengguna.
- Jika test gagal atau lingkungan test belum dapat berjalan, perbaiki dahulu dan jalankan ulang. Jangan menandai tahap selesai atau membuat commit tahap dengan klaim lulus. Kasus wajib yang dilewati belum memenuhi kriteria selesai.
- Catat cakupan perubahan, perintah PHPUnit, hasil, dan hash commit pada tabel progres di bawah. Jangan mengklaim tampilan sudah terverifikasi sebelum pemeriksaan akhir.
- Gunakan generator Artisan, direktori, komponen bersama, dan dependency existing. Pisahkan orkestrasi kompleks bila diperlukan tanpa membuat repository CRUD generik.

Sebelum tahap 1, periksa ulang skema, versi paket, dan kesiapan database test. Rincikan kontrak pada bagian 6 saat dibutuhkan tiap tahap; keputusan produk yang belum tersedia harus diselesaikan sebelum implementasi yang bergantung padanya.

### Tahap 1 — Fondasi database dan domain

Cakupan:

- Finalkan kolom, default/nullability, indeks, dan enum dari tujuh tabel fondasi pada bagian 2; dua tabel pengaturan atribut merupakan tambahan tahap 4A.
- Buat migration sesuai ketergantungan; tambahkan foreign key versi aktif setelah tabel versi tersedia.
- Tambahkan model, relasi, casts, UUID, dan factory. Seeder data awal tidak diperlukan.
- Siapkan dasar pencatatan audit dan perlindungan identitas pelaku tanpa bergantung pada keberadaan akun selamanya.

Kriteria lulus PHPUnit: persistensi dan relasi konfigurasi, keunikan, penolakan penghapusan record yang dirujuk, serta audit tetap tersedia ketika akun pelaku dihapus. Uji constraint melalui perilaku aplikasi/database, bukan hanya membaca deklarasi migration.

Usulan commit: `feat: tambah fondasi domain templat surat`.

### Tahap 2 — Identitas draft, daftar, dan akses halaman

Cakupan:

- Buat route terautentikasi `lt.*`, binding UUID, Form Request, Resource, dan controller untuk identitas serta daftar.
- Implementasikan draft awal dengan nama dan klasifikasi, lalu arahkan ke halaman kelola. Batasi field yang boleh ditulis dan jangan mengubah counter penomoran.
- Tambahkan halaman daftar/tambah/kelola dasar, form identitas, serta sidebar dengan pageId existing.
- Terapkan pencarian nama/kode/nama klasifikasi, filter tunggal, pagination, dan komponen bersama. Tetapkan terlebih dahulu klasifikasi yang ditampilkan ketika tidak ada versi aktif.

Kriteria lulus PHPUnit: akses pengguna login/tamu, UUID tidak dikenal, validasi nama/klasifikasi, field sistem terlindungi, redirect ke kelola, kontrak props Inertia, pencarian literal, filter gabungan, serta urutan dan pagination stabil. Counter `LetterNumber` tetap sama setelah draft dibuat/diubah. Halaman Inertia harus sudah ada agar assertion halaman dapat dijalankan.

Usulan commit: `feat: tambah identitas dan daftar templat surat`.

### Tahap 3 — Konfigurasi variabel dan penandatangan

Cakupan:

- Implementasikan penyimpanan variabel, sumber combobox, kolom tabel berulang, slot, kandidat, dan tampilan placeholder yang dapat disalin.
- Batasi setiap operasi konfigurasi ke versi dan templat induknya; cegah nama placeholder bertabrakan.
- Terapkan kandidat aktif, peringatan nama/slot untuk kandidat yang menjadi tidak aktif, dan penyajian kandidat lama tanpa menghapusnya otomatis.
- Integrasikan penolakan hapus klasifikasi/pegawai dan arsip grup referensi yang dirujuk, termasuk oleh versi historis/Diarsipkan. Operasi arsip yang ditolak tidak boleh meninggalkan snapshot.
- Perubahan konfigurasi membatalkan bukti validasi/uji/konfirmasi terkait dan tetap mempertahankan DOCX sumber.

Kriteria lulus PHPUnit: seluruh jenis variabel dan aturan multiple, kolom yang diizinkan, benturan placeholder, isolasi UUID anak, kandidat tidak aktif ditolak, warning kandidat existing, invalidasi bukti, serta penolakan penghapusan/arsip master dengan rollback utuh. Jalankan juga test penghapusan LetterNumber/Employee dan arsip Reference yang terdampak.

Usulan commit: `feat: tambah konfigurasi variabel dan penandatangan templat`.

### Tahap 4 — Upload, penyimpanan, dan validasi DOCX

Tahap ini sudah selesai dengan kontrak awal di bawah. Kewajiban token penandatangan pada kontrak awal diganti pada tahap 4A; hasil test/commit tahap 4 tetap merupakan bukti perilaku saat implementasinya.

Cakupan:

- Tetapkan config teknis yang dibutuhkan: disk/path privat, batas upload, keamanan ZIP, dan batas placeholder. Path ditentukan aplikasi.
- Implementasikan upload/penggantian DOCX draft, metadata, SHA-256, akses berkas terautentikasi, dan penanganan berkas gagal.
- Validasi struktur DOCX, placeholder tidak dikenal, placeholder wajib penandatangan, serta dukungan text run dan bagian dokumen pada PHPWord yang dipin.
- Hubungkan tahap DOCX pada halaman kelola dan tampilkan hasil validasi beserta galat yang dapat ditindaklanjuti.
- Implementasikan endpoint unduh DOCX contoh/panduan terautentikasi dengan binding templat/versi yang terisolasi. Buat berkas melalui PHPWord berdasarkan konfigurasi versi yang dipilih, dengan identitas, penjelasan variabel, contoh paragraf, baris tabel berulang, slot penandatangan, dan langkah pengujian/publikasi sesuai proses bisnis.
- Tampilkan aksi **Unduh DOCX Contoh** sejak identitas draft disimpan dan pertahankan akses untuk admin berikutnya. Draft tanpa konfigurasi mendapat panduan awal; unduhan setelah perubahan memakai konfigurasi terbaru. Unduhan versi historis tetap memakai konfigurasi versi tersebut, tanpa menimpa sumber DOCX atau bukti pengujiannya.

Kriteria lulus PHPUnit: DOCX valid, ukuran/format tidak valid, struktur ZIP berbahaya/tidak didukung, placeholder tidak dikenal, slot tanpa placeholder, placeholder terpecah, header/footer, hash berubah, invalidasi bukti setelah penggantian, serta akses berkas versi lain. Kegagalan tidak menghilangkan DOCX sumber versi historis.

Untuk DOCX contoh, verifikasi unduhan sebagai pengguna login/admin pengganti, penolakan tamu dan UUID versi milik templat lain, panduan draft tanpa konfigurasi, kecocokan placeholder/kolom/slot dengan konfigurasi, pembaruan contoh setelah konfigurasi berubah, serta isolasi panduan versi historis. Isi penjelasan tidak membuat placeholder tambahan dan berkas tidak memuat data pribadi pegawai. Pengunduhan tidak mengubah status publikasi atau bukti validasi/uji.

Usulan commit: `feat: tambah upload dan validasi docx templat surat`.

### Tahap 4A — Katalog Pegawai, fallback, format tanggal, dan perbaikan UX

Tahap tambahan ini dikerjakan setelah tahap 4 dan sebelum tahap 5, tanpa menomori ulang atau mengubah bukti kelulusan tahap 1–4. Aturan placeholder penandatangan wajib pada implementasi tahap 4 diganti menjadi opsional di tahap ini.

Cakupan:

- Tambahkan katalog, resolver atribut, formatter tanggal, dan masa kerja sesuai kontrak bagian 2. Siapkan pemetaan yang dapat dipakai generate berikutnya; tahap ini belum menambah endpoint generate/publikasi.
- Buat migration tambahan untuk dua tabel pengaturan atribut, `variables.default_text`, dan `columns.date_format`. Jangan menulis ulang migration tahap 1. Data existing memperoleh fallback kosong dan format tanggal efektif `d-m-Y`; tidak mengubah data master Pegawai atau dependensi.
- Perluas payload `lt.configuration.update`: variabel memiliki `default_text` serta `employee_attributes`, slot memiliki `employee_attributes`, dan kolom memiliki `date_format`. Setiap pengaturan atribut berisi `source_attribute`, `default_text`, dan `date_format`; simpan sebagai baris relasional sesuai pemilik. Resource mengembalikan konfigurasi efektif dan metadata katalog melalui opsi backend.
- Pertahankan payload existing yang tidak mengirim field baru. Field baru yang dihilangkan mempertahankan pengaturan pemilik existing; null/kosong eksplisit mengembalikan fallback kosong atau format default. Field baru pada pemilik baru mendapat default. Key tidak dikenal, atribut duplikat, pengaturan tanggal pada atribut non-tanggal, atau fallback multiple ditolak; pengaturan sumber lama dibersihkan bila jenis/sumber variabel berubah.
- Perluas pemeriksaan namespace untuk keluarga token otomatis dan alias. Batasi UUID anak ke versi/induk yang benar; jangan menimpa versi lain. Perubahan konfigurasi tetap atomik, diaudit, dan membatalkan bukti terkait.
- Ubah validator DOCX agar tidak mewajibkan token untuk slot aktif. Perbarui panduan agar mencantumkan katalog token, alias, fallback, format tanggal, dan contoh penempatan tanpa data pribadi pegawai nyata; penjelasan tidak menciptakan placeholder tambahan.
- Terapkan mode baca/edit kartu identitas, konfigurasi, dan DOCX. Hanya satu kartu dapat diedit; pemicu edit kartu lain dinonaktifkan selama edit/penyimpanan. Simpan/Batal hanya tampil saat edit. Simpan sukses menutup edit, sedangkan galat mempertahankan form. Batal mengembalikan nilai tersimpan serta membersihkan galat/pilihan file tanpa navigasi. Form tambah identitas tetap langsung dapat diisi. Unduhan dan informasi status tetap tersedia pada mode baca.
- Samakan naik/turun dengan tombol outline ikon kecil pada edit Reference, bedakan hapus memakai destructive, dan gunakan ikon sebelum teks untuk tombol berteks. Pertahankan label aksesibel untuk tombol ikon; jangan mengubah perilaku komponen bersama di fitur lain.
- Sisakan satu aksi **Unduh DOCX Contoh** yang ringkas pada ringkasan atas halaman kelola; hapus duplikat dari kartu DOCX. Unduhan versi historis tetap tersedia.

Kriteria lulus PHPUnit: seluruh atribut dan label/relasi kosong, alias lama, benturan/panjang token otomatis, token penandatangan yang tidak digunakan, fallback per pemilik, penolakan konfigurasi salah jenis/UUID asing, payload lama, rollback dan isolasi versi, pembaruan panduan tanpa PII, serta fingerprint/bukti stale. Uji masa kerja pada pergantian bulan/tahun, tahun kabisat, beberapa hari, null, hari ini, dan masa depan; uji format default/kustom, locale `id`, dan escape literal. Jalankan regresi konfigurasi, DOCX, contoh, dan penyimpanan yang terdampak. Pengujian hasil penggantian DOCX/tabel kosong yang memerlukan endpoint generate dilengkapi pada tahap 5. Pemeriksaan interaksi kartu, tombol informasi, dan unduhan tunggal masuk verifikasi akhir; styling saja tidak memerlukan test baru.

Usulan commit: `feat: lengkapi atribut pegawai dan perbaiki ux templat surat`.

### Tahap 5 — Generate uji dan bukti pemeriksaan

Cakupan:

- Mulai setelah tahap 4A selesai. Gunakan katalog/resolver, fallback, dan format tanggal yang sudah ditetapkan; jangan membuat allowlist atau pemetaan Pegawai kedua. Finalkan teks hasil Referensi tunggal, urutan pilihan multiple, batas isian, timeout, dan masa simpan hasil uji.
- Buat validasi data contoh dan generate DOCX dengan teks, tanggal, pilihan, tabel berulang, serta penandatangan berbeda untuk setiap slot yang digunakan. Satu pilihan Pegawai mengisi seluruh atribut variabel/slot tersebut, termasuk token alias dasar.
- Turunkan kebutuhan form data contoh dari token yang benar-benar digunakan dalam DOCX tervalidasi. Slot tanpa token tidak meminta pilihan dan tidak menggagalkan generate. Jika ada token atribut slot, minta satu kandidat aktif slot tersebut; token nama tidak diwajibkan. Aturan variabel wajib yang digunakan tetap berlaku meskipun fallback tersedia.
- Terapkan fallback hanya pada nilai kosong sesuai kontrak, serta format per atribut/kolom dengan locale aplikasi. Bekukan tanggal acuan masa kerja sepanjang satu proses generate. Penggantian dilakukan hanya untuk token yang hadir, termasuk header/footer dan text run terpecah.
- Untuk multiple opsional tanpa pilihan, hapus baris templat sambil mempertahankan header/baris statis, dan hapus tabel yang tidak memiliki isi lain. Multiple wajib tanpa pilihan ditolak. Nomor urut dan pemetaan kolom tetap mengikuti daftar pilihan yang tervalidasi.
- Periksa kembali status aktif penandatangan saat generate; jangan mengandalkan pilihan yang sudah dimuat pada formulir.
- Sediakan unduhan hasil uji dan operasi konfirmasi pemeriksaan visual admin. Ikat bukti pada hash DOCX/konfigurasi dan hasil uji yang masih berlaku.
- Lengkapi formulir data contoh dan status validasi/uji/konfirmasi pada halaman kelola; operasinya tidak boleh menghilangkan perubahan kartu lain yang sedang diedit. Blokir operasi yang bergantung pada konfigurasi tersimpan sampai edit disimpan atau dibatalkan.

Kriteria lulus PHPUnit: isi hasil DOCX menggunakan fixture nyata dan PHPWord yang dipin, seluruh keluarga token Pegawai/alias, fallback literal dan angka nol, format tanggal per atribut/kolom berbahasa Indonesia, masa kerja dengan tanggal acuan tetap, pengulangan/penghapusan baris/tabel kosong, teks panjang/header/footer, validasi data contoh, slot tanpa token dan slot yang hanya memakai atribut non-nama, penandatangan ganda/tidak aktif, akses unduhan, expiry, bukti lama ditolak setelah perubahan fallback/format/katalog/locale, serta konfirmasi tanpa hasil uji ditolak. Test membuktikan aturan endpoint konfirmasi; pemeriksaan visual oleh pengembang dilakukan pada verifikasi akhir.

Usulan commit: `feat: tambah generate uji dan konfirmasi docx templat`.

### Tahap 6 — Publikasi, perubahan versi, dan timeline

Cakupan:

- Implementasikan publikasi dengan seluruh prasyarat validasi/uji/konfirmasi dan pemeriksaan kandidat aktif saat operasi dijalankan.
- Terapkan satu draft berjalan, satu pointer versi terbit aktif, edit versi belum digunakan, serta penyalinan konfigurasi dan sumber berkas untuk versi yang sudah digunakan.
- Salin fallback variabel, pengaturan seluruh atribut Pegawai per variabel/slot, serta format tanggal kolom ke draft baru bersama konfigurasi lainnya. Jangan mereset pengaturan tersebut atau berbagi baris anak yang dapat berubah dengan versi asal. Panduan dan unduhan historis tetap menggunakan konfigurasi pemiliknya.
- Kesiapan publikasi memakai fingerprint terbaru, termasuk fallback/format/revisi kontrak/locale; bukti sebelum tahap 4A atau bukti yang sudah stale tidak memenuhi syarat. Jangan mengembalikan kewajiban token penandatangan; slot tanpa token tetap boleh ada, dengan aturan kandidat aktif konfigurasi yang sama.
- Implementasikan arsip draft, aktif/nonaktif templat, timeline versi, dan audit operasi penting. Versi Diarsipkan tetap di domain templat.
- Lengkapi seluruh kontrol empat tahap pengelolaan, pesan penolakan, dan status kesiapan pada halaman kelola.
- Uji kondisi `first_used_at` melalui data test; jangan menambahkan fitur Surat atau alokasi nomor untuk mensimulasikan penggunaannya.

Kriteria lulus PHPUnit: publikasi ditolak untuk setiap prasyarat yang belum terpenuhi, bukti stale, dan kandidat tidak aktif; publikasi valid mengubah pointer secara benar termasuk ketika slot tidak digunakan DOCX; edit/copy mempertahankan fallback dan format per pemilik sesuai status pemakaian; perubahan draft salinan tidak memengaruhi konfigurasi/hasil/panduan versi asal; versi asal dan DOCX tidak tertimpa; arsip/nonaktif tidak menghapus riwayat; timeline/audit mencatat operasi yang benar; payload tidak dapat mengubah versi templat lain.

Usulan commit: `feat: tambah publikasi dan siklus versi templat surat`.

### Tahap 7 — Cleanup, konkurensi, dan regresi integrasi

Cakupan:

- Implementasikan cleanup hasil uji kedaluwarsa dan berkas sementara dengan tetap melindungi DOCX sumber/historis.
- Lengkapi pengujian alur identitas → konfigurasi → DOCX → generate → konfirmasi → publikasi, termasuk perubahan konfigurasi dan peringatan penandatangan tidak aktif.
- Sertakan konfigurasi existing sebelum tahap 4A, penambahan atribut tanpa perubahan skema Pegawai, perubahan fallback/format/locale yang membuat bukti stale, penyalinan pengaturan versi, dan generate yang hanya memakai sebagian token. Cleanup tidak boleh menghapus pengaturan fallback/format atau sumber versi historis.
- Siapkan dan jalankan cakupan PHPUnit konkurensi PostgreSQL antarkoneksi: draft bersamaan, publikasi, perubahan versi, penyimpanan konfigurasi melawan hapus/arsip master, serta perubahan status penandatangan saat operasi.
- Rinci cara eksekusi konkurensi templat yang terisolasi; jangan menganggap runner arsip otomatis menemukan test templat baru.
- Lengkapi changelog implementasi berbahasa Indonesia dan hasil verifikasi yang benar-benar dijalankan.

Kriteria lulus PHPUnit: cleanup hanya menghapus berkas yang boleh dihapus; alur integrasi lulus; konkurensi tidak menghasilkan draft/pointer ganda, referensi hilang, atau penggunaan penandatangan tidak aktif; seluruh suite PHPUnit utama dan runner integrasi arsip yang terdampak lulus. Jalankan test konkurensi templat secara eksplisit; kasus wajib yang skipped tidak dihitung lulus.

Usulan commit: `feat: lengkapi cleanup dan regresi integrasi templat surat`.

### Progres dan bukti tiap tahap

Hasil diisi setelah pekerjaan dan test dilaksanakan. Hash di bawah menunjuk commit implementasi tiap tahap yang sudah selesai; nama commit tahap lainnya tetap merupakan usulan.

| Tahap | Status | Perintah dan hasil PHPUnit | Hash commit |
| --- | --- | --- | --- |
| 1. Fondasi database/domain | Selesai | `vendor/bin/phpunit tests/Feature/LetterTemplateStorageTest.php tests/Feature/LetterNumberDeleteTest.php tests/Feature/EmployeeDeleteTest.php tests/Feature/ReferenceGroupDeleteTest.php tests/Feature/ReferenceArchiveTest.php` — 41 test, 132 assertion lulus | `785609f` |
| 2. Identitas/daftar | Selesai | `vendor/bin/phpunit tests/Feature/LetterTemplateStorageTest.php tests/Feature/LetterTemplateStoreTest.php tests/Feature/LetterTemplateEditTest.php tests/Feature/LetterTemplateIndexTest.php tests/Feature/LetterNumberIndexTest.php tests/Feature/LetterNumberEditTest.php tests/Feature/LetterNumberDeleteTest.php tests/Feature/EmployeeDeleteTest.php tests/Feature/ReferenceGroupDeleteTest.php tests/Feature/ReferenceArchiveTest.php tests/Feature/ListingSearchTest.php` — 144 test, 1.213 assertion lulus | `f10ef22` |
| 3. Variabel/penandatangan | Selesai | `vendor/bin/phpunit tests/Feature/LetterTemplateConfigurationTest.php tests/Feature/LetterTemplateMasterProtectionTest.php tests/Feature/LetterTemplateStorageTest.php tests/Feature/LetterTemplateStoreTest.php tests/Feature/LetterTemplateEditTest.php tests/Feature/LetterTemplateIndexTest.php tests/Feature/LetterNumberDeleteTest.php tests/Feature/EmployeeDeleteTest.php tests/Feature/ReferenceGroupDeleteTest.php tests/Feature/ReferenceArchiveTest.php tests/Feature/ReferenceItemDeleteTest.php` — 172 test, 1.095 assertion lulus | `1d1b684` |
| 4. Upload/validasi DOCX | Selesai | `vendor/bin/phpunit tests/Feature/LetterTemplateDocxTest.php tests/Feature/LetterTemplateExampleTest.php tests/Feature/LetterTemplateConfigurationTest.php tests/Feature/LetterTemplateMasterProtectionTest.php tests/Feature/LetterTemplateStorageTest.php tests/Feature/LetterTemplateStoreTest.php tests/Feature/LetterTemplateEditTest.php tests/Feature/LetterTemplateIndexTest.php` — 197 test, 1.305 assertion lulus | `8dd1f0c` |
| 4A. Katalog Pegawai/fallback/format/UX | Belum dimulai; prasyarat tahap 5 | — | — |
| 5. Generate/konfirmasi | Belum dimulai | — | — |
| 6. Publikasi/versi | Belum dimulai | — | — |
| 7. Cleanup/integrasi | Belum dimulai | — | — |

### Verifikasi akhir — Setelah tahap 1–7 termasuk 4A selesai dan di-commit

- Jalankan pemeriksaan frontend yang relevan, TypeScript, lint, serta build produksi; build SSR bila terdampak. Pemeriksaan ini dikumpulkan pada akhir, bukan menjadi syarat test tiap tahap.
- Gunakan Chromium yang sudah disediakan pengguna untuk menguji aplikasi melalui browser. Periksa akses/browser tooling yang tersedia saat pelaksanaan; tidak perlu mengunduh browser baru.
- Uji alur lengkap dari tambah sampai publikasi, edit/copy versi, arsip draft, dan nonaktifkan; periksa unduhan DOCX contoh setelah pembuatan dan saat login sebagai admin pengganti, unggah/unduh, galat validasi, penandatangan berbeda, dan peringatan nama/slot ketika kandidat berubah tidak aktif.
- Periksa seluruh atribut tersedia pada penandatangan/variabel/kolom berulang, satu pilihan Pegawai dipakai seluruh tokennya, fallback per atribut tersimpan, format tanggal default/kustom dengan bantuan dan contoh sesuai locale, serta masa kerja sampai hari tanpa bagian tahun/bulan nol. Periksa DOCX yang hanya menggunakan pangkat/golongan tanpa token nama dan slot yang sama sekali tidak digunakan.
- Periksa kartu awal dalam mode baca tanpa Simpan/Batal, hanya satu kartu editable, Batal tidak menavigasi dan memulihkan nilai/galat/file, kegagalan simpan tetap editable, serta operasi tahap berikutnya tidak membuang edit. Verifikasi naik/turun, hapus, ikon sebelum teks, dan satu unduhan contoh ringkas di ringkasan atas; unduhan historis tetap tersedia.
- Periksa desktop/mobile, keyboard, fokus input, request lambat, retry, Back/Forward, filter/pagination, loading, dialog, toast, timeline, dan log browser.
- Periksa DOCX contoh/panduan serta hasil DOCX uji secara visual dengan aplikasi pembuka DOCX yang tersedia. Chromium menguji UI aplikasi; pemeriksaan struktur DOCX melalui PHPUnit tidak membuktikan tata letak dokumennya benar. Catat jika pemeriksaan visual DOCX belum dapat dilakukan.
- Jika ada temuan, perbaiki, jalankan ulang PHPUnit yang terdampak dan pemeriksaan yang gagal, lalu commit perbaikannya. Jangan mengubah klaim kelulusan atau menimpa riwayat commit tahap sebelumnya.
- Catat hasil browser/visual secara terpisah. Fitur baru dinyatakan selesai setelah verifikasi akhir dan perbaikan temuan selesai.

## 5. Pengujian

Test yang direncanakan pada PostgreSQL mencakup:

- Draft awal hanya memerlukan nama dan klasifikasi.
- Keunikan, relasi, penolakan penghapusan master, dan isolasi konfigurasi antarversi.
- Penolakan arsip grup referensi yang masih dirujuk, termasuk oleh versi historis/Diarsipkan, tanpa snapshot yatim atau hilangnya data aktif.
- Satu draft berjalan; publikasi hanya setelah validasi, uji, dan pemeriksaan.
- Kandidat tidak aktif ditolak saat penyimpanan; kandidat existing yang menjadi tidak aktif menghasilkan peringatan nama/slot kepada admin dan memblokir publikasi. Generate uji menolak penandatangan yang menjadi tidak aktif setelah formulir dibuka, sementara konfigurasi historis tetap tersimpan.
- Edit versi belum digunakan; penyalinan versi yang sudah digunakan.
- Perubahan konfigurasi membatalkan hasil uji tanpa menghapus DOCX asal.
- Pembentukan tabel berulang dan perlindungan hasil DOCX historis.
- Seluruh atribut bisnis Pegawai tersedia dalam satu katalog; kompatibilitas token/key lama, keluarga token otomatis, isolasi pengaturan per pemilik, dan benturan namespace diuji tanpa membocorkan ID teknis.
- Fallback per atribut/variabel tunggal tidak melewati kewajiban input, angka nol dipertahankan, multiple tidak menerima fallback, serta baris/tabel kosong ditangani tanpa menghapus header atau baris statis.
- Format tanggal default `d-m-Y`, pola PHP kustom, locale aplikasi `id`, escape literal, dan masa kerja sampai hari menggunakan tanggal acuan yang sama sepanjang proses.
- Slot boleh tidak digunakan atau hanya memakai token atribut non-nama; konfigurasi kandidat aktif tetap dijaga. Perubahan fallback/format/revisi kontrak/locale membatalkan kecocokan bukti lama dan penyalinan versi mempertahankan pengaturan tanpa mengubah versi asal.
- Autentikasi, UUID yang tidak dikenal atau milik induk lain, field yang dilindungi, serta kontrak Resource.
- Pencarian literal tanpa membedakan kapital, normalisasi input, filter gabungan, urutan stabil, dan pagination.

Gunakan runner utama `tests/bootstrap.php` dan database disposable dari `.env.testing` / override `TEST_DB_*`; jangan memakai database aplikasi. Ikuti [panduan pengujian repository](../../LARAVEL_PATTERNS.md#database-dependencies-and-testing).

Jalankan PHPUnit langsung untuk tahap implementasi, misalnya `vendor/bin/phpunit tests/Feature/{NamaTestTahap}.php`, lalu tambahkan file regresi existing yang terdampak. Nama dalam kurung kurawal adalah penanda yang harus diganti dengan file test nyata. Pada tahap 7 jalankan suite utama melalui `vendor/bin/phpunit` dan runner integrasi/konkurensi yang relevan secara terpisah. Kelulusan PHPUnit per tahap tidak dianggap sebagai kelulusan browser/visual.

Kasus konkurensi PostgreSQL perlu membuktikan pembuatan draft bersamaan, publikasi, perubahan versi, serta penghapusan/arsip master yang bersaing dengan penyimpanan konfigurasi. Test transaksi biasa belum membuktikan perilaku antarkoneksi. Runner arsip `phpunit.archive.xml` tetap khusus integrasi arsip; test konkurensi templat perlu cakupan dan cara eksekusi tersendiri yang dirinci saat implementasi. Catat kasus yang dilewati atau belum dijalankan.

Untuk DOCX, uji hasil berkas dengan fixture yang mencakup tabel berulang, header/footer, placeholder terpecah, teks panjang, input berbahaya, perubahan hash/konfigurasi, dan expiry hasil uji. Test tahap 4 memakai DOCX nyata melalui PHPWord yang dipin, termasuk penggantian teks panjang/text run/header/footer dan clone baris. Endpoint generate uji dan bukti pemeriksaan tetap menunggu tahap 5.

### Kontrak DOCX yang diterapkan pada tahap 4

Bagian ini mencatat perilaku pada commit tahap 4, bukan klaim bahwa perubahan tahap 4A sudah tersedia. Kewajiban placeholder slot, isi panduan, dan fingerprint di bawah akan diperbarui oleh tahap 4A sesuai bagian 2–4; aturan keamanan paket dan isolasi berkas tetap berlaku.

- Endpoint terautentikasi dengan scoped binding templat/versi: `POST .../source` (`lt.source.store`), `GET .../source` (`lt.source.download`), `POST .../validate` (`lt.validation.store`), dan `GET .../example` (`lt.example.download`), dengan awalan `/letter-templates/{letterTemplate:uuid}/versions/{version:uuid}`. UUID versi asing/tidak dikenal/numerik ditolak 404. Upload menerima hanya file `docx`; field sistem tidak ditulis.
- `config/letter-templates.php` menetapkan disk lokal privat, path sources/temporary/previews, upload 10.240 KB (10 MB), maksimal 1.000 entry ZIP, total dekompresi 50 MB, XML per entry 2 MB/total 8 MB, rasio kompresi maksimal 200, panjang placeholder 100 karakter, serta maksimal 1.000 kemunculan placeholder. Nilai expiry/generate belum ditetapkan karena belum digunakan.
- Upload memeriksa paket sebelum menyimpan. Pemeriksaan menolak path traversal/duplikat, symlink, enkripsi, ZIP rusak, XML tidak valid, DTD/entitas (termasuk encoding UTF-16), macro, objek tertanam/ActiveX, altChunk, field DDE/INCLUDETEXT/INCLUDEPICTURE/LINK/DATABASE, serta konten eksternal selain hyperlink HTTP/HTTPS/mailto. XML dibaca tanpa resolusi eksternal atau substitusi entitas.
- Profil DOCX memakai WordprocessingML transitional, dokumen utama `word/document.xml`, content type DOCX biasa, relasi paket utama relatif ke `word/document.xml`, serta header/footer bernomor berurutan mulai 1 agar sesuai PHPWord yang dipin. Komponen biner yang didukung terbatas pada gambar tertanam umum, font, dan printer settings; SVG/lampiran/komponen aktif ditolak. Bagian XML lain tetap diperiksa, tetapi placeholder di footnote/endnote/comment/custom XML tidak didukung.
- Placeholder teks boleh terpecah antar text run dalam satu paragraf, termasuk pada header/footer. Placeholder pada atribut XML, terpisah antarparagraf, tidak dikenal, atau tidak sesuai sintaks ditolak saat validasi. Variabel biasa boleh tidak digunakan; setiap slot aktif wajib memiliki placeholder sesuai jumlah slot tersimpan.
- Combobox multiple memakai placeholder kolom, bukan nama variabel induk. Kolom yang digunakan harus berada dalam satu baris tabel isi dokumen untuk setiap variabel; satu baris tidak boleh dipakai dua variabel multiple. Tabel bersarang, penggabungan vertikal, atau tabel berulang pada header/footer ditolak. Konfigurasi/kolom lain boleh tidak digunakan.
- Upload membuat path acak baru per versi, mengunci templat/versi untuk memastikan draft belum digunakan, menyimpan metadata aktual dan SHA-256, membatalkan bukti lama, serta mencatat `source.uploaded`. Kegagalan database membersihkan berkas baru dan mempertahankan sumber lama. Berkas lama hanya dihapus jika tidak lagi dirujuk versi mana pun; kegagalan cleanup dilaporkan tanpa menggagalkan metadata baru yang sudah tersimpan.
- Validasi terpisah memeriksa ulang sumber tersimpan, hash aktual, struktur, placeholder, serta hasil pembacaan PHPWord. Hasil gagal tetap disimpan dengan daftar galat dan tanpa bukti valid. Hasil lulus mengisi `validated_at`, hash sumber, hash konfigurasi, dan audit `source.validated`. Validasi ulang membatalkan bukti uji/visual sebelumnya. Hash konfigurasi mencakup nama templat, relasi klasifikasi, konfigurasi variabel/kolom, dan himpunan kandidat per slot; tidak menjadi snapshot nilai data master.
- Halaman kelola menerima metadata sumber tanpa disk/path privat, status pending/valid/invalid/stale, daftar galat/placeholder, batas upload, dan daftar versi yang dokumennya dapat diunduh. Unduhan sumber membuka stream selama shared lock versi dan dikirim sebagai attachment privat. Upload/validasi versi Terbit, Diarsipkan, atau sudah digunakan ditolak; unduh sumber/contoh tetap tersedia untuk versi historis.
- DOCX contoh dibuat melalui PHPWord dari snapshot konfigurasi versi dalam shared lock, dengan identitas/klasifikasi, penjelasan jenis/sumber/wajib, contoh paragraf, baris tabel, slot, serta langkah validasi/uji/publikasi. Nama dalam penjelasan tidak menciptakan placeholder tambahan; data kandidat pegawai tidak dimuat. Berkas sementara dihapus sesudah response dikirim. Unduhan tidak mengubah source, status, bukti pemeriksaan, atau audit.
- PHPUnit membuktikan akses login/tamu/UUID, paket berbahaya dan batas resource, placeholder/slot/tabel/header/footer/text run, hash berubah, invalidasi bukti, rollback file/database, unduhan, serta contoh baru/historis/admin pengganti. Regresi tahap 1–3 lulus sesuai tabel. Pint, TypeScript, lint terkait, dan `pnpm run build` lulus. Pemeriksaan browser/visual serta test frontend/konkurensi tetap mengikuti jadwal akhir; belum diklaim terverifikasi.
- Tidak ada migration/dependency baru. Batas PHP CLI yang terdeteksi adalah upload 2 MB dan POST 8 MB; batas web server dapat berbeda dan perlu diperiksa bila upload besar ditolak. Batas aplikasi tetap 10 MB; konfigurasi PHP sistem tidak diubah.

### Kontrak konfigurasi yang diterapkan pada tahap 3

Bagian ini mencatat kontrak awal tahap 3. Batas empat kolom Pegawai dan payload awal di bawah dipertahankan sebagai riwayat; target katalog, fallback, pengaturan format, dan UX terbaru berada pada bagian 2 serta tahap 4A. Jangan memakai batas historis ini untuk implementasi tahap 5–7.

- Endpoint terautentikasi `PUT /letter-templates/{letterTemplate:uuid}/versions/{version:uuid}/configuration` (`lt.configuration.update`) memakai scoped binding. UUID versi yang tidak dikenal, numerik, atau milik templat lain ditolak 404. Service memeriksa ulang kepemilikan dalam transaksi.
- Payload mengganti seluruh konfigurasi draft melalui `variables` dan `signer_slots` berbentuk list, termasuk list kosong untuk menonaktifkan semuanya. Variabel memuat `id` UUID existing nullable, `name`, `label`, `type`, `is_required`, `option_source`, `reference_group` UUID nullable, `is_multiple`, dan `columns`. Kolom memuat `id` UUID nullable, `source_attribute`, dan `placeholder`; slot memuat `id` UUID nullable dan `candidates` berupa list UUID pegawai. Key anak selain allowlist ditolak. Field sistem tingkat atas tidak ditulis.
- Nama variabel/placeholder kolom maksimal 100 karakter sesuai skema, diawali huruf kecil dan hanya menggunakan huruf kecil, angka, atau garis bawah. Label wajib berupa teks maksimal 100 karakter. Nama variabel, placeholder kolom, dan placeholder slot aktif berbagi satu namespace per versi.
- Teks pendek/panjang/tanggal tidak menerima sumber, grup, multiple, atau kolom. Combobox wajib memiliki sumber; sumber referensi wajib memilih grup aktif, sedangkan sumber pegawai tidak menerima grup. Combobox multiple wajib memiliki minimal satu kolom; mode tunggal tidak menerima kolom.
- Atribut kolom referensi: `row_number`, `content`. Atribut kolom pegawai: `row_number`, `name` (beserta gelar), `nip`, `job_title`. Jenis kelamin/STR/SIP tidak termasuk. Opsi enum, atribut, grup aktif, dan pegawai aktif dikirim melalui `configurationOptions`.
- Urutan list menentukan `sort_order`: variabel/kolom mulai 0, slot mulai 1. Slot tersimpan selalu aktif dan wajib memiliki kandidat; kandidat duplikat dalam slot ditolak. Pegawai yang sama boleh menjadi kandidat di beberapa slot; pemilihan orang berbeda saat generate mengikuti tahap 5. Placeholder satu slot adalah `penandatangan`; beberapa slot memakai `penandatangan_1`, `penandatangan_2`, dan seterusnya.
- UUID variabel/kolom/slot existing harus milik versi dan induk anak yang tepat. Penyimpanan mengganti snapshot konfigurasi draft dalam transaksi sambil mempertahankan UUID tersebut; primary key internal dan baris kandidat dapat dibuat ulang. Riwayat versi lain tetap utuh. Templat dan versi dikunci, lalu grup dan pegawai diperiksa dengan shared lock berurutan sebelum penulisan. Versi Terbit, Diarsipkan, atau yang sudah digunakan tidak dapat diubah melalui endpoint ini.
- Setiap penyimpanan konfigurasi membatalkan bukti validasi/uji/visual melalui `InvalidateLetterTemplateEvidence`, mempertahankan metadata DOCX sumber, dan mencatat audit `configuration.updated`. Penyimpanan identitas menggunakan layanan invalidasi yang sama. Galat penyimpanan membatalkan penggantian konfigurasi dan audit secara utuh.
- Halaman kelola mengirim `configuration.data` berisi UUID versi, `editable`, `has_configuration`, variabel/kolom, slot/kandidat, serta warning nama/slot pegawai tidak aktif. Kandidat tersebut tetap tersimpan. Warning dan pilihan aktif disegarkan dari data terbaru; menyimpan kandidat tidak aktif ditolak. Versi tanpa draft menampilkan konfigurasi versi rujukan secara read-only.
- Form awal menyediakan satu slot yang dapat dihapus. Audit penyimpanan membedakan konfigurasi awal dengan konfigurasi yang sengaja disimpan tanpa slot; slot kosong tidak otomatis muncul kembali setelah reload. Form memakai schema dan komponen bersama, urutan variabel/kolom, serta salin placeholder. Form identitas/konfigurasi menonaktifkan aksi selama salah satunya menyimpan; UUID hasil simpan digunakan pada simpan berikutnya.
- Hapus klasifikasi/pegawai mengunci record dan menolak referensi semua status versi dengan galat `deletion`; arsip grup melakukan pemeriksaan serupa sebelum membuat snapshot. Toast bersama menampilkan pesan penolakan backend. Item referensi tetap dapat diarsipkan sendiri.
- PHPUnit dan regresi tahap ini lulus sesuai tabel progres; Pint, lint file frontend terkait, TypeScript, dan diff lulus. Browser/visual, test frontend, serta konkurensi antarkoneksi tetap ditunda sesuai jadwal. Tidak ada dependency atau migration baru; skema aktif saat pemeriksaan sudah berisi tabel tahap 1.

### Kontrak identitas dan daftar yang diterapkan pada tahap 2

- Route: `GET /letter-templates` (`lt.index`), `GET /letter-templates/create` (`lt.create`), `POST /letter-templates` (`lt.store`), `GET /letter-templates/{letterTemplate:uuid}/edit` (`lt.edit`), serta `PUT/PATCH /letter-templates/{letterTemplate:uuid}` (`lt.update`). Semuanya memerlukan login; tidak ada route hapus permanen.
- Payload store/update hanya `name` (teks unik 3–100 karakter) dan `letter_number` (UUID klasifikasi existing). Backend menerjemahkan UUID menjadi foreign key internal dalam transaksi. Field sistem dan counter diabaikan. Galat memakai field formulir dan pesan bahasa Indonesia.
- Store membuat identitas aktif, draft versi 1, dan audit, kemudian redirect `lt.edit`. Update mengunci identitas dan draft induknya, hanya menerima draft yang belum digunakan, membatalkan bukti pemeriksaan saat identitas berubah, mempertahankan DOCX sumber, dan redirect ke kelola. Perubahan/pembuatan draft dari versi Terbit tetap tahap 6.
- Props: `page.id`, `classifications` berupa daftar `{id: UUID, label: kode — nama}`, serta `resources.data/meta/links` pada index atau `resource.data` pada kelola. Resource mengekspos UUID, nama, status aktif, nomor versi aktif nullable, penanda draft, klasifikasi, dan identitas draft nullable. Path privat dan id numerik tidak diekspos.
- Versi rujukan klasifikasi: versi aktif bila ada; jika tidak, draft berjalan; jika tidak, versi bernomor tertinggi. Pilihan ini merupakan asumsi implementasi yang disampaikan selama pengerjaan. Kolom, pencarian, dan filter memakai relasi `displayVersion` yang sama.
- Filter format `status:active|classification:UUID` atau `status:inactive`; setiap kategori hanya menerima satu nilai valid. Nilai invalid atau lebih dari satu diabaikan untuk kategori tersebut. Pencarian memakai trait `Searchable`, urutan `name, id`, dan ukuran halaman 5/10/15/20/50/100 dengan default 10.
- Halaman dasar menggunakan komponen bersama, helper Wayfinder, pageId sidebar existing, schema Valibot, dan toast existing. Kontrol konfigurasi/DOCX/publikasi/timeline menyusul pada tahap terkait.
- Pemeriksaan statis `pnpm run types:check`, formatter PHP/TSX, dan diff lulus. Browser, pemeriksaan visual, serta test frontend/konkurensi ditunda sesuai jadwal rencana. Migration dijalankan oleh PHPUnit pada database PostgreSQL disposable; database aplikasi belum dimigrasikan.

### Kontrak fondasi yang diterapkan pada tahap 1

- Migration `create_letter_template_tables` menetapkan kolom, panjang, default, nullability, dan indeks. Identitas aktif secara default; versi dimulai dengan status Draft. DOCX, bukti validasi/uji/visual, publikasi, dan pemakaian pertama nullable sampai operasinya tersedia.
- Metadata sumber memakai `source_disk/path/original_name/size/sha256`. Bukti validasi memakai `validated_source_sha256`, `validated_configuration_sha256`, `validated_at`, dan `validation_result` JSONB. Konfigurasi variabel/kolom/penandatangan tetap relasional.
- Bukti uji memakai `tested_source_sha256`, `tested_configuration_sha256`, `test_disk/path`, `tested_at`, dan `test_expires_at`. Pemeriksaan visual memakai waktu serta id akun nullable dan salinan UUID/nama. Detail pengikatan bukti saat konfirmasi diselesaikan tahap 5.
- Audit mencatat `actor_id` nullable dengan `nullOnDelete`, salinan `actor_uuid`/`actor_name`, `occurred_at`, `operation`, dan `description`. Layanan pencatatan menolak versi milik templat lain.
- Foreign key gabungan pointer aktif memastikan kepemilikan versi pada database; penyimpanan model juga menolak pointer ke versi yang belum Terbit. Transaksi publikasi dan penguncian status mengikuti tahap 6.
- Penghapusan master terikat diblokir database, termasuk untuk versi Diarsipkan. Pesan penolakan aplikasi dan rollback arsip grup mengikuti tahap 3.

## 6. Kelanjutan perencanaan

Keputusan atribut Pegawai, token otomatis/alias, token penandatangan opsional, fallback, format tanggal per atribut, masa kerja sampai hari, dan perbaikan UX sudah ditetapkan pada bagian 2 serta tahap 4A. Implementasikan tahap 4A sebelum memulai generate uji; tidak perlu mengulang keputusan produk tersebut.

Rincian berikut masih perlu ditetapkan sebelum implementasi terkait:

- Rincian endpoint dan batas data contoh/generate/publikasi serta operasi versi tahap 5–7 yang belum ditetapkan di atas; fondasi, konfigurasi, upload, dan validasi struktur DOCX tahap 1–4 sudah diterapkan. Kontrak lanjutannya wajib memakai hasil tahap 4A.
- Endpoint generate uji/konfirmasi/publikasi dan kontrak props/payload/error tahap 5–7. Route identitas/daftar/konfigurasi/upload/validasi/unduhan `lt.*` sudah tersedia.
- Nilai config untuk timeout generate, expiry hasil uji, dan cleanup tahap 5–7; aturan serta batas upload/ZIP/XML/placeholder tahap 4 sudah ditetapkan.
- Teks hasil Referensi tunggal dan urutan pilihan multiple pada hasil. Keluaran Pegawai/penandatangan, fallback untuk atribut kosong, dan penanganan multiple kosong sudah ditetapkan; jangan membukanya kembali sebagai keputusan yang belum disepakati.
- Dampak perubahan data master selain status aktif penandatangan terhadap validasi dan hasil uji yang sudah ada. Aturan penandatangan tidak aktif sudah ditetapkan pada bagian 3 dan proses bisnis.
- Mekanisme data snapshot surat historis pada fitur Surat, termasuk nilai seluruh atribut terpilih, tanggal acuan masa kerja, dan locale/format efektif agar hasil lama dapat direproduksi. Relasi master dan konfigurasi versi saja tidak membekukan nilai ini. Ini bukan penambahan tabel Surat dalam tahap fondasi templat.

Jangan menganggap rincian yang belum ditetapkan sebagai keputusan final.

Tahap 1–4 sudah mengubah kode dan menjalankan pengujian pada PostgreSQL disposable. Dependency tidak berubah. Tahap 4 tidak menambah atau menjalankan migration database aplikasi; build frontend sudah diperbarui. Pembaruan rencana tahap 4A dan dampaknya pada tahap 5–7 hanya mengubah dokumentasi; belum menambah migration, mengubah perilaku aplikasi, atau menjalankan pengujian implementasi baru.
