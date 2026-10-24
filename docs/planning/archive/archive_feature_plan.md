# Archive — Rencana Fitur dan Implementasi

Status: implementasi bertahap; tahap 1 selesai, tahap 2–10 belum selesai.

Acuan proses bisnis: [archive_probis.md](archive_probis.md). Dokumen ini mencatat keputusan teknis terbaru dan menggantikan bagian rancangan penyimpanan pada dokumen tersebut: gunakan **dua tabel bersama untuk seluruh modul**, dengan snapshot JSON, bukan tabel arsip khusus per modul. Keputusan terbaru juga mencakup dukungan MySQL/PostgreSQL, konfirmasi ulang ketika data berubah, dan penempatan menu melalui `NavSecondary`.

## 1. Tujuan dan cakupan

- Data yang dihapus keluar dari tabel aktif dan tetap tersedia untuk dipulihkan melalui Archive.
- Fondasi penyimpanan dan daftar Archive digunakan lintas modul. Implementasi pengarsipan tahap awal hanya untuk grup dan item Reference.
- Penambahan modul berikutnya menggunakan dua tabel yang sama, tanpa membuat tabel arsip khusus modul tersebut.
- Aturan pengarsipan, validasi snapshot, pemeriksaan konflik, dan pemulihan tetap ditentukan per jenis entitas.
- Semua pengguna login dapat melihat, memulihkan, dan melakukan Delete Forever.
- Sediakan lima mode restore, pemulihan sebagian, penyelesaian konflik grup, dan Delete Forever sesuai proses bisnis.
- Setiap restore melalui pratinjau dan konfirmasi. Delete Forever memiliki konfirmasi yang menjelaskan sasaran serta sifat permanennya.
- Belum ada bulk operasi lintas paket, kedaluwarsa otomatis, atau penyimpanan riwayat lengkap untuk data yang sudah selesai dipulihkan.

## 2. Fondasi dua tabel bersama

### `archives`

Satu baris mewakili satu paket penghapusan atau satu data yang dihapus mandiri. Tabel ini menjadi sumber daftar arsip terpusat.

| Kelompok kolom   | Isi                                                                                                                             |
| ---------------- | ------------------------------------------------------------------------------------------------------------------------------- |
| Identitas        | ID internal dan UUID entri arsip yang unik                                                                                      |
| Klasifikasi      | Modul, jenis paket/data mandiri, dan penanda entitas utama                                                                      |
| Penyajian        | Label data utama untuk daftar dan pencarian                                                                                     |
| Pelaku dan waktu | Pengguna penghapus, salinan nama penghapus, waktu pengarsipan, dan timestamps                                                   |
| Konsistensi      | Revision paket untuk mendeteksi perubahan                                                                                       |
| Konteks          | Metadata JSON untuk identitas asal yang masih diperlukan, status penyelesaian induk, dan pemetaan tujuan pemulihan/penggabungan |

- Kolom umum tidak menggunakan nama khusus Reference seperti `reference_group_id` atau `job_title_id`.
- Metadata konteks diinterpretasikan oleh penangan entitas yang terdaftar, bukan oleh kode dinamis dari payload.
- Relasi pelaku bersifat nullable dengan `nullOnDelete`; salinan nama mempertahankan informasi penghapus jika akun sudah tidak tersedia.
- Konteks paket tetap tersedia setelah induk dipulihkan selama masih ada children yang belum selesai diproses.

### `archive_records`

Satu baris mewakili satu data asal yang belum dipulihkan atau dihapus permanen. Induk dan setiap child disimpan sebagai record terpisah.

| Kelompok kolom  | Isi                                                                             |
| --------------- | ------------------------------------------------------------------------------- |
| Identitas arsip | ID internal, UUID baris arsip yang unik, dan relasi ke `archives`               |
| Identitas asal  | Penanda entitas terdaftar, primary key asal, dan UUID asal bila tersedia        |
| Hubungan        | Identitas entitas/record induk asal bila tersedia                               |
| Penyajian       | Label teks untuk pencarian dan daftar item                                      |
| Versi           | `schema_version` untuk format snapshot                                          |
| Data            | `payload` JSON yang berisi kolom asal, relasi asal, urutan, dan timestamps asli |

- Gunakan foreign key dari record ke paket dengan cascade saat paket dihapus permanen.
- Hubungan asal tidak memakai foreign key ke tabel aktif yang dapat menyebabkan arsip ikut terhapus.
- Hubungan induk tidak boleh menyebabkan children arsip terhapus ketika record induk selesai dipulihkan.
- Identitas asal harus unik dalam paket untuk entitas yang sama, tetapi dapat muncul lagi pada paket berbeda dari siklus penghapusan berbeda.
- Identitas baris arsip berbeda dari identitas data asal. Pemilihan checkbox dan endpoint item menggunakan UUID baris arsip.
- Simpan snapshot dari daftar kolom yang diizinkan penangan entitas, bukan serialisasi model beserta seluruh relasinya secara otomatis.

### Contoh paket Reference

- Hapus grup dengan tiga item menghasilkan satu baris `archives` dan empat baris `archive_records`: satu grup serta tiga item.
- Hapus satu item mandiri menghasilkan satu baris `archives` dan satu baris `archive_records`, dengan konteks grup asal.
- Restore induk saja menghapus record grup yang berhasil dipulihkan. Paket dan ketiga record item tetap tersedia, dengan tujuan induk aktif dicatat pada konteks paket.
- Setelah semua record selesai diproses, hapus paket beserta konteks yang tidak lagi diperlukan.

## 3. Kompatibilitas database

- Database produksi yang didukung adalah MySQL dan PostgreSQL sesuai versi yang didukung Laravel yang digunakan aplikasi.
- Deklarasikan payload melalui `$table->jsonb('payload')`: pada Laravel 13 yang terpasang, PostgreSQL menggunakan `JSONB` dan MySQL menggunakan `JSON`.
- Konteks JSON menggunakan pendekatan tipe kolom yang sama. Baca melalui cast Eloquent.
- Pencarian, filter, pagination, identitas, dan hubungan utama memakai kolom biasa; pencarian konten children menggunakan label record.
- Gunakan Eloquent/Query Builder untuk query JSON yang benar-benar diperlukan. Hindari ketergantungan operator, indeks, atau SQL khusus PostgreSQL.
- Pemeriksaan konflik unik dilakukan terhadap tabel aktif melalui penangan entitas; constraint tabel aktif tetap menjadi penjagaan terakhir.
- Evaluasi konflik mengikuti aturan unik dan collation database tujuan. Uji perbedaan yang relevan pada kedua engine.
- Fingerprint konfirmasi menggunakan representasi data yang dikanonisasi, bukan urutan key atau format teks JSON yang dikembalikan database.
- Perpindahan engine tetap membutuhkan migrasi skema dan data; snapshot tidak bergantung pada format penyimpanan biner suatu engine.
- SQLite boleh tetap digunakan untuk pengujian fitur existing, tetapi bukan bukti tunggal kompatibilitas produksi atau penguncian transaksi.

## 4. Versi snapshot dan perluasan modul

- Gunakan penanda entitas stabil yang didaftarkan aplikasi, misalnya `reference_group` dan `reference_item`.
- Daftarkan pemetaan entitas ke penangan di kode aplikasi. Jangan menjalankan nama tabel, class, atau perintah yang berasal dari payload/request.
- Penangan menentukan kolom snapshot, versi yang didukung, relasi induk, validasi unik, urutan, dan cara menulis kembali data aktif.
- Perubahan struktur modul ditangani melalui pembacaan versi snapshot yang kompatibel atau transformasi versi yang eksplisit.
- Jika versi snapshot tidak didukung, tampilkan alasan dan tahan pemulihan record terkait; jangan membuang data diam-diam.
- Penambahan modul tidak membutuhkan tabel arsip baru. Cukup tambah penangan, pemetaan entitas, penyajian, dan pengujian perilakunya.
- Tahap awal hanya mendaftarkan entitas Reference; jangan membangun penangan modul lain sebelum diperlukan.

## 5. Pembagian tanggung jawab backend

- Controller menangani request/response dan memanggil operasi bisnis, tanpa menampung logika restore yang kompleks.
- Form Requests memvalidasi mode, kepemilikan item terhadap paket, tujuan penggabungan, nama baru, dan token konfirmasi.
- Resource menyajikan kontrak daftar, detail, pratinjau, konflik, dan hasil tanpa membuka payload internal secara otomatis.
- Rencanakan layanan khusus di `app/Services/Archive` untuk pengarsipan, perhitungan pratinjau, dan eksekusi restore/Delete Forever, serta penangan Reference.
- Pemeriksa konflik yang sama dipakai oleh pratinjau dan eksekusi agar keduanya tidak memiliki aturan berbeda.
- Tidak ada dependency baru yang diperlukan.

## 6. Pengarsipan Reference

- Endpoint delete Reference yang ada tetap digunakan; controller memanggil layanan pengarsipan.
- Hapus grup: kunci grup, baca ulang kondisi terbaru, simpan paket dan seluruh record aktif, lalu hapus grup beserta item aktifnya dalam transaksi yang sama.
- Hapus item: kunci grup, baca ulang item dalam cakupan grup tersebut, simpan paket mandiri dan record, lalu hapus item aktif.
- Item yang sudah diarsipkan mandiri tidak dimasukkan ulang ke paket ketika grup kemudian dihapus.
- Kegagalan penyimpanan snapshot atau penghapusan aktif membatalkan seluruh operasi.
- Dialog hapus menjelaskan pemindahan ke arsip dan kemampuan pemulihan.
- Pembacaan/penyalinan record besar menggunakan chunk agar tidak menghidrasi seluruh model sekaligus, dengan transaksi tetap menjaga kesatuan paket.
- Tambah constraint UUID unik pada grup/item aktif melalui migration baru setelah pemeriksaan duplikasi. Jangan mengubah identitas duplikat otomatis.

## 7. Lima mode restore

| Mode API               | Aksi pengguna            | Perilaku                                         |
| ---------------------- | ------------------------ | ------------------------------------------------ |
| `parent_only`          | Pulihkan Induk Saja      | Pulihkan grup tanpa children                     |
| `parent_with_children` | Pulihkan Induk + Item    | Pulihkan grup beserta seluruh children yang aman |
| `children_all`         | Pulihkan Semua Item      | Pulihkan seluruh children tersisa ke induk aktif |
| `child`                | Pulihkan pada satu baris | Pulihkan satu child                              |
| `children_selected`    | Pulihkan Terpilih        | Pulihkan children yang dipilih melalui checkbox  |

- Mode children membutuhkan induk aktif atau grup aktif tujuan penggabungan yang telah dikonfirmasi.
- Item mandiri menggunakan pemulihan satu item dengan grup asal yang aktif sebagai tujuan.
- Restore biasa mempertahankan ID, UUID, dan timestamps asli. Benturan identitas menahan data terkait.
- `parent_with_children` mempertahankan urutan asal. Mode children dan penggabungan menambahkan item di akhir menurut urutan relatif arsip.
- Mode children tidak ikut memulihkan record di luar cakupan yang diminta.
- Konflik induk menahan paket. Konflik child yang sudah ditampilkan pada pratinjau hanya melewati child tersebut; child lain yang aman tetap dipulihkan.
- Jika induk kembali dihapus, tahan mode children sampai induk tersedia atau pengguna mengonfirmasi grup tujuan lain.

### Penyelesaian konflik grup

- Tampilkan grup arsip dan grup aktif berdampingan, meliputi nama, deskripsi, daftar item, dan konten bentrok.
- Pengguna dapat mengonfirmasi penggabungan, mengubah nama grup arsip, atau membuka edit grup aktif kemudian mencoba kembali.
- Nama sama tidak otomatis dianggap sebagai identitas yang sama.
- Penggabungan memakai identitas grup aktif dan mencatat tujuan pada konteks paket untuk percobaan ulang children tersisa.
- Ubah nama grup arsip hanya tersedia sebelum record grup diselesaikan dan mengikuti validasi nama Reference.
- Perubahan nama atau pilihan tujuan menghasilkan pratinjau baru sebelum konfirmasi.

## 8. Pratinjau, konfirmasi, dan konsistensi

- Pratinjau tidak mengubah database. Persiapan konfirmasi Delete Forever juga tidak menghapus data.
- Backend menghasilkan token terlindungi melalui fasilitas enkripsi Laravel, terikat pada pengguna, paket, aksi, mode, item terpilih, tujuan, dan fingerprint kondisi relevan.
- Pada konfirmasi, baca ulang kondisi di dalam transaksi dan bandingkan dengan kondisi yang telah ditampilkan.
- Jika kondisi relevan berubah, batalkan operasi tanpa perubahan dan kembalikan `409` dengan kode `archive_changed`. Tampilkan kondisi terbaru dan minta pengguna meninjau serta mengonfirmasi ulang.
- Aturan perubahan kondisi berlaku untuk restore maupun Delete Forever. Jangan memasukkan data tambahan yang belum ditampilkan ke dalam operasi.
- Konflik yang sudah diketahui dan tetap sama pada pratinjau tetap mendukung restore sebagian.
- Kunci paket arsip dan grup aktif terkait dalam urutan konsisten. Operasi tambah, edit, hapus, dan reorder Reference mengikuti koordinasi penguncian yang sama; update item juga perlu mengikuti kunci grup.
- Constraint yang berubah menjadi konflik pada saat penulisan membatalkan transaksi dan meminta pratinjau ulang. Kegagalan teknis lain juga membatalkan transaksi dan dilaporkan sebagai kegagalan operasi.
- Permintaan berulang tidak boleh membuat duplikasi, memulihkan induk ulang, atau menghapus data di luar sasaran.

## 9. Delete Forever dan pembersihan

| Cakupan           | Perilaku                                                   |
| ----------------- | ---------------------------------------------------------- |
| Paket lengkap     | Hapus seluruh record arsip tersisa dan paketnya            |
| Satu child/item   | Hapus satu record arsip yang dituju                        |
| Children terpilih | Hapus hanya record arsip yang dipilih dalam paket tersebut |

- Konfirmasi wajib menampilkan nama sasaran, jumlah item, dan keterangan bahwa data tidak dapat dipulihkan.
- Operasi hanya menghapus tabel arsip; induk dan children yang sudah aktif tetap utuh.
- Penghapusan satu paket maupun pilihan beberapa record berjalan secara transaksional.
- Setelah restore, hapus record yang berhasil dipulihkan. Record konflik tetap di arsip.
- Metadata hubungan induk dan tujuan tetap tersedia selama dibutuhkan record tersisa.
- Jika child terakhir dihapus tetapi record induk belum dipulihkan, pertahankan paket dan record induk.
- Jika tidak ada record tersisa, bersihkan paket dan konteksnya.
- Tidak ada penghapusan permanen induk arsip secara terpisah yang meninggalkan children tanpa konteks pemulihan; gunakan aksi paket lengkap atau aksi children.

## 10. Route dan kontrak frontend

| Method dan route                                 | Named route                       | Tanggung jawab                                             |
| ------------------------------------------------ | --------------------------------- | ---------------------------------------------------------- |
| `GET /archives`                                  | `archives.index`                  | Daftar terpusat                                            |
| `GET /reference/archive`                         | `ref.archive.index`               | Daftar yang sama dengan konteks Reference                  |
| `GET /archives/{archive:uuid}`                   | `archives.show`                   | Detail dan children berpaginasi                            |
| `POST /archives/{archive:uuid}/preview`          | `archives.preview`                | Pratinjau restore atau persiapan konfirmasi Delete Forever |
| `PATCH /archives/{archive:uuid}/reference-group` | `archives.reference-group.update` | Ubah nama grup arsip melalui penangan Reference            |
| `POST /archives/{archive:uuid}/restore`          | `archives.restore`                | Eksekusi token restore                                     |
| `DELETE /archives/{archive:uuid}`                | `archives.destroy`                | Eksekusi token Delete Forever                              |

- Semua endpoint memerlukan autentikasi dan mempertahankan perlindungan request bawaan aplikasi.
- Gunakan UUID, validasi cakupan paket, named routes, dan Wayfinder.
- Pratinjau menerima aksi, mode, UUID baris arsip terpilih, dan tujuan penggabungan bila diperlukan.
- Konfirmasi menggunakan token yang telah diterbitkan; backend tidak mempercayai daftar kandidat atau status aman dari browser.
- Kontrak pratinjau memuat induk, tujuan, kandidat, konflik beserta alasan, ringkasan jumlah, dan token konfirmasi.
- Hasil eksekusi memuat jumlah berhasil, tersisa, serta apakah paket masih tersedia.
- Gunakan halaman Inertia di `resources/js/pages/archive`. Daftar memakai props `resources`, `filters`, dan konteks modul; detail memuat children berpaginasi.
- Gunakan `useHttp` untuk pratinjau dan eksekusi JSON, serta navigasi Inertia untuk perpindahan halaman. Definisikan tipe TypeScript untuk mode, resource, konflik, pratinjau, dan hasil.

## 11. Navigasi dan antarmuka

### Penempatan menu

- Tambahkan **Archive** pada `navigations.navSecondary` di `resources/js/components/app-sidebar.tsx`.
- Render komponen `NavSecondary` dari `resources/js/components/nav-secondary.tsx` pada bagian bawah konten sidebar, sebelum area pengguna.
- Menu mengarah ke `archives.index` melalui Wayfinder.
- Gunakan `Link` Inertia untuk navigasi internal dan penanda aktif pada daftar maupun detail Archive.
- Gunakan ikon dari sistem Hugeicons yang sudah dipakai aplikasi, dengan nama yang diverifikasi pada package terpasang.
- Tombol **Arsip** pada daftar Reference tetap tersedia dan mengarah ke daftar dengan konteks Reference.

### Daftar arsip

- Satu baris adalah satu paket grup atau satu item yang diarsipkan mandiri. Children paket ditampilkan pada detail.
- Kolom: Data, Modul, Jenis, Induk, Item tersisa, Status induk, Diarsipkan pada, Diarsipkan oleh, dan Aksi.
- Sembunyikan kolom Modul pada konteks Reference. Jenis ditampilkan sebagai Grup/Item untuk entitas Reference.
- Aksi per baris: **Detail**, **Pulihkan…**, dan **Hapus Permanen…**. Nama data juga membuka detail.
- **Pulihkan…** membuka bagian pemulihan pada detail, bukan langsung mengeksekusi restore.
- Kontrol: pencarian nama/konten, filter Modul pada daftar terpusat, filter Jenis, reset filter, pagination, dan pengaturan jumlah baris.
- Urutan default `archived_at DESC, id DESC`; gunakan indeks sesuai query daftar dan relasi record.
- Parameter pencarian, filter, pagination, dan konteks tersimpan di URL. Tidak ada checkbox lintas paket pada daftar utama.
- Status konflik dihitung pada pratinjau dan tidak disimpan sebagai status tetap pada daftar.

### Detail dan selection

- Tampilkan informasi asal, pelaku, waktu pengarsipan, status induk, tujuan, dan record tersisa.
- Sediakan aksi sesuai status induk dan lima mode restore.
- Selection dimiliki halaman detail dan dipertahankan lintas halaman tabel dalam paket yang sama menggunakan UUID baris arsip.
- Checkbox header hanya memilih halaman saat ini. Tampilkan jumlah pilihan total dan aksi hapus pilihan.
- Pisahkan aksi restore dan Delete Forever secara jelas.
- Gunakan komponen tabel, pagination, dialog, formulir, loading, dan feedback yang sudah tersedia; pertahankan pola TanStack Table v9 aplikasi.
- Perbandingan grup berdampingan pada layar lebar dan bertumpuk pada layar kecil; alasan konflik dijelaskan dengan teks.
- Setelah operasi berhasil, bersihkan selection dan muat ulang detail. Kembali ke daftar jika paket sudah habis.
- Hasil pemulihan sebagian menampilkan jumlah berhasil dan rincian yang tetap diarsipkan, dengan akses untuk mencoba kembali.

## 12. Pemetaan skill berdasarkan pekerjaan

Pemetaan ini adalah panduan memilih skill sesuai pekerjaan aktual, bukan daftar yang harus dibaca seluruhnya pada setiap tahap. Aktifkan skill ketika memasuki domainnya, baca aturan/referensi yang relevan saja, dan gunakan dokumentasi package untuk API sesuai versi terpasang.

### Skill utama per domain

| Skill                                                                                   | Kapan digunakan                                                                                                                              | Tahap terkait                                       |
| --------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------- |
| [laravel-best-practices](../../../.agents/skills/laravel-best-practices/SKILL.md)       | Menulis PHP Laravel: migration, model, request, controller, query, transaksi, dan layanan. Pilih rule yang sesuai area perubahan             | 1–7 dan 10 jika mengubah PHP                        |
| [laravel-patterns](../../../.agents/skills/laravel-patterns/SKILL.md)                   | Menentukan batas controller/layanan/penangan dan koordinasi transaksi; tidak perlu mengulang pembahasan arsitektur untuk perubahan PHP kecil | 1–6 sesuai perubahan arsitektur                     |
| [testing-best-practices](../../../.agents/skills/testing-best-practices/SKILL.md)       | Memilih dan menulis pengujian perilaku, validasi, isolasi database, serta konkurensi                                                         | Setiap tahap yang menambah/mengubah perilaku dan 10 |
| [inertia-react-development](../../../.agents/skills/inertia-react-development/SKILL.md) | Halaman React, navigasi Inertia, request `useHttp`, reload props, dan penanganan hasil operasi                                               | 7–9                                                 |
| [wayfinder-development](../../../.agents/skills/wayfinder-development/SKILL.md)         | Menghubungkan frontend ke route/backend serta memperbarui fungsi route yang dibutuhkan frontend                                              | 7–9; tidak diperlukan untuk tahap backend-only      |
| [shadcn](../../../.agents/skills/shadcn/SKILL.md)                                       | Memakai atau menyusun komponen existing seperti sidebar, dialog, checkbox, menu, field, dan tabel                                            | 7–9                                                 |
| [tailwindcss-development](../../../.agents/skills/tailwindcss-development/SKILL.md)     | Menulis utility class untuk layout, spacing, responsive behavior, dan tampilan komponen                                                      | 7–9 ketika mengubah styling                         |
| [hugeicons](../../../.agents/skills/hugeicons/SKILL.md)                                 | Memilih atau mengubah ikon menu Archive dan tombol aksi; verifikasi nama pada katalog/package terpasang                                      | 7–9 ketika mengerjakan ikon                         |
| [getting-started](../../../.agents/skills/getting-started/SKILL.md)                     | Menyusun tabel Archive melalui hook TanStack Table v9 existing; tidak membuat fondasi tabel kedua                                            | 7 dan 8                                             |
| [table-state](../../../.agents/skills/table-state/SKILL.md)                             | Mengelola selection, checkbox header, pagination, dan subscription state tabel                                                               | 8; 9 jika mengubah state selection                  |

### Skill bersyarat

| Skill                                                                                   | Pemicu yang membenarkan penggunaan                                                                                                                                             |
| --------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| [design-taste-frontend](../../../.agents/skills/design-taste-frontend/SKILL.md)         | Menyusun atau meninjau layout layar Archive baru pada tahap 7–9; gunakan arahan app screen dan pertahankan bahasa visual aplikasi                                              |
| [ui-ux-pro-max](../../../.agents/skills/ui-ux-pro-max/SKILL.md)                         | Ada keputusan interaksi/aksesibilitas yang perlu ditelaah, misalnya perbandingan konflik, fokus dialog, atau kejelasan hasil parsial; cari panduan untuk masalah tersebut saja |
| [goey-toast](../../../.agents/skills/goey-toast/SKILL.md)                               | Mengubah implementasi notifikasi restore/Delete Forever atau pesan pengarsipan dengan komponen toast existing; tidak perlu memasang package baru                               |
| [codebase-design](../../../.agents/skills/codebase-design/SKILL.md)                     | Batas antarmuka penangan atau layanan belum jelas dan membutuhkan keputusan desain; tidak wajib jika kontrak tahap 1 sudah cukup                                               |
| [typescript-advanced-types](../../../.agents/skills/typescript-advanced-types/SKILL.md) | Benar-benar membutuhkan generic/conditional/mapped type yang kompleks; resource interface dan union mode biasa tidak cukup menjadi alasan                                      |

- Jika ada dua entri skill dengan nama yang sama dalam katalog, gunakan satu path kanonis yang dicantumkan di sini; jangan membaca keduanya tanpa kebutuhan.
- Tidak menjalankan `infer-conventions`, migrasi Table v8, pemasangan skill, image generation, Sites, atau skill lain yang tidak terkait pekerjaan Archive.
- `code-review` hanya digunakan jika ada permintaan review branch/diff dengan titik pembanding; verifikasi per tahap bukan alasan untuk menjalankan seluruh workflow review tersebut.
- Mapping skill tidak memberi otorisasi menambah dependency, mengubah struktur di luar rencana, mengirim pesan eksternal, atau memperluas cakupan fitur.

## 13. Tahap implementasi dan commit

Tahap dijalankan berurutan. Satu tahap adalah satu unit kerja yang dapat diverifikasi dan dilanjutkan pada sesi berikutnya. Setiap tahap menyertakan pengujian yang relevan saat perilaku diperkenalkan; tahap 10 bukan tempat menunda seluruh pengujian.

Setiap commit mencakup implementasi, pengujian terkait, dan pembaruan checkpoint dalam dokumen ini. Commit message berikut adalah pesan yang direncanakan, bukan perintah untuk membuat commit saat dokumen rencana ini sedang ditulis.

### Tahap 1 — Fondasi penyimpanan dan kontrak snapshot

- **Dependensi:** proses bisnis dan keputusan dua tabel pada dokumen ini.
- **Pekerjaan:** tetapkan kolom final dua tabel, bentuk metadata hubungan/tujuan, payload versi 1, penanda entitas, serta kontrak minimal penangan Reference. Buat migration, model, factory, cast JSON, indeks, dan constraint UUID aktif setelah preflight duplikasi.
- **Batas:** belum mengubah delete Reference atau menambahkan menu/endpoint yang belum berfungsi. Jangan membuat penangan modul lain.
- **Skill:** Laravel untuk schema/model/kontrak; testing untuk cakupan perilaku relasi dan snapshot. `codebase-design` hanya bila batas kontrak perlu diperdalam.
- **Selesai jika:** migration dan penyimpanan JSON bekerja pada pengujian; identitas asal, hubungan children, dan konteks sisa paket dapat direpresentasikan tanpa tabel khusus Reference. Catat kontrak final untuk tahap berikutnya.
- **Commit:** `feat(archive): add shared archive storage and snapshot contracts`

### Tahap 2 — Pengarsipan dari delete Reference

- **Dependensi:** tahap 1.
- **Pekerjaan:** implementasikan layanan pengarsipan grup dan item, snapshot kolom yang diizinkan, chunk, transaksi, dan koordinasi penguncian operasi aktif. Hubungkan ke endpoint delete existing. Perbarui keterangan dialog dan feedback agar tidak lagi menyatakan data tidak dapat dikembalikan.
- **Batas:** belum ada restore, Delete Forever, atau menu Archive. Tahap ini merupakan checkpoint pengembangan; jangan merilis perubahan alur hapus ke pengguna sebelum alur pemulihan selesai.
- **Skill:** Laravel dan testing; Inertia/shadcn/goey-toast hanya pada perubahan dialog atau notifikasi frontend yang benar-benar dilakukan.
- **Selesai jika:** delete grup/item menghasilkan arsip lengkap, rollback terbukti, arsip mandiri tetap terpisah, dan pengujian delete serta reorder Reference yang terdampak lulus.
- **Commit:** `feat(reference): archive groups and items on deletion`

### Tahap 3 — Pemeriksaan konflik dan token pratinjau

- **Dependensi:** tahap 1–2.
- **Pekerjaan:** implementasikan pemeriksa konflik, dukungan versi snapshot, cakupan kandidat sesuai mode, fingerprint kanonis, dan token terikat pengguna/aksi/paket/pilihan/tujuan. Tambahkan endpoint JSON pratinjau untuk restore maupun persiapan Delete Forever.
- **Batas:** pratinjau tidak menulis data; endpoint eksekusi belum ditambahkan. Jangan memasang route halaman Inertia yang komponennya belum ada.
- **Skill:** Laravel dan testing; belum membutuhkan skill frontend atau Wayfinder.
- **Selesai jika:** pratinjau seluruh mode dapat diuji dengan fixture arsip, konflik/dependensi dijelaskan, snapshot tidak didukung ditahan, cakupan record divalidasi, dan token tidak bisa dialihkan ke sasaran/pengguna lain.
- **Commit:** `feat(archive): add conflict previews and confirmation tokens`

### Tahap 4 — Restore induk dan paket

- **Dependensi:** tahap 3.
- **Pekerjaan:** implementasikan `parent_only` dan `parent_with_children`, endpoint restore, pemeriksaan token/kondisi terbaru di dalam transaksi, restore sebagian untuk konflik yang sudah diketahui, serta pembersihan record yang berhasil. Pertahankan konteks paket ketika induk selesai tetapi children masih tersisa.
- **Batas:** mode children terpisah dan penggabungan belum diekspos sebagai kemampuan yang dapat dieksekusi. Pembaca pratinjau boleh sudah mengenali modenya, tetapi endpoint eksekusi hanya menerima mode yang telah selesai.
- **Skill:** Laravel dan testing.
- **Selesai jika:** kedua mode menjaga identitas/timestamps/urutan, tidak menimpa konflik, dan perubahan setelah pratinjau menghasilkan `archive_changed` tanpa mutasi. Uji induk tanpa children serta record tersisa setelah restore parsial.
- **Commit:** `feat(archive): restore reference parents and packages`

### Tahap 5 — Restore children dan penyelesaian konflik

- **Dependensi:** tahap 4.
- **Pekerjaan:** tambahkan `children_all`, `child`, dan `children_selected`; rename grup arsip; penggabungan eksplisit; serta penyimpanan tujuan agar percobaan ulang konsisten. Lengkapi kontrak resource konflik/hasil.
- **Batas:** tidak menambah bulk lintas paket atau edit snapshot sembarang. Pemulihan induk dan children tetap menggunakan pemeriksa konflik serta protokol konfirmasi yang sama.
- **Skill:** Laravel dan testing.
- **Selesai jika:** lima mode bekerja melalui backend, pilihan hanya memproses UUID dalam paket, urutan append benar, merge/rename teruji, dan induk hilang atau dihapus kembali ditangani tanpa kehilangan konteks.
- **Commit:** `feat(archive): restore selected children and resolve group conflicts`

### Tahap 6 — Delete Forever

- **Dependensi:** tahap 3–5, termasuk token konfirmasi dan aturan pembersihan paket.
- **Pekerjaan:** implementasikan endpoint Delete Forever untuk paket, satu record, serta children terpilih; pemeriksaan perubahan kondisi; transaksi; dan pembersihan paket/konteks yang sudah habis.
- **Batas:** tidak menghapus data aktif, tidak membuat scheduled purge, dan tidak menyediakan penghapusan induk arsip yang meninggalkan children tanpa konteks.
- **Skill:** Laravel dan testing.
- **Selesai jika:** ketiga cakupan bekerja, operasi berulang tidak melebar ke data lain, rollback teruji, dan child terakhir tidak menghapus induk yang masih diarsipkan.
- **Commit:** `feat(archive): permanently delete archived packages and records`

### Tahap 7 — Daftar, detail, dan dua pintu navigasi

- **Dependensi:** tahap 1–6 untuk kontrak dan kapabilitas backend yang stabil.
- **Pekerjaan:** tambahkan query/resource daftar dan detail berpaginasi, route GET, tipe frontend, halaman Inertia, pencarian/filter, kolom daftar, dan status induk. Hubungkan menu melalui `NavSecondary` serta tombol Arsip Reference menggunakan Wayfinder. Perbarui fungsi route melalui mekanisme Wayfinder proyek.
- **Batas:** navigasi/halaman baca harus berfungsi; tombol eksekusi restore/purge baru diekspos setelah tahap 8/9. Hindari tombol aktif yang belum memiliki alur lengkap.
- **Skill:** Laravel/testing untuk endpoint baca; Inertia, Wayfinder, shadcn, dan skill tabel untuk halaman. Gunakan skill layout, styling, dan ikon hanya pada pekerjaan UI terkait.
- **Selesai jika:** kedua pintu masuk membaca data sama, konteks Modul benar, pencarian children tidak menggandakan baris paket, pagination stabil, detail dapat dibuka, dan status aktif menu benar. Pemeriksaan tipe/build lulus.
- **Commit:** `feat(archive): add archive listings and secondary navigation`

### Tahap 8 — Antarmuka restore dan konflik

- **Dependensi:** tahap 5 dan 7.
- **Pekerjaan:** hubungkan kelima mode restore, selection lintas halaman, checkbox header per halaman, pratinjau `useHttp`, perbandingan grup, rename/penggabungan, konfirmasi, dan hasil parsial. Tangani `archive_changed` dengan peninjauan ulang tanpa pengiriman otomatis.
- **Batas:** gunakan state selection dan komponen tabel existing; tidak menambah library state/table baru atau operasi lintas paket.
- **Skill:** Inertia, Wayfinder, shadcn, `table-state`, testing untuk interaksi berlogika; skill UX/styling dan goey-toast jika mengubah area terkait.
- **Selesai jika:** seluruh mode dapat digunakan dari layar, pilihan lintas halaman tetap benar, hanya pilihan yang dikirim, perubahan target membatalkan pratinjau lama, hasil parsial jelas, dan selection dibersihkan setelah sukses. Pemeriksaan tipe/build dan alur layar lulus.
- **Commit:** `feat(archive): add restore selection and conflict resolution flows`

### Tahap 9 — Antarmuka Delete Forever dan penyelesaian alur

- **Dependensi:** tahap 6–8.
- **Pekerjaan:** tambahkan aksi permanen di daftar/detail dan toolbar selection; hubungkan persiapan konfirmasi, ringkasan nama/jumlah, eksekusi, serta refresh/redirect setelah paket habis. Lengkapi feedback dan penanganan galat yang berbeda dari restore.
- **Batas:** tidak merilis aksi permanen tanpa konfirmasi yang terikat pada sasaran terbaru. Gunakan mekanisme token existing, bukan alur konfirmasi kedua dengan aturan berbeda.
- **Skill:** Inertia, Wayfinder, shadcn, testing; `table-state` hanya bila state selection berubah, goey-toast/UX/styling sesuai area yang disentuh.
- **Selesai jika:** paket, satu item, dan pilihan children dapat dihapus dari layar; perubahan data meminta konfirmasi ulang; tombol tidak dapat dikirim berulang saat processing; record aktif tetap utuh; redirect dan state pascaoperasi benar.
- **Commit:** `feat(archive): add permanent deletion confirmation flows`

### Tahap 10 — Kompatibilitas MySQL/PostgreSQL dan verifikasi akhir

- **Dependensi:** tahap 1–9.
- **Pekerjaan:** tambahkan/lengkapi pengujian integrasi lintas engine serta konkurensi pada database pengujian terisolasi. Verifikasi JSON, constraint/collation, fingerprint, restore versus purge, dan operasi Reference aktif. Jalankan regresi relevan serta pemeriksaan alur lengkap.
- **Batas:** jangan menjalankan refresh/migration pengujian pada database aplikasi. Jika salah satu engine pengujian belum tersedia, catat verifikasi yang belum berjalan; tahap belum dinyatakan lengkap.
- **Skill:** testing dan Laravel ketika menulis pengujian atau memperbaiki perilaku backend; skill frontend hanya jika ada masalah UI yang memerlukan perubahan.
- **Selesai jika:** pengujian SQLite relevan, integrasi MySQL/PostgreSQL, skenario konkurensi, tipe, build, Pint untuk PHP, serta alur layar memenuhi kriteria bagian 15. Catat bukti hasil dan keterbatasan aktual.
- **Commit:** `test(archive): verify cross-database behavior and concurrency`

Jika verifikasi menemukan perbaikan implementasi yang terpisah dari pengujian, buat commit `fix(archive): ...` yang menyebut perilaku konkretnya sebelum commit verifikasi. Jangan membuat commit kosong sekadar mencocokkan urutan; commit verifikasi mencakup pengujian serta checkpoint hasil yang nyata.

## 14. Checkpoint dan kelanjutan antar-context

- Mulai satu sesi dengan satu tahap aktif. Baca dokumen ini, checkpoint terakhir, `git status`, dan commit tahap sebelumnya sebelum mengerjakan perubahan.
- Baca aturan proyek serta skill yang relevan hanya untuk path/domain tahap aktif. Muat ulang fakta versi/API yang diperlukan bila belum tersedia dalam context.
- Jangan otomatis memuat semua source atau semua skill. Mulai dari file yang tercatat pada checkpoint dan perluas eksplorasi jika ada ketidakpastian konkret.
- Tahap selesai hanya jika hasil dan verifikasinya terpenuhi. Setelah itu, perbarui tracker dan checkpoint di dokumen ini, lalu gunakan commit message tahap tersebut jika pembuatan commit sudah diotorisasi.
- Jika context hampir habis di tengah tahap, simpan pekerjaan secara koheren dan tulis checkpoint **Dalam proses** dengan perubahan yang sudah dibuat, pemeriksaan yang sudah/belum berjalan, serta langkah berikutnya. Jangan menandai selesai atau membuat commit final tahap sebelum kriterianya terpenuhi.
- Sesi berikutnya melanjutkan tahap aktif, bukan mengulang tahap yang sudah selesai. Baca ulang kontrak final dari kode/commit, bukan mengandalkan ringkasan percakapan saja.
- Jangan memasukkan perubahan pengguna yang tidak terkait ke commit tahap.
- Seluruh tahap merupakan checkpoint pengembangan. Rilis fitur lengkap dilakukan setelah tahap 10 terpenuhi; checkpoint antara belum menjamin seluruh alur tersedia bagi pengguna.

### Tracker tahap

| Tahap                        | Status        | Commit message rencana                                                 |
| ---------------------------- | ------------- | ---------------------------------------------------------------------- |
| 1. Fondasi                   | Selesai       | `feat(archive): add shared archive storage and snapshot contracts`     |
| 2. Pengarsipan Reference     | Belum dimulai | `feat(reference): archive groups and items on deletion`                |
| 3. Pratinjau/token           | Belum dimulai | `feat(archive): add conflict previews and confirmation tokens`         |
| 4. Restore induk/paket       | Belum dimulai | `feat(archive): restore reference parents and packages`                |
| 5. Children/konflik          | Belum dimulai | `feat(archive): restore selected children and resolve group conflicts` |
| 6. Delete Forever backend    | Belum dimulai | `feat(archive): permanently delete archived packages and records`      |
| 7. Daftar/navigasi           | Belum dimulai | `feat(archive): add archive listings and secondary navigation`         |
| 8. UI restore                | Belum dimulai | `feat(archive): add restore selection and conflict resolution flows`   |
| 9. UI Delete Forever         | Belum dimulai | `feat(archive): add permanent deletion confirmation flows`             |
| 10. Verifikasi lintas engine | Belum dimulai | `test(archive): verify cross-database behavior and concurrency`        |

### Format checkpoint

Perbarui bagian berikut pada akhir sesi/tahap; tidak perlu membuat file handoff baru.

- **Tahap aktif:** tahap 1 selesai; berikutnya tahap 2.
- **Status:** fondasi siap untuk integrasi delete Reference.
- **Commit terakhir yang relevan:** basis `3bbeef2`; commit tahap ini menggunakan `feat(archive): add shared archive storage and snapshot contracts`.
- **File/kontrak penting:** `Archive`, `ArchiveRecord`, `SnapshotHandler`, `ReferenceSnapshot`, dan `ArchiveEntityRegistry`. Envelope record memakai `source_key` string, `source_uuid`, tiga kolom identitas induk, `schema_version`, dan payload kolom asli. Registry hanya mengenali `reference_group`/`reference_item`.
- **Pekerjaan selesai:** dua tabel bersama, model/factory, snapshot Reference versi 1 dan validasi envelope, registry eksplisit, serta migration preflight UUID unik.
- **Verifikasi:** `ArchiveStorageTest` 7 tes/22 assertions dan regresi Reference 91 tes/501 assertions lulus di SQLite; Pint dijalankan. Database aplikasi belum dimigrasikan.
- **Keputusan baru:** konteks Reference memakai `parent`, `target`, dan `parent_resolved`; identitas induk disalin sebagai metadata, bukan FK yang menghapus children. Tidak ada seeder data arsip fiktif: arsip produksi berasal dari operasi hapus.
- **Pekerjaan tersisa/kendala:** tahap 2–10; integrasi MySQL/PostgreSQL belum diverifikasi dan tetap menjadi bagian tahap 10.
- **Langkah berikutnya:** implementasikan layanan pengarsipan transaksional dan integrasikan endpoint delete Reference pada tahap 2.

## 15. Pengujian dan kriteria penerimaan

- Pengarsipan menyimpan data lengkap sebelum menghapus aktif; kegagalan membatalkan seluruh operasi.
- Hapus grup hanya mencakup item aktifnya; arsip item mandiri tidak digabungkan ulang.
- Dua pintu masuk menampilkan sumber data sama dengan konteks dan filter yang sesuai; menu sekunder memiliki penanda aktif yang benar.
- Pratinjau tidak mengubah data, dan token tidak dapat digunakan untuk pengguna, aksi, paket, atau pilihan lain.
- Kelima mode restore menjaga identitas, relasi, dan urutan sesuai aturan.
- Konflik nama, konten, identitas, penggabungan, rename, dan restore sebagian menghasilkan perilaku yang ditetapkan.
- Induk yang dipulihkan tanpa children, induk yang dihapus kembali, dan grup tujuan yang tidak tersedia ditangani tanpa kehilangan konteks.
- Uji selection lintas halaman, pilihan kosong, record paket lain, dan record di luar pilihan.
- Ketiga cakupan Delete Forever menjaga data aktif tetap utuh; uji child terakhir dan rollback.
- Perubahan arsip atau tujuan setelah pratinjau/konfirmasi menghasilkan `archive_changed` tanpa mutasi.
- Konfirmasi berulang serta restore bersamaan dengan Delete Forever tidak menimbulkan duplikasi atau kehilangan data.
- Snapshot versi yang tidak didukung tetap tersimpan dan ditahan dengan alasan yang jelas.
- Jalankan pengujian fitur dengan konfigurasi SQLite existing, lalu pengujian integrasi dan konkurensi pada MySQL serta PostgreSQL menggunakan database pengujian terisolasi.
- Verifikasi perilaku unik/collation yang relevan pada kedua engine, payload JSON, dan hasil fingerprint yang tidak bergantung format penyimpanan JSON.
- Jalankan pemeriksaan TypeScript, build frontend, Pint untuk perubahan PHP, dan pemeriksaan alur layar.

## 16. Penerapan dan batas kompatibilitas

- Gunakan migration baru; jangan mengubah migration yang sudah diterapkan bersama.
- Fitur tidak memulihkan data yang dihapus sebelum pengarsipan tersedia.
- Tabel aktif tetap menyimpan data aktif saja dan tidak memerlukan filter soft delete untuk fitur ini.
- Penambahan modul memerlukan penangan dan pengujian baru, tetapi tetap menggunakan dua tabel arsip bersama.
- Jangan menjatuhkan tabel arsip berisi data sebagai langkah rollback produksi; gunakan perbaikan maju.
- Tidak ada perubahan perilaku penghapusan modul selain Reference pada tahap ini.
