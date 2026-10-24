# Rencana Teknis Bertahap Templat Surat MVP 1

Status: implementasi bertahap; tahap 1 selesai, tahap 2 dikerjakan. Ditinjau ulang pada 25 Oktober 2026 terhadap kode, route, skema database aktif, dan pola aplikasi terbaru. Fondasi database dan pembagian pekerjaan ditetapkan di sini; rincian kontrak yang masih terbuka tercatat pada bagian 6.

Acuan proses bisnis: [letter-template-management-probis.md](letter-template-management-probis.md). Pilihan tabel relasional dan pemblokiran penghapusan master yang dirujuk, termasuk oleh versi historis atau arsip, sudah disepakati. Rincian kolom dan constraint di bawah merupakan rancangan teknis terakhir.

## 1. Ringkasan

Gunakan tujuh tabel relasional. Identitas templat tetap satu; DOCX dan seluruh konfigurasi berada pada versi masing-masing. Migration menggunakan Schema Builder Laravel dengan target PostgreSQL 18 untuk aplikasi dan pengujian database. SQLite bukan lagi target pengujian repository.

### Fondasi yang sudah tersedia

| Bagian | Kondisi saat peninjauan | Dampak pada rencana |
| --- | --- | --- |
| Templat Surat | Menu sudah ada dengan URL `#`; belum ada route, model, tabel, halaman, atau test fitur | Seluruh implementasi domain templat masih perlu dikerjakan |
| Penomoran Surat | `App\Models\LetterNumber`, klasifikasi berkode string, serta counter halaman/urutan | Gunakan sebagai relasi klasifikasi; jangan mengubah counter dari fitur templat |
| Pegawai | `Employee`, relasi `jobTitle`, dan status `AsnStatusEnum` | Pilihan baru hanya pegawai dengan status `AKTIF`; nama beserta gelar, NIP, dan jabatan mengikuti data yang tersedia |
| Daftar Referensi | Grup/item dengan UUID, urutan `sort_order`, dan alur arsip/pemulihan | Pilih grup/item dari tabel aktif; arsip grup yang dirujuk versi templat harus diblokir |
| Arsip pusat | Snapshot `Archive`/`ArchiveRecord`; handler saat ini hanya Reference | Status Diarsipkan pada versi templat tetap berada dalam tabel versi; belum ada integrasi arsip pusat untuk templat |
| Infrastruktur bersama | `Searchable`, komponen list/form, Wayfinder, runner PostgreSQL | Gunakan fondasi yang ada; rincian mengikuti [LARAVEL_PATTERNS.md](../../LARAVEL_PATTERNS.md) dan [FEATURE_WORKFLOW.md](../../FEATURE_WORKFLOW.md) |
| PHPWord | Terpasang pada commit `91a1d0d978ff2cff382134c5d0f80c4a03dffd2e` | Tidak perlu memasang dependency baru; kemampuan DOCX tetap perlu dibuktikan dengan test |

Daftar ini menyatakan kondisi kode yang tersedia, bukan hasil pengujian fitur Templat Surat. Fitur Surat juga belum tersedia; menu masih menggunakan URL `#`.

## 2. Tabel dan relasi

Semua entitas direncanakan memakai primary key numerik, UUID publik, dan timestamps. Foreign key memakai id numerik; Resource dan URL memakai UUID. Gunakan constraint unik UUID seperti master Reference terbaru. Nama enum mengikuti pola `*Enum` dengan nilai integer, `label()`, dan `options()`.

| Tabel | Data utama |
| --- | --- |
| `letter_templates` | `name` unik maksimal 100 karakter, `is_active`, `active_version_id` nullable |
| `letter_template_versions` | `letter_template_id`, nomor versi, `letter_number_id`, status, metadata DOCX, bukti validasi/uji/pemeriksaan, `published_at`, `first_used_at` |
| `letter_template_variables` | `version_id`, nama variabel, label, jenis, wajib/tidak, urutan, sumber pilihan, `reference_group_id` nullable, pilihan tunggal/multiple |
| `letter_template_variable_columns` | `variable_id`, atribut sumber, nama placeholder kolom, urutan |
| `letter_template_signer_slots` | `version_id`, urutan slot; hanya menyimpan slot yang diaktifkan |
| `letter_template_signer_candidates` | `slot_id`, `employee_id` |
| `letter_template_audits` | Templat, versi terkait bila ada, pelaku, waktu, operasi, dan keterangan |

Ketentuan:

- Klasifikasi berada pada versi agar perubahan klasifikasi tidak mengubah versi historis.
- Metadata DOCX menyimpan disk, path relatif, nama asli, ukuran, dan SHA-256. Berkas disimpan di storage privat.
- Bukti pengujian terikat hash DOCX dan konfigurasi, beserta lokasi/expiry hasil uji dan waktu/pelaku pemeriksaan visual.
- Status versi memakai enum integer: Draft = 1, Terbit = 2, Diarsipkan = 3. Versi terbit terdahulu tetap Terbit; `active_version_id` menentukan versi yang digunakan untuk surat baru.
- Jenis variabel memakai enum integer: teks pendek, teks panjang, tanggal, combobox. Mode multiple hanya berlaku untuk combobox.
- Sumber combobox memakai enum integer: referensi atau pegawai.
- Tidak menambah tabel Surat, penghitung nomor, atau alokasi nomor pada tahap ini.
- Kolom pegawai yang baru tersedia, seperti jenis kelamin, STR, dan SIP, tidak otomatis ditambahkan sebagai jenis variabel atau kolom tabel berulang. Cakupan kolom tetap mengikuti proses bisnis yang disepakati.
- DOCX contoh/panduan dihasilkan dari konfigurasi versi saat diminta, tanpa tabel tambahan. Panduan dapat diunduh ulang dan tidak menjadi bukti validasi/uji maupun pengganti DOCX sumber.

## 3. Constraint dan perilaku penyimpanan

- Unique: nama templat; pasangan templat–nomor versi; nama variabel per versi; urutan slot per versi; pasangan slot–pegawai; nama placeholder kolom per variabel.
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

### Integrasi dengan penghapusan master saat ini

- `LetterNumber` dan `Employee` saat ini dihapus permanen. Implementasi templat harus menambahkan penolakan yang jelas ketika master masih dirujuk, termasuk oleh versi historis atau Diarsipkan; jangan hanya menampilkan error foreign key.
- `ArchiveReference::group()` saat ini membuat snapshot lalu menghapus grup dari tabel aktif dalam satu transaksi. Arsip bukan SoftDeletes. Jika grup dirujuk variabel templat, operasi ini harus ditolak dan seluruh transaksi dibatalkan tanpa meninggalkan paket arsip baru.
- Item referensi dapat diarsipkan dan dipulihkan secara terpisah. Fondasi templat merujuk grup, bukan menyimpan pilihan item surat. Penguncian nilai item yang sudah dipilih pada surat historis perlu kontrak snapshot pada fitur Surat nanti.
- Status Diarsipkan pada `letter_template_versions` tidak memindahkan baris ke `archives`/`archive_records`, tidak melepas relasi master, dan tidak mengaktifkan pemulihan atau hapus permanen lewat Arsip pusat.
- DOCX dan konfigurasi versi historis dipertahankan, tetapi relasi ke master saja tidak membekukan nama pegawai, jabatan, klasifikasi, atau isi referensi. Kontrak data surat historis masih harus dirinci pada fitur Surat.

## 4. Tahap implementasi dan commit

Pembagian di bawah adalah tahap pengerjaan teknis, berbeda dari empat tahap pengelolaan yang dilihat admin. Status pelaksanaan tercatat pada tabel progres di bawah. Penyusunan rencana ini belum menjalankan implementasi, test fitur, atau commit tahap.

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

- Finalkan kolom, default/nullability, indeks, dan enum dari tujuh tabel pada bagian 2.
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

### Tahap 5 — Generate uji dan bukti pemeriksaan

Cakupan:

- Finalkan format hasil combobox tunggal/penandatangan, urutan pilihan multiple, data kosong, batas isian, timeout, dan masa simpan hasil uji.
- Buat validasi data contoh dan generate DOCX dengan teks, tanggal, pilihan, tabel berulang, serta penandatangan berbeda untuk setiap slot.
- Periksa kembali status aktif penandatangan saat generate; jangan mengandalkan pilihan yang sudah dimuat pada formulir.
- Sediakan unduhan hasil uji dan operasi konfirmasi pemeriksaan visual admin. Ikat bukti pada hash DOCX/konfigurasi dan hasil uji yang masih berlaku.
- Lengkapi formulir data contoh dan status validasi/uji/konfirmasi pada halaman kelola.

Kriteria lulus PHPUnit: isi hasil DOCX menggunakan fixture nyata dan PHPWord yang dipin, pengulangan baris, teks panjang/header/footer, validasi data contoh, penandatangan ganda/tidak aktif, akses unduhan, expiry, bukti lama ditolak setelah perubahan, serta konfirmasi tanpa hasil uji ditolak. Test membuktikan aturan endpoint konfirmasi; pemeriksaan visual oleh pengembang dilakukan pada verifikasi akhir.

Usulan commit: `feat: tambah generate uji dan konfirmasi docx templat`.

### Tahap 6 — Publikasi, perubahan versi, dan timeline

Cakupan:

- Implementasikan publikasi dengan seluruh prasyarat validasi/uji/konfirmasi dan pemeriksaan kandidat aktif saat operasi dijalankan.
- Terapkan satu draft berjalan, satu pointer versi terbit aktif, edit versi belum digunakan, serta penyalinan konfigurasi dan sumber berkas untuk versi yang sudah digunakan.
- Implementasikan arsip draft, aktif/nonaktif templat, timeline versi, dan audit operasi penting. Versi Diarsipkan tetap di domain templat.
- Lengkapi seluruh kontrol empat tahap pengelolaan, pesan penolakan, dan status kesiapan pada halaman kelola.
- Uji kondisi `first_used_at` melalui data test; jangan menambahkan fitur Surat atau alokasi nomor untuk mensimulasikan penggunaannya.

Kriteria lulus PHPUnit: publikasi ditolak untuk setiap prasyarat yang belum terpenuhi dan kandidat tidak aktif; publikasi valid mengubah pointer secara benar; edit/copy mengikuti status pemakaian; versi asal dan DOCX tidak tertimpa; arsip/nonaktif tidak menghapus riwayat; timeline/audit mencatat operasi yang benar; payload tidak dapat mengubah versi templat lain.

Usulan commit: `feat: tambah publikasi dan siklus versi templat surat`.

### Tahap 7 — Cleanup, konkurensi, dan regresi integrasi

Cakupan:

- Implementasikan cleanup hasil uji kedaluwarsa dan berkas sementara dengan tetap melindungi DOCX sumber/historis.
- Lengkapi pengujian alur identitas → konfigurasi → DOCX → generate → konfirmasi → publikasi, termasuk perubahan konfigurasi dan peringatan penandatangan tidak aktif.
- Siapkan dan jalankan cakupan PHPUnit konkurensi PostgreSQL antarkoneksi: draft bersamaan, publikasi, perubahan versi, penyimpanan konfigurasi melawan hapus/arsip master, serta perubahan status penandatangan saat operasi.
- Rinci cara eksekusi konkurensi templat yang terisolasi; jangan menganggap runner arsip otomatis menemukan test templat baru.
- Lengkapi changelog implementasi berbahasa Indonesia dan hasil verifikasi yang benar-benar dijalankan.

Kriteria lulus PHPUnit: cleanup hanya menghapus berkas yang boleh dihapus; alur integrasi lulus; konkurensi tidak menghasilkan draft/pointer ganda, referensi hilang, atau penggunaan penandatangan tidak aktif; seluruh suite PHPUnit utama dan runner integrasi arsip yang terdampak lulus. Jalankan test konkurensi templat secara eksplisit; kasus wajib yang skipped tidak dihitung lulus.

Usulan commit: `feat: lengkapi cleanup dan regresi integrasi templat surat`.

### Progres dan bukti tiap tahap

Isi hasil hanya setelah pekerjaan dan test dilaksanakan. Nama commit di atas merupakan usulan, bukan commit yang sudah dibuat.

| Tahap | Status | Perintah dan hasil PHPUnit | Hash commit |
| --- | --- | --- | --- |
| 1. Fondasi database/domain | Selesai | `vendor/bin/phpunit tests/Feature/LetterTemplateStorageTest.php tests/Feature/LetterNumberDeleteTest.php tests/Feature/EmployeeDeleteTest.php tests/Feature/ReferenceGroupDeleteTest.php tests/Feature/ReferenceArchiveTest.php` — 41 test, 132 assertion lulus | Dicatat setelah commit |
| 2. Identitas/daftar | Belum dimulai | — | — |
| 3. Variabel/penandatangan | Belum dimulai | — | — |
| 4. Upload/validasi DOCX | Belum dimulai | — | — |
| 5. Generate/konfirmasi | Belum dimulai | — | — |
| 6. Publikasi/versi | Belum dimulai | — | — |
| 7. Cleanup/integrasi | Belum dimulai | — | — |

### Verifikasi akhir — Setelah tahap 1–7 selesai dan di-commit

- Jalankan pemeriksaan frontend yang relevan, TypeScript, lint, serta build produksi; build SSR bila terdampak. Pemeriksaan ini dikumpulkan pada akhir, bukan menjadi syarat test tiap tahap.
- Gunakan Chromium yang sudah disediakan pengguna untuk menguji aplikasi melalui browser. Periksa akses/browser tooling yang tersedia saat pelaksanaan; tidak perlu mengunduh browser baru.
- Uji alur lengkap dari tambah sampai publikasi, edit/copy versi, arsip draft, dan nonaktifkan; periksa unduhan DOCX contoh setelah pembuatan dan saat login sebagai admin pengganti, unggah/unduh, galat validasi, penandatangan berbeda, dan peringatan nama/slot ketika kandidat berubah tidak aktif.
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
- Autentikasi, UUID yang tidak dikenal atau milik induk lain, field yang dilindungi, serta kontrak Resource.
- Pencarian literal tanpa membedakan kapital, normalisasi input, filter gabungan, urutan stabil, dan pagination.

Gunakan runner utama `tests/bootstrap.php` dan database disposable dari `.env.testing` / override `TEST_DB_*`; jangan memakai database aplikasi. Ikuti [panduan pengujian repository](../../LARAVEL_PATTERNS.md#database-dependencies-and-testing).

Jalankan PHPUnit langsung untuk tahap implementasi, misalnya `vendor/bin/phpunit tests/Feature/{NamaTestTahap}.php`, lalu tambahkan file regresi existing yang terdampak. Nama dalam kurung kurawal adalah penanda yang harus diganti dengan file test nyata. Pada tahap 7 jalankan suite utama melalui `vendor/bin/phpunit` dan runner integrasi/konkurensi yang relevan secara terpisah. Kelulusan PHPUnit per tahap tidak dianggap sebagai kelulusan browser/visual.

Kasus konkurensi PostgreSQL perlu membuktikan pembuatan draft bersamaan, publikasi, perubahan versi, serta penghapusan/arsip master yang bersaing dengan penyimpanan konfigurasi. Test transaksi biasa belum membuktikan perilaku antarkoneksi. Runner arsip `phpunit.archive.xml` tetap khusus integrasi arsip; test konkurensi templat perlu cakupan dan cara eksekusi tersendiri yang dirinci saat implementasi. Catat kasus yang dilewati atau belum dijalankan.

Untuk DOCX, uji hasil berkas dengan fixture yang mencakup tabel berulang, header/footer, placeholder terpecah, teks panjang, input berbahaya, perubahan hash/konfigurasi, dan expiry hasil uji. Test fitur Templat Surat belum tersedia; bagian ini bukan laporan test yang sudah lulus.

### Kontrak fondasi yang diterapkan pada tahap 1

- Migration `create_letter_template_tables` menetapkan kolom, panjang, default, nullability, dan indeks. Identitas aktif secara default; versi dimulai dengan status Draft. DOCX, bukti validasi/uji/visual, publikasi, dan pemakaian pertama nullable sampai operasinya tersedia.
- Metadata sumber memakai `source_disk/path/original_name/size/sha256`. Bukti validasi memakai `validated_source_sha256`, `validated_configuration_sha256`, `validated_at`, dan `validation_result` JSONB. Konfigurasi variabel/kolom/penandatangan tetap relasional.
- Bukti uji memakai `tested_source_sha256`, `tested_configuration_sha256`, `test_disk/path`, `tested_at`, dan `test_expires_at`. Pemeriksaan visual memakai waktu serta id akun nullable dan salinan UUID/nama. Detail pengikatan bukti saat konfirmasi diselesaikan tahap 5.
- Audit mencatat `actor_id` nullable dengan `nullOnDelete`, salinan `actor_uuid`/`actor_name`, `occurred_at`, `operation`, dan `description`. Layanan pencatatan menolak versi milik templat lain.
- Foreign key gabungan pointer aktif memastikan kepemilikan versi pada database; penyimpanan model juga menolak pointer ke versi yang belum Terbit. Transaksi publikasi dan penguncian status mengikuti tahap 6.
- Penghapusan master terikat diblokir database, termasuk untuk versi Diarsipkan. Pesan penolakan aplikasi dan rollback arsip grup mengikuti tahap 3.

## 6. Kelanjutan perencanaan

Rincian berikut masih perlu ditetapkan sebelum implementasi terkait:

- Nama kolom lengkap, nullability/default, indeks, dan kontrak validasi setiap tahap serta operasi versi.
- Endpoint operasi domain, bentuk props/payload/error, dan pemetaan field formulir. URL dasar serta alias `lt` sudah disepakati, tetapi route belum dibuat.
- Nilai config selain upload 10 MB, aturan struktur DOCX yang didukung/ditolak, timeout, expiry, dan cleanup.
- Format keluaran combobox tunggal dan penandatangan, urutan pilihan multiple pada hasil, serta penanganan NIP/jabatan kosong.
- Dampak perubahan data master selain status aktif penandatangan terhadap validasi dan hasil uji yang sudah ada. Aturan penandatangan tidak aktif sudah ditetapkan pada bagian 3 dan proses bisnis.
- Mekanisme data snapshot surat historis pada fitur Surat. Ini bukan penambahan tabel Surat dalam tahap fondasi templat.

Jangan menganggap rincian yang belum ditetapkan sebagai keputusan final.

Penyimpanan dokumen ini tidak menjalankan migration, mengubah kode aplikasi, atau mengubah dependency.
