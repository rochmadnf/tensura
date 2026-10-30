# Rencana aktif: Backend dahulu, frontend setelah handoff

Tanggal keputusan: 30 Oktober 2026.

Status: implementasi backend dimulai atas instruksi pengguna. Progres dan bukti verifikasi ada pada bagian 8; frontend menunggu handoff backend.

## 1. Keputusan terbaru dan urutan baca

Pengguna membatalkan pengerjaan paralel Codex–Antigravity dan memilih backend selesai lebih dahulu. Antigravity belum perlu memulai frontend. Dokumen ini menggantikan aturan kerja paralel, baseline branch serentak, mock wajib, milestone paralel, dan pembagian file integrator terpisah pada kontrak sebelumnya.

Baca berurutan:

1. AGENTS.md, FEATURE_WORKFLOW.md, LARAVEL_PATTERNS.md, serta aturan/skill proyek terbaru.
2. Dokumen ini untuk urutan pelaksanaan dan handoff aktif.
3. [Kontrak teknis v1](letter-inputs-calendar-contract.md) untuk payload, enum, placeholder, endpoint, validasi, snapshot, dan perilaku error.
4. [Proposal lengkap](letter-questions-work-calendar-proposal.md) untuk konteks bisnis, keputusan percakapan, referensi visual, dan sumber kode lokal.

Jika ada konflik cara kerja, dokumen ini lebih baru dan berlaku. Kontrak teknis tetap digunakan; kebutuhan perubahan kontrak dicatat dan dikomunikasikan, bukan dilakukan diam-diam. Aturan proyek dan instruksi pengguna terbaru tetap didahulukan.

## 2. Tujuan dan konteks fitur

Pengembangan terdiri dari:

- Radio: satu pilihan per pertanyaan; nama/kode otomatis bisa diubah; token `${jenis_pelayanan:rawat_jalan}`; isi terpilih default `✔`, lainnya kosong.
- Angka: bulat/desimal per pertanyaan; token nilai `${jumlah}` dan `${jumlah:terbilang}`.
- Nominal: Rupiah bulat atau maksimal dua desimal; nilai dan terbilang termasuk Rupiah/Sen, presisi tanpa aritmetika float.
- Terbilang wajib memakai `riskihajar/terbilang`, referensi clone `/home/rochmadnf/Sites/node/terbilang`. Paket tidak dipakai untuk menentukan hari kerja.
- Kalender Kerja di Data Master: satu kalender instansi per tahun, pola awal Senin–Sabtu, pengecualian libur/kerja pengganti, dan konfirmasi tahunan.
- Kegiatan biasa boleh memiliki jam/lokasi tetapi tidak mengubah hitungan hari kerja. Libur/kerja pengganti berlaku sepanjang hari.
- Pengambilan libur Indonesia melalui port helper PHP dari `/home/rochmadnf/Sites/node/api-hari-libur`; cuti bersama opsional default tidak, tinjau sebelum terapkan, tanpa duplikat/overwrite manual otomatis.
- Rentang tanggal: pilihan basis hari kalender/hari kerja, hitung inklusif, rincian tanggal, hasil seperti `12 (Dua Belas) hari`, snapshot dan refresh eksplisit bila kalender berubah.

Frontend berikutnya memiliki empat tampilan Bulan/Minggu/Hari/Agenda. Referensi pengguna: `examples/application-example.html`, `examples/application.css`, dan `examples/dialog-application-example.html`. Jangan menyalin seluruh stylesheet/script situs asal. Detail fitur dan default teknis mengikuti kedua dokumen rujukan.

## 3. Branch dan lingkungan kerja

| Fase                                | Penanggung jawab                       | Branch                                                                                               |
| ----------------------------------- | -------------------------------------- | ---------------------------------------------------------------------------------------------------- |
| Backend beserta wiring PHP dan test | Codex                                  | `feat/letter-inputs-calendar-api`                                                                    |
| Frontend setelah backend diserahkan | Antigravity                            | `feat/letter-inputs-calendar-ui`                                                                     |
| Integrasi akhir                     | Codex/integrator setelah frontend siap | Lanjutkan branch fitur yang disepakati saat handoff; tidak membuat branch integrasi paralel sekarang |

Commit `3e824bb` menyimpan proposal dan kontrak awal. Itu referensi historis, bukan kewajiban memulai frontend dari sana. Backend memakai baseline repository terkini yang bersih/disepakati ketika implementasi dimulai; catat SHA-nya. Frontend nantinya dibuat dari **SHA handoff backend**, bukan baseline lama, agar migration, paket, endpoint, dan kontrak aktual sudah ikut terbawa.

Saat keputusan ini dibuat, checkout masih memiliki perubahan pengguna pada font, manifest, editor templat, dan file lain. Jangan memindahkan, membuang, melakukan reset/stash, atau memasukkan perubahan tersebut ke commit fitur tanpa otorisasi. Gunakan checkout terisolasi untuk backend; pembuatan worktree di luar writable root mengikuti izin lingkungan. Jangan menjalankan dua agent pada folder checkout yang sama.

Belum perlu membuat worktree/branch frontend sekarang. Database/storage test harus disposable dan terpisah dari aplikasi; worktree tidak otomatis mengisolasi PostgreSQL, storage, cache, atau port.

## 4. Lima tahap backend

### BE1 — Fondasi dan pertanyaan baru

- Periksa versi paket, skema, alur konfigurasi, dan test existing sebelum mengubahnya.
- Tambahkan migration additive, enum/config radio, Angka, Nominal, serta aturan validasi dan Resource sesuai kontrak.
- Pasang paket terbilang melalui Composer sesuai versi yang kompatibel; pengguna sudah meminta paket ini.
- Uji nama/kode, opsi, wajib/opsional, nol, negatif/presisi menurut kontrak, serta terbilang Indonesia.
- Jangan menawarkan jenis baru dalam daftar pilihan UI existing sebelum frontend mendukungnya. Pisahkan pengenalan jenis di domain dari penyajiannya pada opsi UI. Test backend dapat menggunakan jenis baru secara langsung sesuai akses/kontraknya.

### BE2 — Integrasi dokumen dan pertukaran templat

- Implementasikan token turunan, deteksi pertanyaan terpakai, resolver feedback form, snapshot, generate uji, dan rendering literal DOCX.
- Tangani copy versi dan penyesuaian draft tanpa mengganti identitas/nilai secara diam-diam.
- Ekspor/impor membawa config dan kode opsi; paket lama tetap dibaca sesuai kompatibilitas kontrak, tipe tidak didukung ditolak jelas.
- Pertahankan token dan perilaku tipe lama serta dokumen resmi/historis.
- Verifikasi body/header/footer/tabel sesuai dukungan renderer existing, token terpecah, konflik namespace, dan output terbilang.

### BE3 — Kalender Kerja dan kegiatan

- Implementasikan tahun, pola mingguan, pengecualian harian, kegiatan all-day/jam, konfirmasi kesiapan, revision/CAS, dan audit.
- Sediakan endpoint baca/tulis dan count sesuai kontrak. Selama fase backend, Codex boleh menangani route dan wiring PHP; pembatasan integrator terpisah pada rencana paralel tidak berlaku.
- Data baca mendukung query empat tampilan tanpa perlu membangun halaman React sekarang.
- Uji overlap pengecualian, update/hapus, rollback lintas tahun, konflik antarkoneksi, timezone, tahun kabisat, dan kegiatan biasa yang tidak mengubah work_revision.
- Route navigasi Inertia yang membutuhkan halaman baru tidak ditawarkan kepada pengguna sebelum frontend tersedia; JSON endpoint tetap dapat diuji mandiri.

### BE4 — Mengambil hari libur

- Implementasikan helper PHP, HTTP/cache/parser, fixture sumber, dan normalisasi rentang/tanggal.
- Implementasikan preview terikat aktor/revision, cuti bersama opsional, duplikat/conflict, expiry, serta apply idempoten dan atomik.
- Verifikasi struktur sumber saat implementasi; jangan menganggap selector referensi masih valid tanpa pemeriksaan.
- Kegagalan sumber tidak boleh menjadi sukses kosong, menghapus kalender, atau menimpa data manual.
- Test otomatis memakai HTTP fake/fixture; pemeriksaan sumber live dilaporkan terpisah.

### BE5 — Rentang tanggal dan konsistensi Surat

- Integrasikan pertanyaan rentang, basis kalender/hari kerja, durasi dan terbilang, serta snapshot dependency kalender.
- Tahan generate/pemeriksaan/penerbitan bila hasil rentang kerja stale atau kalender belum siap.
- Perluas refresh eksplisit sesuai kontrak; jangan menghitung ulang hasil yang sudah diperiksa ketika field lain disimpan.
- Lindungi pemeriksaan akhir dengan lock dan revision agar kalender tidak berubah di antara validasi dan commit penerbitan.
- Uji alur Surat, revisi/pemulihan, lintas tahun, nilai nol hari kerja, rollback, retry, dan histori yang tetap sama.

## 5. Verifikasi dan pelaporan setiap tahap

- Jalankan test backend terkecil yang membuktikan perubahan, termasuk failure mode; perluas regression jika perubahan menyentuh alur existing.
- Gunakan PostgreSQL disposable dan factory sesuai aturan proyek. Jangan menjalankan migration destructive pada database aplikasi.
- Jalankan Pint setelah perubahan PHP. Test backend tidak menunggu komponen React selesai; gunakan endpoint JSON/factory dan fixture kontrak. Jangan mengklaim skip sebagai bukti kelulusan.
- Simpan commit tahap dengan pesan bahasa Inggris. Laporkan SHA, kemampuan backend yang siap, endpoint/test yang lulus, keterbatasan, serta pekerjaan tersisa.
- Bedakan kemampuan backend dari fitur yang bisa dipakai melalui web. Selama fase ini belum ada menu, editor, atau halaman baru yang dijanjikan berfungsi.
- Pertahankan UI existing. Pengenalan tipe baru tidak boleh membuat editor/form lama menawarkan pilihan yang belum bisa dirender.
- Tahap backend yang benar-benar selesai dan terverifikasi mengikuti kenaikan patch proyek. Seluruh fitur baru dinyatakan selesai setelah frontend dan integrasi lulus, lalu minor naik/reset patch; tidak melakukan bump ganda untuk penyelesaian yang sama.
- Versi dihitung dari kondisi repository saat itu, bukan hardcode dari rekapan lama. Ikuti aturan release notes, GitHub auth/repository/tag/release terbaru. Jangan membuat PR tanpa persetujuan eksplisit.
- Penyimpanan rencana ini saja tidak menaikkan versi atau membuat release.

## 6. Syarat handoff ke Antigravity

Frontend baru dimulai setelah backend siap diserahkan dan pengguna melanjutkan ke fase frontend. Tidak ada penugasan aktif Antigravity selama BE1–BE5.

Handoff wajib mencatat:

| Isi               | Keterangan                                                                                                 |
| ----------------- | ---------------------------------------------------------------------------------------------------------- |
| Branch dan SHA    | SHA backend stabil yang menjadi parent branch frontend                                                     |
| Kontrak terbaru   | Contract ID dan perubahan dibanding v1, jika ada                                                           |
| Setup             | Versi PHP/Composer, migration yang dijalankan, konfigurasi wajib, cara menyiapkan test DB/storage          |
| Endpoint          | Route Wayfinder, method, auth/CSRF, request/response/error aktual; bedakan yang siap dari yang belum       |
| Fixture           | Contoh konfigurasi/isian radio, Angka/Nominal, kalender/kegiatan, impor libur, rentang, stale dan conflict |
| Kompatibilitas    | Perilaku config lama, paket pertukaran, snapshot, dan dokumen historis                                     |
| Test              | Perintah, jumlah/hasil, regresi, serta keterbatasan verifikasi                                             |
| Aktivasi frontend | Bagaimana jenis baru dan navigasi kalender dibuka setelah UI siap; jangan menyuruh frontend menebak        |
| Batas file        | File bersama dan perubahan pengguna yang harus dijaga                                                      |

Perbarui kontrak agar sesuai implementasi yang sudah diverifikasi sebelum handoff. Jika backend ternyata memerlukan perubahan keputusan produk, tanyakan dan catat hasilnya; jangan membiarkan frontend menemukan perbedaan melalui trial-and-error.

Frontend kemudian memakai endpoint nyata dan Wayfinder dari SHA handoff. Mock bukan kewajiban arsitektur untuk mengatasi backend yang belum selesai; mock/fake test tetap diperbolehkan bila memang membantu pengujian. Hindari menambahkan adapter produksi yang tidak diperlukan hanya karena kontrak lama membahas kerja paralel.

## 7. Instruksi siap diberikan ke agent

### Codex/backend

> Baca aturan proyek, docs/planning/letter-inputs-calendar-backend-first-plan.md, kontrak teknis, dan proposal. Implementasikan BE1–BE5 pada feat/letter-inputs-calendar-api dari baseline yang disepakati. Backend dikerjakan lebih dahulu; tidak ada frontend paralel. Jaga perubahan pengguna dan UI existing. Bangun/test endpoint nyata tanpa menunggu React. Laporkan dan commit per tahap, lalu sediakan SHA handoff beserta kontrak aktual. Jangan menambah komponen UI atau membuat PR otomatis.

### Antigravity/frontend — setelah handoff tersedia

> Backend dikerjakan lebih dahulu. Jangan mulai frontend dari commit proposal lama. Minta/gunakan SHA handoff backend yang sudah diberikan pengguna, lalu buat feat/letter-inputs-calendar-ui dari SHA itu. Baca aturan proyek, rencana backend-dahulu, kontrak yang sudah diperbarui, dan proposal. Inventaris komponen shadcn Base UI; minta persetujuan untuk kekurangannya. Gunakan endpoint nyata dan Wayfinder; jangan mengubah kontrak/backend sepihak. Pengujian visual dilakukan pengguna. Jika menemukan defect backend, laporkan request/response dan reproduksinya untuk perbaikan, bukan membuat backend tandingan.

## 8. Status implementasi backend

- [x] Baseline backend `66b303e`; branch `feat/letter-inputs-calendar-api`, checkout terisolasi `/tmp/tensura-calendar-api`.
- [x] BE1 selesai: migration/config/opsi radio relasional, validasi, audit/fingerprint, dan paket terbilang 2.1.1. Verifikasi 124 test / 954 assertion dan Pint lulus pada database disposable `tensura_test_calendar_api`. Jenis baru belum ditawarkan oleh daftar opsi UI. Tahap berikutnya dicatat terpisah.
- [x] BE2 selesai: token/resolver/snapshot, render literal, copy/penyesuaian dan pertukaran. Lima test integrasi pertanyaan / 49 assertion lulus pada checkpoint ini; jenis baru masih tersembunyi di opsi UI.
- [x] BE3 selesai: endpoint tahun/kegiatan/count, query empat tampilan, audit, revision/CAS dan mutasi lintas tahun. Lima test endpoint kalender lulus; konflik write antarkoneksi dibuktikan pada suite PostgreSQL khusus.
- [x] BE4 selesai: helper PHP, fixture/cache/parser, preview/apply terikat aktor/tahun/revision, konflik dan idempotensi. Empat test HTTP impor lulus; apply bersamaan dibuktikan pada suite PostgreSQL. Struktur sumber live 2026 diperiksa terpisah.
- [x] BE5 selesai: rentang inklusif, snapshot parsial/frozen, stale/missing, refresh terkonfirmasi, serta lock dan pemeriksaan ulang sebelum klaim nomor. Regresi akhir 610 test / 5.051 assertion lulus; 38 test khusus dilewati runner reguler. Lima test kalender dijalankan setelahnya dan lulus. Suite konkurensi Surat terpisah: 14 test / 209 assertion lulus.
- [x] Kontrak aktual dan [handoff backend](letter-inputs-calendar-backend-handoff.md) tersedia; SHA implementasi backend: `eea9a29e9f77c18cffff38ac2b8d9aa7fdc3685a`.
- [x] Backend BE1–BE5 digabung ke `main` melalui PR #10 (commit merge `164b6a8c536ea9a781d7aa1e3e586efbfed4a095`).
- [x] Fase frontend dimulai oleh Antigravity pada branch `feat/letter-inputs-calendar-ui` dari `main` terbaru (`164b6a8`).
- [x] Implementasi frontend FE1–FE6 selesai: tipe & schema, editor konfigurasi templat (radio, numeric, money, date_range), form draft Surat & generate uji dengan feedback resolver terbilang, halaman Kalender Kerja Inertia (4 tampilan: Bulan, Minggu, Hari, Agenda), alur ambil hari libur nasional preview & apply, integrasi status kalender surat, serta aktivasi enum jenis 1–8 pada `LetterTemplateVariableTypeEnum::options()`.
- [ ] Pengujian visual oleh pengguna melalui panduan `docs/tests/letter/tahap-11.md`.

## 10. Rencana Pelaksanaan Frontend (Antigravity)

1. **Tahap FE1 — Schema, Tipe, dan Kompatibilitas Token** [Selesai]:
    - Pembaruan schema TS: `letter-template-configuration.ts`, `letter-template-test.ts`, `letter.ts`, serta definisi tipe kalender `work-calendar.ts`.
    - Dukungan token bersuffix titik dua (`:`) pada `CopyPlaceholder` dan katalog token.
    - Penanganan format tampilan rentang tanggal (`{start_date, end_date}`) pada pembacaan draft Surat agar tidak muncul `[object Object]`.
2. **Tahap FE2 — Editor Pertanyaan Baru pada Templat Surat** [Selesai]:
    - Editor konfigurasi untuk Radio (opsi relasional, code unik, token per opsi, default `selected_text` ✔).
    - Editor konfigurasi Angka dan Nominal (mode integer/decimal, scale, izinkan negatif).
    - Editor konfigurasi Rentang Tanggal (basis hari kalender/hari kerja, format tanggal).
    - Penamaan variabel otomatis dan kode opsi yang dapat diubah manual tanpa tertimpa saat label diubah.
3. **Tahap FE3 — Form Draft Surat, Generate Uji, dan Feedback Terbilang** [Selesai]:
    - Integrasi input Radio, Angka, Nominal, dan Rentang pada form draft Surat dan form generate uji.
    - Feedback langsung melalui `lt.values.resolve` dengan debounce, pembatalan/pengabaian response lama, status menghitung, dan penanganan validasi tanpa menghilangkan input pengguna.
    - Format nominal Indonesia di form dikonversi eksplisit ke bentuk kanonis saat request.
4. **Tahap FE4 — Halaman Kalender Kerja (Bulan, Minggu, Hari, Agenda) dan Navigasi** [Selesai]:
    - Registrasi wrapper Inertia `wc.index` pada route/controller dan penambahan menu Kalender Kerja di sidebar Data Master.
    - Implementasi 4 tampilan kalender sesuai referensi visual token/komponen proyek.
    - Pengelolaan tahun kalender, pola mingguan kerja, dan konfirmasi kesiapan tahun.
    - Pengelolaan kegiatan biasa, libur, dan hari kerja pengganti beserta penanganan CAS/revision conflict.
5. **Tahap FE5 — Tinjauan Impor Libur Nasional Indonesia** [Selesai]:
    - Tombol pengambilan libur nasional dengan alur: request preview -> dialog tinjauan/pemilihan item -> apply terkonfirmasi.
    - Penandaan status item (new, duplicate, conflict) dan pencegahan duplikasi/penimpaan data manual.
6. **Tahap FE6 — Integrasi Kalender Kerja pada Alur Surat dan Verifikasi Akhir** [Selesai]:
    - Tampilan status `calendar_state` (current/stale/missing) pada draft surat beserta alasan penahanan penerbitan.
    - Alur refresh dependensi melalui preview dan apply existing (`ltr.masters.preview` / `ltr.masters.apply`).
    - Aktivasi jenis baru 1–8 pada `LetterTemplateVariableTypeEnum::options()`.
    - Checklist pengujian visual pada `docs/tests/letter/tahap-11.md`.
    - Pemeriksaan kualitas: `pnpm check:ui`, `pnpm types:check`, test PHP dan vitest, serta Pint dan format Vite Plus.

## 9. Checkpoint implementasi

| Tahap | Commit    | Versi | Kemampuan web pada tahap backend                                                                                                                 |
| ----- | --------- | ----- | ------------------------------------------------------------------------------------------------------------------------------------------------ |
| BE1   | `0cea613` | 0.6.1 | Halaman existing tetap; jenis baru belum ditawarkan                                                                                              |
| BE2   | `0a16afc` | 0.6.2 | Backend dokumen/resolver siap; editor baru belum tersedia                                                                                        |
| BE3   | `526f4ed` | 0.6.3 | Endpoint kalender siap; belum ada menu/halaman kalender                                                                                          |
| BE4   | `4b03821` | 0.6.4 | Endpoint preview/apply libur siap; tombol menunggu frontend                                                                                      |
| BE5   | `eea9a29` | 0.6.5 | Backend Surat siap mengunci snapshot kalender; tampilan state/field baru menunggu frontend                                                       |
| FE    | `HEAD`    | 0.7.0 | Frontend terintegrasi penuh: konfigurasi pertanyaan baru, form surat/uji, kalender kerja 4 tampilan, impor libur, dan verifikasi status kalender |

Semua pesan commit menggunakan bahasa Inggris. Pengujian visual disiapkan pada tahap 11 untuk pengguna. Perubahan pengguna pada checkout utama tetap dijaga.
