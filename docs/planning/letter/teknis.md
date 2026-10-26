# Surat — Rencana Teknis Bertahap

Status: tahap 1 selesai diimplementasikan dan diverifikasi nonvisual; tahap 2–10 belum selesai. Kelayakan tampilan/cetak viewer belum dinilai pengguna. Acuan bisnis: [probis Surat](probis.md).

Tahap 1 menyediakan tombol Preview DOCX pada hasil generate uji Templat Surat, kontrol halaman/zoom/fit width/Cetak, frame terisolasi dan pembatasan ukuran. Verifikasi: 36 test PHPUnit/293 assertion untuk endpoint generate/unduh existing dan 12 test frontend terarah; TypeScript serta build lulus. Checklist visual/cetak tahap 1 tetap belum dijalankan oleh agent.

## 1. Aturan pelaksanaan

- Ikuti [FEATURE_WORKFLOW.md](../../../FEATURE_WORKFLOW.md), [LARAVEL_PATTERNS.md](../../../LARAVEL_PATTERNS.md), aturan proyek, dan skill yang relevan. Periksa ulang versi paket serta skema aktif sebelum implementasi tahap terkait.
- Target awal Linux, PHP 8.4, PostgreSQL, queue database, dan LibreOffice headless. Windows tidak termasuk klaim dukungan tahap pertama. Tidak menambah Redis atau layanan cloud untuk preview/SSE.
- Selesaikan dan verifikasi setiap tahap sebelum commit. Pesan commit berbahasa Inggris; usulan pesan ada di bagian tahapan. Jangan memasukkan perubahan pengguna atau modul lain yang tidak terkait.
- Setelah setiap tahap, sampaikan: hasil implementasi, hal yang sudah bisa dilakukan melalui web beserta pintu masuknya, hasil pemeriksaan otomatis, hash/pesan commit, dan tautan checklist manual. Bila tahap belum menyediakan fungsi web tertentu, nyatakan dengan jelas.
- Pengguna melakukan seluruh pemeriksaan visual, browser manual, perbandingan Word/PDF, dan cetak secara mandiri. Agent tidak menjalankan screenshot, browser visual, atau inspeksi visual dokumen. Checklist ada di `docs/tests/letter/tahap-*.md`; jangan menandai item pengguna lulus tanpa laporannya.
- PHPUnit, test frontend nonvisual, pemeriksaan tipe, lint, formatter, build, dan pengujian konkurensi tetap dilakukan sesuai perubahan. Test render/interaksi berbasis DOM boleh dipakai tanpa inspeksi visual. Perubahan dokumentasi saja tidak memerlukan test aplikasi.
- Hasil tahap dapat disampaikan sebelum checklist manual pengguna selesai. Pengecualian: penggunaan DOCX sebagai preview utama dan dropdown unduhan bersifat kondisional pada hasil uji viewer tahap 1 oleh pengguna; jangan mengklaim kesetiaan tampilan sudah terbukti lewat build/test otomatis.
- Dependensi viewer yang direncanakan: `docx-preview` dan paket EmbedPDF headless yang diperlukan. Verifikasi versi/API dan persetujuan dependensi menurut aturan proyek sebelum pemasangan. Rencana ini tidak memasang paket atau mengubah konfigurasi server.

## 2. Keputusan arsitektur

### Domain, route, dan antarmuka

- Domain Surat memakai `Letter` pada folder Laravel yang sudah ada: model, controller, request, Resource, job, dan `app/Services/Letter`. Hindari repository/generic workflow framework baru. Gunakan namespace fitur `Letter` untuk memisahkan dari `MasterData`.
- URL `/letters`, alias route yang direncanakan `ltr`, binding `{letter:uuid}`, halaman `resources/js/pages/letters`. Verifikasi alias belum dipakai sebelum menetapkannya. Pakai identitas menu Surat existing, bukan UUID baru; helper frontend melalui Wayfinder.
- Route resource menyediakan daftar, tambah, detail, ubah draft, simpan, dan hapus draft. Endpoint operasi terpisah menangani pembaruan master, penyesuaian templat, generate preview, konfirmasi, penerbitan, revisi, pembatalan, pemulihan, permintaan PDF, serta baca status pekerjaan/berkas. Semua memerlukan login; versi, operasi, dan berkas dibatasi ke induk yang benar.
- Resource mengirim nilai tersimpan, label status, kemampuan aksi dan alasan tertahan, batas input, versi draft, dan metadata pekerjaan. Jangan mengirim path privat atau seluruh model. Form Request tetap validasi utama; operasi memeriksa ulang state di bawah transaksi.
- Daftar memakai `SectionData`, `ResourceDataTable`, `Searchable`, filter bersama, dan urutan `updated_at DESC, id DESC`. Halaman kelola memakai `FormCard`, satu editor aktif, Batal lokal, input dipertahankan saat galat, serta `gooeyToast`. Ikon melalui Hugeicons; shell Base UI mengikuti komponen proyek.

### Preview, cetak, dan unduhan

- Kandidat awal DOCX viewer: `docx-preview`, dimuat secara lazy saat preview dibuka. Mode hanya-baca menyediakan zoom, fit width, navigasi halaman, dan Cetak. Tidak menyediakan toolbar editing, penyimpanan perubahan, atau integrasi Puter cloud.
- Viewer mengambil DOCX hasil generate dari endpoint terautentikasi. Render dalam frame terisolasi dari CSS aplikasi, tanpa script/HTML eksternal dari dokumen, dengan batas ukuran dan cleanup blob URL saat ditutup. Jangan memperlakukan read-only UI sebagai pembatas akses terhadap byte yang sudah dikirim ke browser.
- Jangan membangun parser/layout DOCX sendiri dari mesin Puter. Sumber Puter menjadi referensi pengalaman penggunaan; kelayakan renderer diputuskan menggunakan sampel milik aplikasi pada tahap 1.
- Setelah viewer dinyatakan layak oleh pengguna: Preview utama memakai DOCX; server tidak mengonversi PDF setiap review. Sediakan **Lihat/Cetak PDF** atas permintaan bila tampilan DOCX kurang memadai. Jika viewer tidak layak, pertahankan jalur PDF dan catat keputusan pengguna sebelum menerapkan desain yang mensyaratkan preview DOCX.
- Cetak membuka dialog browser setelah font, gambar, dan render siap; cetak isi dokumen saja tanpa toolbar. Draft memakai `XX.XX` berwarna warning serta penanda DRAFT pada keluaran cetak. Cetak PDF mengikuti PDF hasil LibreOffice, bukan cetak halaman UI.
- Unduhan setelah terbit memakai satu dropdown **Unduh** dengan pilihan **Word (.docx)** dan **PDF (.pdf)**, bersyarat kelayakan preview DOCX. Gunakan komponen dropdown dan ikon chevron Hugeicons yang benar-benar tersedia. Tidak menampilkan dua tombol unduh terpisah.
- Word dihasilkan dengan penggantian placeholder dan pengulangan baris seperti biasa; pengaturan font dan bagian dokumen lain tidak dinormalisasi ulang oleh LibreOffice. Unduh Word menggunakan artefak resmi tersebut. Substitusi font hanya untuk rendering/konversi PDF.
- Gunakan EmbedPDF headless untuk PDF dengan kontrol lihat, zoom, halaman, dan Cetak; tanpa anotasi/editor. Aset worker/WASM dihost aplikasi, bukan mengambil dokumen melalui layanan viewer eksternal.
- PDF dibuat saat diminta, lalu digunakan kembali untuk artefak Word dan profil render yang sama. Klik serentak memakai pekerjaan yang sama. PDF resmi yang sudah tersedia tidak diganti diam-diam setelah instalasi font; versi historis tetap menunjuk hasil tersimpan.

### Font dan placeholder sistem

- Tambahkan pemeriksaan font pada validasi DOCX Templat Surat: baca font efektif dari teks, style/default, tema, tabel, header/footer, dan bagian dokumen yang didukung. Jangan menganggap seluruh isi `fontTable.xml` dipakai. Periksa keluarga serta varian yang relevan; tampilkan keterbatasan pemeriksaan bila struktur belum dapat diidentifikasi.
- Pisahkan galat struktur/placeholder dari peringatan font. Font hilang tidak menggagalkan validasi struktur, generate uji, publikasi templat, penerbitan Surat, atau unduhan. Kesamaan font juga tidak diklaim menjamin tata letak identik.
- Hasil validasi memiliki daftar font digunakan/tersedia/kurang dan varian. Tampilkan bagian **Font Dokumen** di bawah variabel pada kartu **DOCX Sumber**, dengan aksi **Unggah font** pada yang kurang. Header kartu menyediakan **Font tersedia**, membuka panel pencarian keluarga/varian serta unggah.
- Kelola font tambahan pada storage privat aplikasi, bukan melalui sudo atau penulisan direktori sistem. Validasi berkas `.ttf`/`.otf`, isi/metadata melalui Fontconfig, ukuran default 10 MB per berkas, dan hash. Tolak berkas rusak; duplikat hash bersifat aman; font berbeda dengan keluarga/varian yang sama tidak menimpa diam-diam. Penghapusan/penggantian font tidak termasuk tahap awal.
- Konfigurasi Fontconfig dan direktori font yang sama dipakai oleh inventory, converter, dan worker. Setelah upload, bangun cache dan verifikasi font benar-benar terbaca sebelum menandainya siap. Font tersedia untuk seluruh templat, bukan hanya pengunggah.
- Viewer browser memuat keluarga/varian yang diperlukan melalui endpoint font terautentikasi dengan metadata yang benar, bukan mengandalkan font komputer admin. Font sistem yang tidak dapat disajikan sebagai web font memakai fallback browser dengan peringatan; jangan mengklaim ketersediaan server berarti browser telah memuat font.
- Fallback konversi default Arial, lalu Calibri, lalu Liberation Sans, diatur melalui config dan dipastikan tersedia melalui health check. Catat keluarga asli/pengganti pada artefak. Kegagalan converter berbeda dari sekadar font tidak tersedia.
- Inventori font mempunyai fingerprint berisi hash berkas/varian serta versi converter. Perubahan font relevan membatalkan preview/konfirmasi draft terkait; font yang tidak dipakai tidak membatalkan seluruh draft. Preview baru memakai profil terbaru, tanpa mengubah artefak terbit yang sudah disimpan.
- `${nomor_surat}` dan `${tanggal_surat}` menjadi token sistem yang dicadangkan; tolak benturan dengan nama variabel/kolom. Format tanggal disimpan per versi templat, default `d-m-Y`, memakai formatter/locale yang konsisten. DOCX panduan, validasi, generate uji, dan publikasi templat mengenali token ini; hasil uji memakai nomor contoh, tanpa alokasi nomor Surat.

## 3. Penyimpanan dan konsistensi

### Penomoran per periode

`letter_number_periods` mempunyai **9 kolom**:

| Kolom | Kontrak |
| --- | --- |
| `id` | Primary key numerik |
| `uuid` | Identitas publik unik |
| `letter_number_id` | FK klasifikasi, penghapusan dibatasi bila masih dirujuk |
| `year` | Tahun periode |
| `month` | Bulan 1–12 |
| `last_page_number` | Posisi halaman terakhir urutan otomatis, nullable |
| `last_sequence_number` | Posisi nomor terakhir urutan otomatis, nullable |
| `created_at` | Waktu dibuat |
| `updated_at` | Waktu diperbarui |

- Unique `(letter_number_id, year, month)`; kedua posisi harus sama-sama null atau valid. Halaman integer positif, urutan 1–99; halaman tidak dibatasi tipe smallint master lama. Batas implementasi halaman 2.147.483.647; bila habis, tolak tanpa memutar nomor. Tahun input 1–9999 dengan tanggal valid.
- Angka null berarti belum pernah alokasi otomatis dan kandidat awal `01.01`. Sesudah `01.99` lanjut `02.01`. Periode lama tetap tersedia saat bulan berjalan berubah.
- Di detail Penomoran Surat, tampilkan **Urutan per Periode**, aksi **Siapkan Periode**, dan **Lihat pemakaian**. Posisi tidak dapat diedit setelah ada alokasi/pemakaian pada periode itu. Tampilkan perkiraan nomor berikutnya sebagai perkiraan, bukan nomor yang dipesan.
- Migrasi bertahap: tambah tabel dan UI persiapan, pertahankan angka lama sementara; admin memilih periode untuk menyalin angka terakhir master. Simpan jejak persiapan agar tidak diulang. Jangan menebak periode dari timestamp record. Klasifikasi baru cukup memiliki kode/nama dan dapat memulai periode kosong.
- Persiapan setiap klasifikasi existing harus selesai sebelum penerbitan pertamanya. Sesudah itu periode yang belum tersedia dibuat otomatis kosong. Pada tahap akhir, setelah semua klasifikasi existing dipindahkan dan referensi kode diperbarui, hapus `page_number`/`sequence_number` dari `letter_numbers` melalui migration terpisah. Jika belum lengkap, migration harus berhenti dengan petunjuk, bukan membuang angka lama.
- Increment hanya pada `letter_number_periods`. Posisi otomatis terpisah dari nomor manual. Alokasi otomatis melewati nomor yang sedang dipakai; nomor batal tidak menyebabkan posisi mundur. Saran manual mencari nomor tersedia sesudah pilihan yang bentrok dalam periode yang sama.
- Catat seluruh klaim nomor pada `letter_number_assignments`, dengan periode, halaman/urutan, surat, mode otomatis/manual/pemulihan, waktu klaim, dan waktu dilepas. Partial unique index PostgreSQL menjamin satu klaim aktif per nomor dan satu klaim aktif per surat. Pembatalan melepas klaim, tetapi tidak menghapus riwayatnya.
- Ketika otomatis mencapai nomor manual yang pernah dipakai lalu dibatalkan, jangan mengambilnya kembali secara otomatis: jejak klaim manual dipakai untuk melewati nomor itu. Penggunaan ulang nomor batal tetap pilihan manual.

### Surat, draft, artefak, dan audit

| Tabel/modul | Isi dan invariant |
| --- | --- |
| `letters` | UUID, perihal, pembuat, templat asal, status Draft/Terbit/Batal, pointer versi resmi, data pembatalan, timestamps |
| `letter_drafts` | Satu draft kerja per surat, jenis awal/revisi/pemulihan, versi templat, tanggal, pilihan nomor, alasan, revision integer, data formulir/snapshot, fingerprint pemeriksaan |
| `letter_versions` | Versi resmi append-only, nomor/tanggal lengkap, versi templat, snapshot nilai efektif, locale/format, tanggal acuan atribut, alasan, aktor, waktu, pointer artefak Word |
| `letter_artifacts` | Pemilik draft/versi, jenis Word/PDF, disk/path privat, hash/size, fingerprint sumber/render, status, warning font, expiry untuk draft saja |
| `letter_operations` | UUID, jenis operasi, aktor, target/revision, idempotency key dan request hash, status queued/running/succeeded/failed/stale, lease/attempt, hasil/galat aman |
| `letter_audits` | Pelaku beserta identitas/nama tersimpan, kejadian, waktu, alasan, perubahan, dan hubungan ke versi/operasi/nomor |
| `document_fonts` | Metadata font aplikasi, keluarga/varian, hash unik, status instalasi, pengunggah, disk/path privat, timestamps |

- FK relasi memakai id numerik; payload dan route memakai UUID. Enum mengikuti pola proyek. Tambahkan indeks listing, pending operations, foreign key, dan artefak sesuai query nyata.
- Konfigurasi templat tetap relasional. Nilai formulir dinamis, snapshot resolved, dan rincian audit disimpan sebagai JSONB dengan shape/version eksplisit; tidak menambah satu kolom SQL untuk setiap variabel templat.
- Snapshot mencakup urutan pilihan multiple, nilai sumber terpilih, keluarga token/atribut, fallback yang diterapkan, format/locale, tanggal acuan masa kerja, dan hasil string yang diperiksa. Rendering menggunakan snapshot, tidak mengambil master terbaru diam-diam. Refresh master adalah operasi eksplisit.
- Untuk penyesuaian templat, cocokkan nama variabel, jenis, sumber/grup, dan konfigurasi relevan; jangan memakai UUID item yang berubah setelah copy versi sebagai satu-satunya identitas. Pilihan signer hanya dipertahankan jika pemetaan slot tidak ambigu dan kandidat masih sah. Tampilkan ringkasan sebelum konfirmasi; yang ambigu harus dipilih ulang.
- Simpan draft memakai revision compare-and-swap. Payload lama ditolak dengan identitas pengubah; input frontend tetap terlihat. Perubahan membatalkan pemeriksaan. Penerbitan juga membandingkan revision dan versi templat aktif.
- Bukti pemeriksaan mengikat actor yang memeriksa, revision draft, hash DOCX, profil renderer/font, dan jenis preview yang benar-benar dibuka. Viewer mengakui render selesai; konfirmasi manusia tetap terpisah. Jika memilih pemeriksaan melalui PDF, PDF tersebut harus siap. Kegagalan PDF opsional tidak membatalkan pemeriksaan DOCX yang sah.
- Semua pengguna login mengelola surat bersama; akses ini bukan izin mengambil sembarang path. Versi/artefak/operasi tetap di-scope ke induk dan kebijakan fitur. Pemeriksaan kandidat aktif/relasi dilakukan ulang tanpa menimpa snapshot nilainya.

### Penerbitan dan nomor tanpa celah akibat kegagalan

1. Request menerima revision, fingerprint pemeriksaan, alasan bila perlu, pilihan nomor, dan idempotency key. Simpan operasi durable; kirim job setelah commit. Recovery scheduler mengantrekan ulang operasi queued yang belum dijalankan akibat putus proses setelah commit.
2. Worker memeriksa state lalu mengambil snapshot yang telah diperiksa. Tentukan kandidat nomor; render Word resmi ke staging di luar transaksi panjang. Hanya token sistem nomor yang berubah dari preview, bukan data master.
3. Dalam transaksi pendek, kunci aggregate terkait dengan urutan konsisten, pastikan draft/templat/status/kandidat nomor masih sesuai, lalu simpan klaim nomor, increment posisi otomatis bila relevan, versi resmi, pointer status, bukti pemakaian pertama templat, audit, serta hasil operasi bersama-sama.
4. Jika kandidat otomatis berubah selama render, buang staging dan ulangi render untuk kandidat terbaru maksimal tiga kali. Setelah batas, kembalikan galat konflik yang dapat dicoba ulang. Pilihan manual yang bentrok ditolak dengan saran, bukan diam-diam diganti.
5. Jika gagal sebelum commit, nomor/counter tidak berubah dan staging dibersihkan. Jika commit sudah berhasil, retry mengembalikan versi yang sama; tidak membuat nomor/versi kedua. File resmi harus sudah tersedia sebelum pointer metadata di-commit.
6. PDF tidak dibuat otomatis saat terbit pada jalur DOCX. Permintaan PDF kemudian tidak mengubah nomor atau status surat; kegagalan konversi dapat dicoba ulang dari Word resmi yang sama.

Pembatalan melepas klaim aktif dan membuang draft revisi dalam satu transaksi. Pemulihan tidak mengubah tanggal asal, menggunakan draft dengan templat terbaru, dan memeriksa ulang nomor saat commit. Jika nomor lama sudah dipakai, tampilkan saran dalam periode tanggal asal dan minta pilihan eksplisit. Riwayat menautkan pemakai saat ini, tanpa mengubah dokumen lama. Revisi biasa mempertahankan klaim nomor/tanggal dan menghasilkan versi resmi baru.

## 4. Worker, SSE, dan penyimpanan berkas

- Gunakan queue database existing, antrean `letters`, operation UUID sebagai input job, dan perubahan status durable. Lease serta unique constraint operasi mencegah dua worker menjalankan efek yang sama. Dispatch dedup bukan pengganti idempotensi.
- Default anggaran render Word 30 detik, konversi LibreOffice 60 detik, job timeout 90 detik, connection queue khusus `letters` dengan `retry_after` 120 detik; jangan mengubah timeout modul lain. Retry infrastruktur maksimal tiga percobaan dengan backoff 5/15/30 detik; galat input, state usang, atau nomor manual bentrok tidak diulang otomatis.
- Health check memastikan executable LibreOffice/Fontconfig, font fallback, storage, queue, dan dukungan PostgreSQL listener tersedia. Converter memakai argv terstruktur, direktori kerja/profil LibreOffice unik per operasi, timeout, verifikasi exit code dan PDF nyata, serta cleanup. Jangan membuat shell command dari nama file/font pengguna.
- Konversi di-dedup berdasarkan hash Word dan profil converter/font efektif. Font berubah tidak menimpa PDF resmi/historis yang sudah ada; PDF yang belum pernah dibuat merekam profil saat konversi pertamanya.
- SSE ditempatkan pada layout autentikasi utama yang persisten: satu EventSource per tab, hidup saat navigasi Inertia, ditutup saat logout/layout autentikasi berakhir. Event hanya menyegarkan status target yang relevan, bukan me-reset input seluruh halaman.
- Gunakan PostgreSQL `LISTEN/NOTIFY` sebagai sinyal worker → SSE dan `letter_operations` sebagai sumber durable. Koneksi PDO PostgreSQL khusus listener, di luar transaksi dan tidak melalui transaction-pooling. PHP 8.4 menyediakan `Pdo\Pgsql::getNotify()`. Tidak menambah loop SELECT status tiap dua detik di balik SSE.
- Commit status operasi dan `pg_notify` dalam transaksi yang sama. Payload notification hanya identitas operasi; endpoint mengambil data yang diizinkan untuk pengguna. NOTIFY sendiri bukan penyimpanan hasil dan bukan mekanisme otorisasi.
- Saat tersambung: lakukan LISTEN terlebih dahulu, kemudian kirim snapshot operasi aktif serta hasil terminal pengguna selama tujuh hari terakhir. Setelah wake-up, kirim state terbaru yang berhak dibaca; browser dedup berdasarkan UUID operasi + revision status, bukan nomor urut global yang dapat commit tidak berurutan.
- Heartbeat komentar tiap 15 detik, koneksi diputar tiap 60 detik dan browser reconnect dengan backoff maksimum 30 detik. Reconnect selalu mengambil snapshot sehingga tidak mengandalkan sinyal transient yang mungkin hilang. Hasil dokumen tetap tersedia pada detail surat setelah jendela notifikasi tujuh hari berlalu.
- SSE memeriksa ulang sesi/hak akses saat rotasi koneksi, tanpa menahan lock sesi atau transaksi database. Proxy buffering dimatikan untuk endpoint SSE; timeout proxy melebihi umur koneksi. Sediakan kapasitas PHP/database untuk koneksi terbuka dan dokumentasikan batasnya pada deployment.
- Selesai/gagal menampilkan toast singkat dengan aksi menuju hasil, bukan memaksa navigasi atau unduh otomatis ketika pengguna berada di halaman lain. Setelah refresh/tab ditutup, job tetap berjalan dan hasil dapat ditemukan kembali.
- `config/letters.php` memusatkan path, batas proses, expiry, converter/font, dan SSE. Artefak draft default 24 jam; kedaluwarsa memerlukan generate ulang, bukan menghapus isian draft. Staging yatim dibersihkan setelah 24 jam dan hanya bila tidak dirujuk operasi aktif. Dokumen resmi/historis tidak mengikuti expiry draft.

## 5. Tahapan implementasi dan hasil di web

Setiap tahap berikut wajib menambahkan/memperbarui test yang bermakna dan melaporkan kemampuan web. Checkbox progres baru dicentang setelah implementasi dan pemeriksaan otomatis tahap tersebut selesai; checklist visual tetap dimiliki pengguna.

| Tahap | Pekerjaan dan hasil yang dapat dicoba melalui web | Commit yang direncanakan | Uji mandiri |
| --- | --- | --- | --- |
| 1 | Viewer DOCX hanya-baca pada hasil uji Templat Surat existing: buka, zoom, pindah halaman, dan Cetak. Tidak membuat fitur Surat/penerbitan dulu. Pengguna menilai kelayakan sampel sebelum preview DOCX menjadi default. | `feat: add read-only DOCX preview and printing` | [Tahap 1](../../tests/letter/tahap-1.md) |
| 2 | Tabel periode 9 kolom, detail Penomoran Surat, persiapan periode angka lama, dan perlindungan master terikat. Web dapat melihat klasifikasi/periode dan menetapkan angka awal; counter lama belum dihapus. | `feat: add monthly letter numbering periods` | [Tahap 2](../../tests/letter/tahap-2.md) |
| 3 | Pisahkan renderer/snapshot dari orkestrasi generate uji, tambahkan token sistem/format tanggal pada templat, pertahankan regresi DOCX. Web dapat menyiapkan, memvalidasi, dan menguji templat dengan token nomor/tanggal tanpa mengambil nomor Surat. | `feat: support system letter fields and reusable rendering` | [Tahap 3](../../tests/letter/tahap-3.md) |
| 4 | Inventory/pemeriksaan font, unggah font aplikasi, panel Font tersedia, pemuatan font browser, warning nonblocking. Web dapat mengetahui dan melengkapi font pada kartu DOCX Sumber. | `feat: add document font checks and managed font uploads` | [Tahap 4](../../tests/letter/tahap-4.md) |
| 5 | Fondasi data Surat, daftar/detail/tambah/edit/hapus draft, form dinamis, snapshot, revision guard, penyesuaian templat, dan refresh master eksplisit. Web dapat menyimpan dan melanjutkan draft; belum dapat menerbitkan. | `feat: add shared letter draft management` | [Tahap 5](../../tests/letter/tahap-5.md) |
| 6 | Operasi durable, worker, SSE global, generate DOCX draft, preview dan konfirmasi yang terikat revision/font. Web dapat meminta preview, pindah halaman sambil menunggu, membuka hasil, dan menyatakan sudah memeriksa; belum mendapat nomor resmi. | `feat: generate letter previews with queued jobs and SSE` | [Tahap 6](../../tests/letter/tahap-6.md) |
| 7 | Penerbitan idempoten, alokasi otomatis/manual, klaim nomor, Word resmi, riwayat dasar, dan dropdown Unduh. Jika viewer layak, menu Word aktif; PDF ditandai belum tersedia sampai tahap 8. Web dapat menerbitkan dan mengunduh Word bernomor resmi. | `feat: issue letters with atomic numbering and Word downloads` | [Tahap 7](../../tests/letter/tahap-7.md) |
| 8 | Konversi PDF on demand, dedup/retry, EmbedPDF headless serta Cetak PDF. Dropdown Unduh mengaktifkan Word/PDF, dan Lihat/Cetak PDF tersedia pada preview. Web dapat mengunduh PDF tanpa konversi berulang untuk hasil yang sama. | `feat: add on-demand PDF conversion and viewing` | [Tahap 8](../../tests/letter/tahap-8.md) |
| 9 | Revisi satu draft, alasan, versi historis, pembatalan, pemulihan, pelepasan/penggunaan ulang nomor, dan timeline lengkap. Web dapat menjalankan seluruh siklus Surat serta menelusuri nomor/dokumen historis. | `feat: add letter revisions cancellation and recovery` | [Tahap 9](../../tests/letter/tahap-9.md) |
| 10 | Cleanup/recovery scheduler, konkurensi nyata, deployment health check, regresi integrasi, dan migration penghapusan counter master setelah prasyarat persiapan terpenuhi. Web menggunakan seluruh alur dengan hanya satu sumber counter. | `feat: finalize letter reliability and retire legacy counters` | [Tahap 10](../../tests/letter/tahap-10.md) |

### Pemeriksaan otomatis per tahap

- Tahap 1: fixture DOCX representatif, autentikasi/berkas, read-only controls, lifecycle blob/font loading, dan test DOM nonvisual. Fidelity/hasil print dinilai pengguna, bukan assertion jumlah elemen saja.
- Tahap 2: constraint periode, copy legacy tanpa kehilangan angka, duplikat periode, null pasangan counter, dan endpoint protected fields. Service alokasi diuji tanpa mengekspos tombol penerbitan sebelum waktunya.
- Tahap 3–4: regresi renderer literal XML, tabel berulang/urutan, header/footer, fallback/tanggal, namespace token sistem, struktur font/style/tema, file font rusak/duplikat, inventory gagal, serta warning nonblocking. Perubahan font relevan mengusangkan pemeriksaan; font tidak relevan tidak membatalkannya.
- Tahap 5–6: scope UUID/aktor, validasi dinamis, snapshot tidak berubah oleh master, migrasi konfigurasi tidak ambigu, CAS, revision stale, operasi ganda, retry, SSE auth/reconnect/dedup, logout, dan hasil selesai setelah navigasi. Uji LISTEN/NOTIFY antarkoneksi pada PostgreSQL disposable; jangan mengganti dengan mock database.
- Tahap 7–9: auto/manual/rollover/periode lampau, saran konflik, tidak menghabiskan nomor saat gagal, retry setelah commit, partial unique klaim, invalidasi preview, revisi bernomor tetap, pemulihan berganti nomor, riwayat berulang, dedup PDF, serta Word tetap identik dengan artefak tersimpan setelah PDF dibuat.
- Tahap 10: runner konkurensi PostgreSQL terpisah untuk penerbitan serentak, pembatalan/pemulihan versus klaim lain, duplicate jobs, dan master/template berubah saat generate. Uji crash boundary/recovery dan cleanup artefak aktif/resmi. Jalankan suite terdampak termasuk regresi Templat Surat/Penomoran; hasil skip bukan bukti lulus.
- Saat PHP berubah jalankan Pint; saat frontend berubah jalankan test nonvisual terkait, lint, `types:check`, dan build. Regenerasi Wayfinder saat signature route berubah. Jangan menjalankan formatter pada perubahan pengguna yang tidak terkait hanya untuk membuat commit ini bersih.

## 6. Referensi teknis

- [docx-preview: batas pemenggalan halaman](https://github.com/VolodymyrBaydalka/docxjs#breaks).
- [Puter Documents: batas preview hanya-baca](https://github.com/HeyPuter/everything/blob/main/src/documents/docs/conformance.md#best-effort-opening-and-read-only-previews).
- [EmbedPDF headless React](https://www.embedpdf.com/docs/react/headless/introduction) dan [plugin cetak](https://www.embedpdf.com/docs/react/headless/plugins/plugin-print).
- [LibreOffice command line](https://help.libreoffice.org/latest/en-GB/text/shared/guide/start_parameters.html) dan [substitusi font](https://help.libreoffice.org/latest/en-GB/text/shared/optionen/01010700.html).
- [Fontconfig](https://fontconfig.pages.freedesktop.org/fontconfig/fontconfig-user.html).
- [PHP 8.4 PostgreSQL getNotify](https://www.php.net/manual/en/pdo-pgsql.getnotify.php) dan [PostgreSQL NOTIFY](https://www.postgresql.org/docs/18/sql-notify.html).
- [Inertia persistent layouts](https://inertiajs.com/docs/v3/the-basics/layouts) dan [Laravel SSE](https://laravel.com/docs/13.x/responses#event-streams).
