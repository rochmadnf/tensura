# Handoff backend pertanyaan Surat dan Kalender Kerja

Backend dikerjakan dahulu, mengikuti keputusan pengguna pada 30 Oktober 2026. Frontend belum dikerjakan. Dokumen ini dibaca bersama [rencana aktif](letter-inputs-calendar-backend-first-plan.md), [kontrak v1](letter-inputs-calendar-contract.md), dan [proposal](letter-questions-work-calendar-proposal.md).

## Baseline dan branch

- Baseline backend: `66b303e`.
- Branch backend: `feat/letter-inputs-calendar-api`.
- Checkout implementasi: `/tmp/tensura-calendar-api`. Checkout utama dan perubahan pengguna di sana dipertahankan.
- Versi setelah lima tahap backend: `0.6.5`. Fitur lengkap belum dinyatakan selesai; kenaikan minor menunggu frontend dan integrasi selesai.
- Parent frontend harus berasal dari **HEAD backend yang diserahkan**, bukan commit proposal lama. SHA final dicatat pada bagian hasil penyerahan di bawah.
- Branch frontend: `feat/letter-inputs-calendar-ui`. Backend tidak mengirim penugasan atau pesan ke Antigravity secara otomatis.

## Yang sudah tersedia

BE1 menambahkan enum/config, opsi radio relasional, validasi, audit/fingerprint, serta `riskihajar/terbilang` 2.1.1. BE2 menghubungkan token, isian, snapshot, DOCX, copy versi, penyesuaian draft, dan pertukaran templat. BE3 menyediakan tahun, pola mingguan, kegiatan, pengecualian, konfirmasi, audit, CAS dan hitungan hari. BE4 menyediakan helper libur Indonesia dan preview/apply impor. BE5 menghubungkan rentang tanggal dengan snapshot serta penguncian penerbitan Surat.

Tidak ada halaman React, menu kalender, komponen baru, atau perubahan tampilan dalam pekerjaan ini. Jenis 5–8 sengaja belum ditawarkan oleh `LetterTemplateVariableTypeEnum::options()`. Jangan hanya membuka daftar tersebut sebelum seluruh editor dan pengisiannya siap.

## Setup

Gunakan versi proyek terpasang: PHP 8.4, Laravel 13, PostgreSQL 18, Composer, dan pnpm sesuai `package.json`. Satu-satunya dependensi baru adalah paket terbilang yang sudah diminta pengguna. `config/terbilang.php` menetapkan bahasa Indonesia dan `use_intl=false`; pecahan diolah sebagai string agar tidak kehilangan presisi.

Pada checkout backend/frontend yang bersih:

```bash
composer install
php artisan migrate --no-interaction
```

Jalankan migration biasa pada database aplikasi yang dipilih, setelah memeriksa `.env`. Jangan memakai `migrate:fresh` pada database aplikasi. Dua migration additive baru:

- `2026_10_29_182126_add_question_input_configuration.php`.
- `2026_10_29_182726_create_work_calendar_tables.php`.

Aplikasi tetap memerlukan setup dokumen/queue/font yang sudah ada. Konfigurasi aplikasi menggunakan `APP_TIMEZONE`, `APP_LOCALE`, dan opsional `APP_VERSION`; override versi pada environment dapat menutupi default versi yang baru. Tidak ada `.env` atau kredensial yang ikut di-commit. Database aplikasi utama tidak dimigrasikan selama pengujian backend ini.

## Endpoint aktual

Semua route terdaftar melalui `routes/work-calendar.php`, memakai middleware `web` dan `auth`, session/CSRF existing. Kirim `Accept: application/json` dan token CSRF pada write. Identitas event/pertanyaan/versi yang dikirim ke klien adalah UUID. Year pada URL berupa angka tahun, bukan UUID record tahun.

| Route                 | Method | Controller/action                        |
| --------------------- | ------ | ---------------------------------------- |
| `lt.values.resolve`   | POST   | `LetterQuestionValueController::store`   |
| `wc.data`             | GET    | `WorkCalendarController::data`           |
| `wc.years.store`      | POST   | `WorkCalendarController::store`          |
| `wc.years.update`     | PATCH  | `WorkCalendarController::update`         |
| `wc.years.confirm`    | POST   | `WorkCalendarController::confirm`        |
| `wc.events.store`     | POST   | `WorkCalendarController::storeEvent`     |
| `wc.events.update`    | PUT    | `WorkCalendarController::updateEvent`    |
| `wc.events.destroy`   | DELETE | `WorkCalendarController::destroyEvent`   |
| `wc.count`            | POST   | `WorkCalendarController::count`          |
| `wc.holidays.preview` | POST   | `WorkCalendarHolidayController::preview` |
| `wc.holidays.apply`   | POST   | `WorkCalendarHolidayController::apply`   |

`wc.index` dan Inertia `work-calendar/index` **belum didaftarkan** karena halaman belum tersedia. Frontend/integrasi menambahkan wrapper tersebut dan navigasinya setelah komponen siap. Jangan menambahkan halaman placeholder produksi hanya agar route tersedia.

Gunakan Wayfinder dari route aktual saat integrasi. File generated tidak ditulis atau di-commit secara manual. Vite plugin existing menghasilkan route; `php artisan wayfinder:generate --with-form --no-interaction` dapat dipakai mengikuti workflow proyek.

Success JSON mengikuti `{data: ...}`. Create year/event menghasilkan 201; write lainnya 200. Error baru memakai `{message,code,errors}` dengan `errors` object. Domain: 422 aturan bisnis, 409 revisi berubah, 410 preview impor kedaluwarsa, 502 sumber libur gagal. Error umum diberi kode `unauthenticated`, `forbidden`, `not_found`, `session_expired`, `rate_limited`, atau `server_error` melalui konfigurasi exception yang dibatasi pada route JSON baru. Route Surat/templat existing mempertahankan redirect dan validation response existing.

Resolver dibatasi 60 request/menit, preview impor 10 request/menit. Frontend perlu debounce, membatalkan/mengabaikan response lama, serta menampilkan isian dan pesan galat tanpa menghilangkannya.

## Konfigurasi dan fixture

Shape lengkap ada pada kontrak. Contoh nyata yang dapat dibaca atau dijalankan:

| Kebutuhan                                                                                          | File test                                           |
| -------------------------------------------------------------------------------------------------- | --------------------------------------------------- |
| Payload konfigurasi radio, kepemilikan UUID, urutan opsi, presisi                                  | `tests/Feature/LetterQuestionConfigurationTest.php` |
| Resolver, nol/opsional, input salah, body/header/footer/tabel DOCX, token terpecah, copy dan impor | `tests/Feature/LetterQuestionRenderingTest.php`     |
| Tahun, pola, CAS, hari Minggu pengganti, nol hari kerja, lintas tahun, empat tampilan              | `tests/Feature/WorkCalendarTest.php`                |
| HTML sumber 12 bulan, rentang libur, cache, cuti bersama, duplikat/konflik, aktor/expiry           | `tests/Feature/WorkCalendarHolidayTest.php`         |
| Rentang, snapshot parsial, dependency stale/missing, dua hasil dengan revisi tahun berbeda         | `tests/Feature/LetterDateRangeTest.php`             |
| Refresh terkonfirmasi, terbit/revisi/batal/pemulihan, histori tetap                                | `tests/Feature/LetterLifecycleTest.php`             |
| Kalender melawan penerbitan, pemeriksaan setelah render, dua write/apply bersamaan                 | `tests/Feature/LetterConcurrencyTest.php`           |

Perhatikan nama existing: konfigurasi editor dan Resource generate uji menggunakan `is_required`/`is_multiple`, sedangkan definisi form Surat memakai field existing `required`/`multiple`. `radio`, `numeric`, dan `date_range` ditambahkan pada Resource terkait; yang tidak berlaku null. Opsi radio ada di `radio.options`, dengan `id`, `code`, dan `label`; jawaban mengirim **code**, bukan UUID.

Contoh isian ke `lt.values.resolve`, generate uji, atau draft memakai envelope `values` yang sama:

```json
{
    "template_version": "UUID-versi",
    "values": {
        "UUID-radio": "rawat_inap",
        "UUID-angka": "12.5",
        "UUID-nominal": "1250000.50",
        "UUID-rentang": {
            "start_date": "2026-11-01",
            "end_date": "2026-11-07"
        }
    }
}
```

UUID contoh wajib diganti dengan record versi tersebut. Resolver boleh menerima subset; UUID asing ditolak. Jawaban normal decimal memakai titik tanpa pemisah ribuan, bukan angka JSON. Nominal decimal dinormalisasi scale 2. Nol sah; null opsional menghasilkan token kosong. Maksimal 12 digit bulat; angka normal scale 1–6, nominal integer/scale 2. Terbilang normal mengeja digit pecahan; nominal memisahkan Rupiah dan Sen.

Radio memakai `${nama:kode}`, satu jawaban dan satu tanda terpilih. Semua token opsi wajib ada saat validasi DOCX; token dasar `${nama}` tidak sah untuk radio. Nama dan code bisa diubah melalui konfigurasi, dengan invalidasi bukti templat existing. Pembuatan nama/code otomatis pada editor menjadi tugas frontend; backend memvalidasi dan menyimpan nama/code yang dikirim.

## Kalender dan impor

Tahun baru default Senin–Sabtu dan berstatus draft. Konfirmasi tidak wajib untuk membuat kegiatan, tetapi wajib untuk perhitungan hari kerja. Activity tidak mengubah `work_revision` atau readiness. Holiday/working_day harus all-day, dengan satu pengecualian per tanggal; overlap ditolak. Maksimal 366 tanggal inklusif. Nilai waktu lokal `HH:mm` mengikuti timezone server.

`wc.data` menyediakan grid 42 hari mulai Senin untuk month, 7 hari untuk week, 1 hari untuk day, dan seluruh bulan untuk agenda. GET tidak membuat tahun. Hari tanpa tahun memiliki `is_working:null`. Filter hanya menyaring event. Map `calendar_revisions` setiap event mencakup seluruh tahun event, termasuk tahun di luar period tampilan. Untuk memindahkan event ke tahun baru, ambil revisi tahun tujuan sebelum write; update/delete memerlukan union tahun lama/baru.

`FetchIndonesianHolidays::fetch($year)` adalah helper PHP yang diminta pengguna. Sumber tetap `https://tanggalans.com/kalender-{year}/` dengan garis miring penutup untuk menghindari redirect. DOM PHP digunakan tanpa dependensi parser tambahan. Ada cache 24 jam, timeout 3/10 detik, satu retry, batas HTML 2 MB, dan pemeriksaan struktur 12 bulan. Lisensi MIT kode referensi dipertahankan dalam class.

Tombol frontend harus memulai **preview**, kemudian dialog tinjauan dan **apply**. Preview berlaku 15 menit, terikat aktor/tahun/revision, tidak mengubah readiness. Cuti bersama opsional default false. Apply hanya menerima ID item `new` yang dipilih, tidak menerima tanggal/nama dari browser. Token sukses yang diulang mengembalikan hasil yang sama tanpa duplikasi. Data manual dipertahankan; sumber gagal tidak menjadi sukses kosong.

Struktur halaman sumber live tahun 2026 sudah diperiksa terpisah saat implementasi. Pengujian otomatis menggunakan fixture dan HTTP fake; tidak bergantung pada ketersediaan situs eksternal. Daftar hasil impor tetap harus ditinjau admin sebelum diterapkan.

## Rentang, snapshot, dan penerbitan

Token rentang: `mulai`, `akhir`, `jumlah_hari`, `terbilang`, dan `durasi`. Format tanggal memakai formatter aplikasi, termasuk nama bulan Indonesia. Hitungan inklusif dan basis dipilih pada konfigurasi pertanyaan. Basis kalender tidak memerlukan record tahun; basis hari kerja memerlukan semua tahun siap. Nol hari kerja ditolak.

Snapshot menyimpan `question_results[UUID] = {input, config, timezone, result}`. `result` membawa canonical value, display, words, tokens, dan dependency `calendar_state`. Nilai renderer akhir tetap map string literal. Field lain yang disimpan tidak menghitung ulang range lama. Snapshot parsial mempertahankan hasil sah pertanyaan lain ketika kalender untuk suatu range belum tersedia.

Detail draft memiliki `calendar_state` null/current/stale/missing, `question_results`, dan alasan penahanan. `missing` berarti perhitungan belum tersedia; tahun yang perlu disiapkan ada di `changed_years`. Jika dua pertanyaan menyimpan revision kerja berbeda untuk tahun sama, daftar dependency boleh berisi tahun sama dengan revision berbeda; satu hasil usang tetap membuat seluruh draft stale.

Refresh menggunakan `ltr.masters.preview` dan `ltr.masters.apply` existing, bukan endpoint baru. Preview menampilkan perubahan token/durasi dan menghasilkan token konfirmasi; apply memeriksa revision serta seluruh state kembali. Setelah kalender dikonfirmasi, admin harus meninjau dan menerapkan refresh lalu generate/periksa ulang. Tanpa hasil awal, menyimpan raw input diperbolehkan tetapi penerbitan ditahan.

Generate, pemeriksaan, dan penerbitan memverifikasi snapshot kalender. Urutan lock: templat, tahun kalender terurut, surat, draft, lalu aturan penomoran/operasi existing. Kandidat draft diperiksa lagi setelah menunggu lock. Pemeriksaan diulang sesudah render sebelum klaim nomor. Mutasi kalender tidak mengunci surat, dan tidak ada lock yang ditahan selama HTTP atau render. Revisi/pemulihan mengikuti tanggal serta identitas resmi existing. DOCX dan snapshot versi historis tidak dihitung ulang.

## Kompatibilitas dan pekerjaan frontend

Config lama diperlakukan null. Nilai enum 1–4, token lama, fingerprint konfigurasi lama, dan dokumen historis dipertahankan. Copy versi memberi UUID pertanyaan/opsi baru dengan code yang sama. Penyesuaian mempertahankan code radio yang masih ada dan angka yang tetap valid; basis range berubah memerlukan isian ulang.

Format pertukaran `tensura-exchange` tetap versi 1: pembaca lama sudah menolak key/enum tidak dikenal dengan validasi ketat. Pembaca baru menerima paket lama. Paket hanya membawa konfigurasi dan opsi, tidak membawa kalender/isian/hasil hitung. Round-trip encrypted package serta UUID opsi baru diuji.

Urutan pekerjaan frontend:

1. Baca AGENTS.md dan skill yang berlaku. Inventaris komponen shadcn Base UI; ajukan daftar kebutuhan yang belum ada kepada pengguna. Pengguna meminta selalu memakai komponen tersebut.
2. Buat branch UI dari SHA handoff. Jaga perubahan pengguna pada editor templat/font di checkout utama; bandingkan sebelum integrasi.
3. Perluas schema/tipe, editor radio/angka/nominal/rentang, field isian Surat serta generate uji, preview terbilang, dan feedback resolver.
4. Buat layar Kalender Kerja empat tampilan dan dialog mengikuti referensi `examples/application-example.html`, `examples/application.css`, serta `examples/dialog-application-example.html` dalam token/komponen proyek.
5. Tambahkan wrapper Inertia `wc.index`, menu Data Master, feedback CAS/readiness, tinjauan impor, state stale/missing, dan alur refresh dependensi.
6. Aktifkan jenis 5–8 pada daftar pilihan setelah semua konsumen siap. Gunakan endpoint nyata dan Wayfinder; jangan membuat backend atau fallback mock produksi tandingan.
7. Jalankan pemeriksaan frontend proyek dan integrasi. Pengujian visual dilakukan pengguna; simpan daftar uji yang relevan sesuai workflow fitur. Jangan mencentang uji visual yang belum dilakukan pengguna.

## Verifikasi dan hasil penyerahan

Database disposable reguler: `tensura_test_calendar_api`. Suite konkurensi memakai database terpisah `tensura_test_letters_lifecycle_calendar_api`, dengan `pcntl` dan `posix` pada Linux.

```bash
TEST_DB_DATABASE=tensura_test_calendar_api php artisan test --compact --filter='Letter|WorkCalendar'
vendor/bin/pint --dirty --format agent
```

Untuk suite konkurensi, ikuti bootstrap `tests/letter-bootstrap.php`: set `LETTER_TEST_DATABASE=tensura_test_letters_lifecycle_calendar_api` dan `LETTER_TEST_HOST`, `LETTER_TEST_PORT`, `LETTER_TEST_USERNAME`, `LETTER_TEST_PASSWORD` sesuai database disposable, kemudian:

```bash
vendor/bin/phpunit -c phpunit.letters.xml
```

Suite tersebut menjalankan migration fresh pada database test yang namanya dibatasi bootstrap. Jangan memakai kredensial/database aplikasi. Suite reguler melewati test konkurensi khusus; skip tersebut tidak dihitung sebagai lulus. Suite lifecycle konkurensi dijalankan terpisah, seluruh 14 test/209 assertion lulus. Suite konkurensi lain existing yang dilewati runner reguler tidak diklaim telah diverifikasi ulang.

Regresi akhir: 610 test / 5.051 assertion lulus, dengan 38 test khusus dilewati. Setelah penambahan test tampilan/filter, lima test kalender / 75 assertion dijalankan dan lulus. Verifikasi akhir rentang dan siklus Surat: 14 test / 250 assertion lulus. SHA handoff dicatat setelah commit akhir. Pint, format dokumen, route list, serta diff whitespace juga diperiksa. Tidak ada pengujian visual/browser atau Windows pada pekerjaan backend ini.

Composer audit menemukan advisory existing pada `symfony/mime`; paket itu tidak ditambahkan atau diperbarui dalam fitur ini. Dependensi di luar scope tidak diubah.
