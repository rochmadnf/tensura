# Kontrak v1: Pertanyaan Surat dan Kalender Kerja

Tanggal: 30 Oktober 2026. Contract ID: `letter-inputs-calendar/v1`.

**Perubahan cara kerja:** pengguna memilih **backend dahulu, frontend setelah handoff**. Ikuti [rencana aktif backend-dahulu](letter-inputs-calendar-backend-first-plan.md). Bagian pembagian paralel, tiga branch serentak, gateway mock wajib, kepemilikan integrator PHP terpisah, dan milestone paralel di dokumen ini menjadi riwayat rancangan, bukan instruksi pelaksanaan aktif. Payload, endpoint, token, validasi, dan aturan bisnis tetap menjadi kontrak teknis sampai diperbarui secara tercatat. Antigravity memulai dari SHA handoff backend, bukan commit kontrak awal.

Dokumen ini menetapkan kontrak kerja backend/frontend berdasarkan [proposal](letter-questions-work-calendar-proposal.md). Ini hasil permintaan pengguna untuk membuat kesepakatan agar agent dapat bekerja paralel; bukan laporan implementasi. Default teknis yang sebelumnya terbuka ditetapkan di sini dan dapat diubah hanya melalui prosedur perubahan kontrak pada bagian terakhir. Ketentuan ini tidak menggantikan kewajiban meminta persetujuan komponen/dependensi yang belum disetujui atau persetujuan PR.

## Catatan implementasi backend, 30 Oktober 2026

Contract ID tetap `letter-inputs-calendar/v1`; tidak ada perubahan keputusan bisnis. Lihat [handoff aktual](letter-inputs-calendar-backend-handoff.md) untuk setup, fixture, commit, dan hasil test.

- Semua endpoint JSON pada bagian 6 sudah tersedia. `wc.index` dan wrapper Inertia belum didaftarkan sampai halaman frontend siap.
- Error umum endpoint baru menambahkan kode `unauthenticated`, `forbidden`, `not_found`, `session_expired`, `rate_limited`, dan `server_error`. Error domain tetap mengikuti kontrak. Route Surat/templat existing mempertahankan pola response existing.
- Resource editor dan generate uji memakai `is_required`/`is_multiple`; definisi form Surat memakai nama existing `required`/`multiple`. Konfigurasi `radio`, `numeric`, `date_range` tersedia pada masing-masing Resource tersebut.
- Snapshot memakai `question_results[UUID]={input,config,timezone,result}`. Detail state `missing` memiliki `years:[]` dan tahun yang perlu disiapkan pada `changed_years`. Hasil sah pertanyaan lain tetap disimpan dalam snapshot parsial.
- Daftar dependency dapat memuat tahun sama dengan work_revision berbeda bila dua pertanyaan dibekukan pada waktu berbeda. Satu dependency usang membuat state draft stale; jangan hanya mengambil revision terakhir tahun itu.
- Map revisi pada event hasil GET mencakup seluruh tahun event, termasuk tahun di luar period tampilan. Untuk tahun tujuan baru, ambil revision aktual sebelum mengirim mutasi.
- Format paket pertukaran tetap versi 1 karena parser lama sudah menolak key/enum baru secara ketat. Pembaca baru menerima paket lama; impor tidak menyertakan kalender atau hasil perhitungan.
- URL sumber helper memakai `https://tanggalans.com/kalender-{year}/`; garis miring penutup mencegah redirect. Pemeriksaan live struktur sumber dilaporkan terpisah dari test fixture.
- Jenis baru belum dibuka pada `LetterTemplateVariableTypeEnum::options()` sampai editor, form Surat, dan generate uji frontend siap. Backend tidak menunggu frontend untuk verifikasi endpoint.

## 1. Branch, baseline, dan penanggung jawab

| Peran                                           | Agent                | Branch                            |
| ----------------------------------------------- | -------------------- | --------------------------------- |
| Kontrak, integrasi, wiring, verifikasi akhir    | Codex/integrator     | `feat/letter-inputs-calendar`     |
| Backend, migrasi, snapshot, DOCX, impor libur   | Codex/backend        | `feat/letter-inputs-calendar-api` |
| Frontend konfigurasi/form/kalender dan test DOM | Antigravity/frontend | `feat/letter-inputs-calendar-ui`  |

Ketika kontrak dibuat, HEAD berada pada `main`, commit `1b164904e24de889f0d15367dc74b8c0ceaf2b44`, dan working tree berisi perubahan pekerjaan lain. Angka ini adalah titik observasi, bukan instruksi membuang perubahan atau memakai versi aplikasi lama.

**Baseline bersama:** integrator menyimpan hanya kedua dokumen planning ini pada satu commit kontrak, setelah memastikan isinya tidak mencakup staging pekerjaan orang lain. Jika ada pekerjaan lain yang harus menjadi dasar, integrator terlebih dahulu menyepakati commit finalnya dengan pemilik. Catat SHA commit kontrak pada pesan handoff. Buat ketiga branch dari SHA baseline yang sama; jangan memakai HEAD yang berubah-ubah. Dokumen ini tidak membuat branch/worktree secara otomatis.

Gunakan worktree berbeda. Contoh dari root repository, setelah `<BASELINE_SHA>` diketahui:

```sh
git worktree add -b feat/letter-inputs-calendar ../tensura-calendar-integration <BASELINE_SHA>
git worktree add -b feat/letter-inputs-calendar-api ../tensura-calendar-api <BASELINE_SHA>
git worktree add -b feat/letter-inputs-calendar-ui ../tensura-calendar-ui <BASELINE_SHA>
```

Jangan menjalankan contoh ini sebelum baseline disimpan. Membuat worktree di luar workspace yang diizinkan dapat memerlukan persetujuan lingkungan; jangan mengubah batas akses sendiri. Agent tidak berpindah branch pada checkout yang sedang digunakan agent lain.

### Kepemilikan file

| Pemilik    | Boleh diubah                                                                                                                                                                                     | Dilarang tanpa handoff                                                                      |
| ---------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------- |
| Backend    | `app/**`, migration/factory terkait, config baru kalender/terbilang, `tests/Feature/**` dan `tests/Unit/**` terkait, Composer untuk paket terbilang, file route baru `routes/work-calendar.php`  | Semua `resources/js/**`, routes bersama, bootstrap/provider existing bersama, versi/release |
| Frontend   | Schema/tipe TS dan adapter transport baru fitur, tampilan baru kalender, editor pertanyaan baru, field baru form, test frontend fitur                                                            | PHP, migration, Composer, routes, file generated Wayfinder, versi/release                   |
| Integrator | `routes/web.php`, `bootstrap/app.php`/provider wiring yang diperlukan, sidebar, root editor templat, root form Surat/generate uji, shared root type bila perlu, version/release, dokumen kontrak | Perubahan bisnis sepihak yang menyimpang dari kontrak                                       |

Root `resources/js/pages/letter-templates/edit.tsx` sedang memiliki perubahan pekerjaan lain pada observasi awal. Frontend menulis editor/field terpisah; integrator memasangnya sesudah perubahan pemilik lama tersimpan. Backend boleh memperluas Resource PHP; frontend memiliki schema TS-nya. Keduanya harus mengikuti shape di bawah.

Agent boleh menambah test dan file implementasi di area yang dimilikinya sesuai struktur existing. Perubahan komponen shared existing harus diminta ke integrator, bukan dilakukan di kedua branch. Frontend boleh menyusun primitive existing dalam editor fitur; primitive/shared component yang belum tersedia tetap memerlukan persetujuan pengguna.

## 2. Aturan tidak saling menunggu

1. Dokumen ini menjadi sumber kontrak payload, bukan hasil implementasi salah satu pihak.
2. Frontend langsung membuat tipe, fixture, dan **adapter mock yang bisa menulis state di memori**; tidak menunggu migration/controller/API berjalan. Editor pertanyaan dan layar kalender menerima gateway lewat props. Test DOM memasang mock gateway.
3. Backend langsung mengimplementasikan dan menguji endpoint/module dengan data factory; tidak menunggu UI. Backend menaruh definisi route baru pada `routes/work-calendar.php` (auth, nama/binding sesuai kontrak). Test backend memuat file itu melalui group middleware web jika belum terdaftar, lalu menguji controller nyata dengan permintaan JSON. Integrator menyertakan file tersebut dari routes/web.php tanpa menyalin definisinya. Test GET Inertia/page dilakukan integrator setelah wrapper tersedia; file halaman bukan prasyarat kelulusan backend.
4. Integrator menyiapkan route/wiring produksi serta adapter HTTP secara terpisah. Frontend tidak perlu membuat atau memalsukan file generated `@/routes` agar build lulus.
5. Root page Inertia hanya wrapper integrator. Frontend mengirim `CalendarScreen`, editor konfigurasi, dan field renderer yang dapat dirender mandiri oleh test. Preview web bersama tersedia setelah wrapper dipasang, tetapi hal itu bukan prasyarat pengerjaan/kelulusan unit frontend.
6. Saat satu endpoint belum tersedia, frontend tetap bekerja pada mock. Saat UI belum tersedia, backend tetap menguji respons. Jangan menggunakan timeout, polling, atau endpoint placeholder produksi untuk menyembunyikan fitur yang belum selesai.
7. Mock hanya dipakai test/dev preview yang ditandai jelas. Adapter mock tidak boleh diimpor atau menjadi fallback saat API produksi gagal.
8. Integrator melakukan merge/cherry-pick milestone ke branch integrasi, bukan saling merge branch BE/FE. Tidak perlu menunggu seluruh fitur selesai untuk mengintegrasikan milestone.

### Gateway yang dipakai frontend

Frontend memiliki tipe dan mock pada area fitur baru; integrator memiliki adapter HTTP produksi. Nama kontrak TS: `CalendarGateway` dan `QuestionGateway`. Method mengembalikan Promise payload respons (tanpa envelope `data`) atau melempar `{status, code, message, errors}`. Komponen tidak memanggil URL sendiri.

```ts
interface CalendarGateway {
    load(query: CalendarQuery): Promise<CalendarPageData>;
    prepareYear(input: PrepareYearInput): Promise<CalendarYear>;
    updateYear(year: number, input: UpdateYearInput): Promise<CalendarYear>;
    confirmYear(year: number, input: RevisionInput): Promise<CalendarYear>;
    createEvent(input: CreateEventInput): Promise<CalendarEvent>;
    updateEvent(id: string, input: UpdateEventInput): Promise<CalendarEvent>;
    deleteEvent(id: string, input: DeleteEventInput): Promise<DeletedEvent>;
    previewHolidays(year: number, input: HolidayPreviewInput): Promise<HolidayPreview>;
    applyHolidays(year: number, input: HolidayApplyInput): Promise<HolidayApplyResult>;
    countDays(input: CountDaysInput): Promise<DayCount>;
}

interface QuestionGateway {
    resolve(input: ResolveQuestionsInput): Promise<ResolvedQuestions>;
}
```

Nama tipe di atas diimplementasikan dari tabel/shape pada dokumen ini oleh frontend, bukan menunggu backend menghasilkan TS. Transport produksi menggunakan Wayfinder dan HTTP client Inertia versi terpasang melalui integrator; jangan hardcode URL di JSX. Adapter mock mencakup state kosong, siap, belum siap, validasi, konflik revisi, impor gagal, dan sukses. Nama method/field tidak boleh diubah hanya di satu adapter.

## 3. Representasi bersama dan error

- Semua API di bawah memakai auth/session/CSRF web existing; tidak membuat API key atau sistem role baru. Semua pengguna login mengikuti akses bersama existing.
- Identitas publik `id` adalah UUID string. FK numerik tidak dikirim. Angka revisi integer positif mulai `1`. Semua timestamp audit ISO 8601 dengan offset/UTC, tanggal `YYYY-MM-DD`, waktu lokal `HH:mm`.
- `contract_version: 1` berada pada `CalendarPageData` dan hasil resolve; tidak disamakan dengan versi aplikasi atau versi snapshot renderer existing.
- Nilai uang/angka desimal dikirim sebagai **string**, bukan JSON number. String kosong UI dinormalisasi menjadi `null` sebelum request.
- Data baca normal JSON memakai `{ "data": ... }`. Koleksi bukan paginasi memakai `data` array. GET halaman kalender memakai Inertia, prop `calendarData` berisi `CalendarPageData`; shared `app`, `auth`, dan `page` tetap existing.
- Operasi JSON memakai 200; create year/event memakai 201. Hapus mengembalikan 200 dengan data `{id, calendar_revisions}`. Tidak memakai 204 agar revision map tersedia.
- Error JSON: `{ "message": "Pesan Indonesia", "code": "stable_code", "errors": { "field.path": ["Pesan"] } }`. `errors` selalu object, boleh kosong.
- 422 untuk input/aturan bisnis; 409 untuk revision/token state berubah; 410 untuk preview impor kedaluwarsa; 502 untuk sumber libur gagal; 429 throttle; 401 JSON untuk sesi tidak aktif. Navigasi halaman tamu tetap redirect login sesuai proyek. 404 untuk UUID/year tidak ada atau tidak sesuai induk.
- Kode domain: `validation_failed`, `revision_conflict`, `calendar_year_missing`, `calendar_not_ready`, `calendar_changed`, `calendar_exception_conflict`, `zero_working_days`, `holiday_source_failed`, `holiday_source_changed`, `holiday_preview_expired`, `holiday_preview_conflict`.
- Komponen mempertahankan isian saat gagal. Galat field melalui `FieldError`; toast Galat/Berhasil mengikuti proyek. 409 menawarkan muat ulang state tanpa menimpa input otomatis.

## 4. Kontrak pertanyaan baru

### Enum dan konfigurasi

Pertahankan angka enum existing 1–4. Tambahkan `RADIO = 5`, `NUMBER = 6`, `MONEY = 7`, `DATE_RANGE = 8`. Label Indonesia: Pilihan tunggal, Angka, Nominal, Rentang tanggal.

Pertahankan field konfigurasi existing. Tambahkan tiga field pada setiap variabel, baik Resource maupun payload simpan `item`: `radio`, `numeric`, `date_range`. Field yang tidak berlaku adalah `null`; payload lama yang tidak memiliki ketiganya dianggap `null`. Jenis baru wajib memiliki config yang sesuai. Ini tidak mengubah envelope request existing `{item: variable}` atau bulk `{variables, signer_slots}`.

```json
{
    "id": null,
    "name": "jenis_pelayanan",
    "label": "Jenis pelayanan",
    "type": 5,
    "is_required": true,
    "option_source": null,
    "reference_group": null,
    "is_multiple": false,
    "default_text": null,
    "employee_attributes": [],
    "columns": [],
    "radio": {
        "selected_text": "✔",
        "options": [
            { "id": null, "code": "rawat_jalan", "label": "Rawat jalan" },
            { "id": null, "code": "rawat_inap", "label": "Rawat inap" }
        ]
    },
    "numeric": null,
    "date_range": null
}
```

Resource menghasilkan UUID pada opsi tersimpan. Urutan array adalah urutan tampil; tidak menerima `sort_order` dari frontend. Kode opsi unik dalam pertanyaan. Nama variabel unik dalam versi. Regex nama/kode `[a-z][a-z0-9_]*`; nama, kode, dan token lengkap tunduk batas token existing 100 karakter. Label 1–100 karakter; radio 2–100 opsi; `selected_text` 1–100 karakter tanpa control character. Tidak menerima HTML sebagai instruksi render; selalu teks literal.

Konfigurasi angka: `numeric: {"mode":"integer","scale":0,"allow_negative":false}` atau `{"mode":"decimal","scale":2,"allow_negative":false}`. Angka normal mendukung scale 1–6. Nominal decimal selalu scale 2, integer scale 0. Maksimal 12 digit bagian bulat, tanpa eksponen; nilai lebih presisi ditolak, bukan dibulatkan diam-diam. Negatif default ditolak; admin boleh mengaktifkan `allow_negative`. Nol sah dan menghasilkan `Nol`, tidak dianggap kosong.

Konfigurasi rentang: `date_range: {"basis":"working_days","date_format":"d-m-Y"}`; basis lain `calendar_days`. `date_format` mengikuti formatter/validasi tanggal existing. Semua jenis baru bersifat tunggal, tanpa columns/employee_attributes/reference group/default_text. Wajib/opsional memakai `is_required` existing.

### Isian Surat dan generate uji

Envelope existing `values` tetap map UUID pertanyaan ke nilai. Gunakan bentuk yang sama pada generate uji dan draft Surat:

| Jenis           | Nilai request                                                   | Normalisasi                                                                                                   |
| --------------- | --------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------- |
| Radio           | kode opsi string, misalnya `rawat_inap`, atau null              | Kode, bukan UUID opsi/label; stabil saat copy versi                                                           |
| Angka           | `"12.5"`, `"12"`, atau null                                     | Desimal memakai titik tanpa pemisah ribuan; normalisasi nol awal dan nol pecahan berlebih yang tidak bermakna |
| Nominal decimal | `"1250000.50"` atau null                                        | Simpan string scale 2; frontend mengubah input Indonesia secara eksplisit                                     |
| Rentang         | `{"start_date":"2026-11-02","end_date":"2026-11-14"}` atau null | Kedua tanggal harus lengkap; tanggal akhir tidak sebelum awal                                                 |

Opsional kosong menjadi null; semua tokennya menjadi string kosong, bukan `Nol`/`0 hari`. Wajib kosong ditolak ketika aturan kesiapan/generate/penerbitan existing mensyaratkan lengkap. Jangan memperketat penyimpanan draft belum lengkap di luar kontrak existing. Kesalahan berada pada `values.<uuid>` atau `values.<uuid>.start_date/end_date`.

### Token hasil

| Jenis         | Token                                                                                          |
| ------------- | ---------------------------------------------------------------------------------------------- |
| Radio         | `${nama:kode_opsi}` untuk setiap opsi; `${nama}` tidak didukung                                |
| Angka/Nominal | `${nama}` dan `${nama:terbilang}`                                                              |
| Rentang       | `${nama:mulai}`, `${nama:akhir}`, `${nama:jumlah_hari}`, `${nama:terbilang}`, `${nama:durasi}` |

Rentang `durasi` menghasilkan `12 (Dua Belas) hari`; `terbilang` hanya `Dua Belas`; `jumlah_hari` hanya `12`. Angka normal memakai tampilan Indonesia tanpa pemisah ribuan, misalnya `1250,5`; nominal memakai `Rp1.250.000,50` pada mode decimal dan `Rp1.250.000` pada integer. Terbilang nominal menyebut Rupiah, serta `dan ... Sen` hanya jika pecahan tidak nol. Angka negatif diawali `Minus`. Kapitalisasi tiap kata seperti `Dua Belas`.

Paket terbilang dipakai pada bagian integer; pecahan Angka normal dieja digit demi digit setelah `Koma` agar presisi tidak hilang. Nominal memisahkan integer Rupiah dan integer Sen. Hindari menyerahkan decimal besar ke float internal paket. `riskihajar/terbilang` adalah satu-satunya penambahan dependensi yang sudah diminta pengguna.

Token baru boleh tersebar pada body/header/footer/tabel sesuai dukungan renderer existing. Pertanyaan dianggap dipakai jika salah satu token turunannya ada di DOCX. Radio memerlukan semua token opsi tersedia paling sedikit sekali ketika validasi sumber; token Angka/Nominal/Rentang boleh dipakai sebagian. Token unknown, kode opsi hilang, dan collision ditolak. Nama dasar tanpa `:` tetap memakai aturan existing; jangan membuka karakter bebas hanya untuk mendukung suffix.

### Resolve untuk feedback form

`ResolveQuestionsInput`: `{template_version: UUID, values: map}`; resolve tidak menyimpan draft atau bukti pemeriksaan. Variabel harus milik versi yang dapat diakses; nilainya divalidasi seperti generate. Resolver dapat menerima subset UUID untuk field yang berubah, tetapi menolak UUID asing.

`ResolvedQuestions`: `{contract_version:1, fields:[{id, value, display, words, tokens, calendar_state}]}`. `tokens` map nama token tanpa `${}` ke teks. Radio `display` label opsi, `words:null`; rentang `display` durasi. `calendar_state` null kecuali rentang kerja, berisi state pada bagian kalender. Untuk null opsional, `display:""`, `words:null`, tokens semua kosong. Nilai tidak valid mengembalikan error 422, bukan preview palsu. Frontend debounce 300 ms, abaikan response request lama, dan tampilkan status menghitung; mock memakai hasil deterministik. Backend tidak mempercayai hasil klien: menghitung saat isian baru/berubah, lalu memverifikasi hasil snapshot saat generate/issue. Save field lain tidak menghitung ulang rentang stale secara diam-diam; ikuti bagian snapshot.

## 5. Data Kalender Kerja dan kegiatan

### Tahun

`CalendarYear`:

```json
{
    "id": "00000000-0000-4000-8000-000000002026",
    "year": 2026,
    "revision": 1,
    "work_revision": 1,
    "status": "draft",
    "working_weekdays": [1, 2, 3, 4, 5, 6],
    "confirmed_at": null,
    "confirmed_by": null
}
```

ISO weekday: Senin 1 sampai Minggu 7. Tahun 1900–2199. Pola wajib 1–7 hari unik, diurutkan. `confirmed_by` null atau `{id:UUID,name:string}`. Status `ready` hanya jika revision kerja terakhir sudah dikonfirmasi. `revision` berubah pada perubahan tahun/kegiatan terkait; `work_revision` hanya berubah karena pola atau pengecualian libur/kerja. Konfirmasi menambah revision umum dan menyimpan revision kerja yang dikonfirmasi, tanpa menaikkan work_revision.

Belum ada tahun: `calendar:null`, bukan otomatis membuat record saat GET. `prepareYear` menerima `{year, copy_from_year:null|number}`; null memakai pola awal Senin–Sabtu. Hanya pola disalin; semua tahun baru draft. Duplikat year mengembalikan 409. Edit pola dan confirm menerima `revision` yang dilihat pengguna; mismatch 409 tanpa overwrite.

### Kegiatan

`CalendarEvent`:

```json
{
    "id": "00000000-0000-4000-8000-000000000101",
    "revision": 1,
    "title": "Pelatihan internal",
    "kind": "activity",
    "start_date": "2026-11-02",
    "end_date": "2026-11-02",
    "all_day": false,
    "start_time": "08:00",
    "end_time": "10:00",
    "location": "Ruang rapat",
    "description": null,
    "origin": "manual",
    "source_url": null,
    "imported_at": null,
    "calendar_revisions": { "2026": 2 }
}
```

Kind `activity|holiday|working_day`; origin `manual|national_holiday|collective_leave`. Origin/source/imported_at adalah field server, tidak diterima pada form manual. Judul 1–150, lokasi maksimal 255, keterangan maksimal 5000; opsional kosong null. Tanggal akhir inklusif; maksimal 366 tanggal per event. Timezone tunggal mengikuti `config('app.timezone')`, dikirim server, bukan pilihan browser/pegawai. Jam bukan UTC; tanggal libur jangan digeser lewat konversi timezone.

Semua tahun yang disentuh event harus sudah ada, tetapi kegiatan biasa tidak memerlukan status ready. Event lintas tahun diperbolehkan dalam batas rentang. All-day membuat kedua time null. Activity bertanggal/jam memakai interval mulai inklusif–selesai eksklusif; akhir wajib lebih besar dari awal. Waktu hingga `23:59`; selesai tengah malam berikutnya ditulis tanggal berikutnya `00:00`. Belum mendukung DST timezone lain, pengulangan, peserta, status, atau free color.

Holiday/working_day wajib all_day true dan time null. Setiap tanggal hanya boleh punya satu pengecualian efektif, termasuk rentang yang tumpang tindih: create/update manual ditolak 422 pada tanggal bentrok, jangan memilih pemenang otomatis. Kegiatan biasa boleh bertumpuk. Hari libur rutin dari pola tidak perlu event tersimpan. Edit manual event hasil impor mengubah origin menjadi manual dan menyimpan jejak sumber di audit.

Create menerima field writable event di atas serta `calendar_revisions` untuk seluruh tahun terdampak; update menambah `revision` event dan mencakup union tahun lama/baru; delete menerima revision event dan map seluruh tahun lama. API memperbarui dan mengembalikan map tahun baru. Semua perubahan atomik, lock tahun berurutan lalu event; tidak partial save lintas tahun.

### Penyimpanan minimum milik backend

- `work_calendar_years`: unique year, UUID, weekdays JSON, revision, work_revision, confirmed_work_revision nullable, confirmed_at/aktor, timestamps.
- `calendar_events`: UUID, revision, field kegiatan, metadata sumber, timestamps. Simpan pengecualian harian pada `work_calendar_exceptions` dengan unique tanggal, FK event dan year; hapus/update event memperbarui seluruh tanggal atomik.
- `work_calendar_audits`: aktor tersimpan, perubahan, waktu; mencakup pola, konfirmasi, event, dan impor. Tidak menambah timeline frontend dalam v1.
- `holiday_import_previews`: UUID/token hash, aktor, tahun, revision tahun, snapshot hasil fetch/tinjauan, expiry, hasil apply nullable. Preview tidak mengubah kalender; apply dapat diulang dengan token sama untuk mengembalikan hasil yang sudah tersimpan.
- Konfigurasi tipe baru pada variabel menggunakan nullable JSON config terstruktur sesuai tiga field di atas; opsi radio relasional berurutan dengan UUID, code unique per variable. Migration additive; tidak merombak tipe lama.

Nama kolom internal dapat mengikuti konvensi selama skema publik tetap sama. Constraint, revision, dan idempotensi adalah bagian wajib, bukan pilihan internal yang boleh dihilangkan.

## 6. Endpoint yang dibekukan

Prefix halaman/data `/work-calendar`; nama route `wc.*`. `{year}` integer, `{event}` UUID dengan binding dan auth. Semua read baru selain index merupakan JSON; write kalender baru juga JSON. Operasi templat/Surat existing tetap response/redirect existing.

| Method dan URL                                      | Route                 | Input → data hasil                                                                   |
| --------------------------------------------------- | --------------------- | ------------------------------------------------------------------------------------ |
| GET `/work-calendar`                                | `wc.index`            | CalendarQuery → Inertia `work-calendar/index`, page.id `work-calendar`, calendarData |
| GET `/work-calendar/data`                           | `wc.data`             | CalendarQuery → CalendarPageData                                                     |
| POST `/work-calendar/years`                         | `wc.years.store`      | PrepareYearInput → CalendarYear                                                      |
| PATCH `/work-calendar/years/{year}`                 | `wc.years.update`     | `{revision,working_weekdays}` → CalendarYear                                         |
| POST `/work-calendar/years/{year}/confirm`          | `wc.years.confirm`    | `{revision}` → CalendarYear                                                          |
| POST `/work-calendar/events`                        | `wc.events.store`     | CreateEventInput → CalendarEvent                                                     |
| PUT `/work-calendar/events/{event}`                 | `wc.events.update`    | UpdateEventInput → CalendarEvent                                                     |
| DELETE `/work-calendar/events/{event}`              | `wc.events.destroy`   | DeleteEventInput → DeletedEvent                                                      |
| POST `/work-calendar/years/{year}/holidays/preview` | `wc.holidays.preview` | `{revision,include_collective_leave:false}` → HolidayPreview                         |
| POST `/work-calendar/years/{year}/holidays/apply`   | `wc.holidays.apply`   | `{preview_id,preview_token,selected_ids}` → HolidayApplyResult                       |
| POST `/work-calendar/count-days`                    | `wc.count`            | `{start_date,end_date,basis}` → DayCount                                             |
| POST `/letter-question-values/resolve`              | `lt.values.resolve`   | ResolveQuestionsInput → ResolvedQuestions                                            |

CalendarQuery: `{year?:number, date?:YYYY-MM-DD, view?:"month"|"week"|"day"|"agenda", search?:string, kind?:"activity"|"holiday"|"working_day"}`. Default date hari berjalan pada timezone aplikasi; default year dari date; jika year dikirim tanpa date pilih tanggal pertama bulan yang sama pada year tersebut (tanggal diklem valid). Jika keduanya dikirim, year harus sama dengan tahun date. Pencarian literal judul/keterangan/lokasi maksimal 100; kind kosong berarti semua. Tidak memengaruhi data kerja yang dipakai kalkulasi.

CalendarPageData: `{contract_version:1, timezone, query:{year,date,view,search,kind}, calendar:CalendarYear|null, calendar_years:CalendarYear[], period:{start_date,end_date}, days:CalendarDay[], events:CalendarEvent[], missing_years:number[], truncated:false}`. `calendar_years` memuat semua tahun tersimpan yang bersinggungan period, minimal tahun terpilih jika ada. `CalendarDay={date,weekday,in_selected_month,is_today,is_working:boolean|null,basis:"weekly"|"holiday"|"working_day"|"unconfigured",exception_id:UUID|null}`. Tahun belum ada menghasilkan is_working null, tidak menebak Senin–Sabtu sebagai konfigurasi sah.

Month memakai grid 42 hari mulai Senin sebelum/tepat tanggal 1. Week Senin–Minggu, day satu hari, agenda seluruh bulan date. Event dipilih berdasarkan overlap period, urut start_date/start_time/id; activity all-day di depan timed pada tanggal sama. Semua hari dikirim, filter hanya memengaruhi events. Maksimal 1000 event hasil; jika melampaui, 422 minta persempit query, jangan diam-diam truncate. Tampilan agenda memuat semua event bulan tersebut tanpa pagination pada batas ini.

### Kalkulasi

DayCount: `{start_date,end_date,basis,days:number,words:string,duration:string,dates:[{date,counted,reason}],calendar_state}`. `reason` adalah `calendar_day|working_day|weekly_off|holiday`. `calendar_state` null untuk calendar_days, atau `{state:"current",years:[{year,work_revision}]}` untuk working_days. Belum siap/missing menghasilkan 422 dengan errors `calendar` dan code sesuai tabel; tidak mengembalikan hasil sukses sementara. Nol working days menghasilkan `zero_working_days`. Rentang maksimal 366 hari, termasuk tahun kabisat. Nol tanggal optional ditangani resolver pertanyaan, bukan countDays.

## 7. Impor libur: kontrak preview dan apply

Helper internal PHP: `FetchIndonesianHolidays::fetch(int $year): array` menghasilkan `[{date,name,is_collective_leave,source_url}]`. Filter cuti bersama dilakukan dalam preview. Helper tidak mengubah DB kalender dan tidak memakai server Deno. Namespace mengikuti `App\Services\WorkCalendar`.

Gunakan Laravel HTTP client dengan URL sumber tetap, connect timeout 3 detik, timeout 10 detik, maksimal satu retry; cache hasil valid 24 jam per tahun. Batasi HTML 2 MB dan parsing tanpa network/entity eksternal. Validasi tahun/mapping bulan/tanggal, struktur 12 bulan, rentang valid, dan hasil tidak kosong; kegagalan struktur adalah error, bukan sukses kosong. Simpan fetched_at/from_cache pada preview. Jangan memasang parser baru jika DOM PHP existing cukup. Mempertahankan lisensi kode yang diadaptasi wajib.

HolidayPreview: `{id:UUID,token:string,year,calendar_revision,include_collective_leave,source_url,fetched_at,from_cache,expires_at,items:[{id:UUID,date,name,kind:"national_holiday"|"collective_leave",decision:"new"|"duplicate"|"conflict",reason:null|"manual_working_day"|"existing_holiday"|"source_changed",selectable:boolean}],summary:{new,duplicate,conflict}}`.

Preview berlaku 15 menit, terikat aktor, tahun, revision kalender, dan snapshot sumber. `new` selectable true/default dipilih; duplicate/conflict selectable false. Libur manual pada tanggal sama dianggap duplicate dan dipertahankan. Hari kerja pengganti manual adalah conflict. Sumber yang mengubah isi libur impor lama adalah conflict; v1 tidak memperbarui/menghapus event lama otomatis. Beberapa libur sumber pada tanggal sama digabung menjadi satu item dengan nama unik digabung `; `, tidak membuat pengecualian ganda.

Apply hanya menerima ID item new yang dipilih dari preview tersimpan. Tidak menerima nama/tanggal/sumber dari browser. Minimal satu dipilih; jika tidak ada new, UI menampilkan tidak ada perubahan dan tidak menawarkan Apply. Token usang karena kalender berubah menghasilkan 409 dan perlu preview baru. Token expired 410. Identitas salah 404. Apply transaksi atomik, menambah event all-day dan work_revision hanya jika ada perubahan; status kembali draft. Tidak boleh partial apply ketika state berubah di tengah transaksi.

HolidayApplyResult: `{preview_id,created:number,skipped:number,calendar:CalendarYear,calendar_revisions:Record<string,number>}`. Token sukses yang diulang oleh aktor sama mengembalikan hasil apply terdahulu, tanpa efek kedua. Tinjauan/Apply yang dibatalkan tidak mengubah kalender atau status readiness.

## 8. Snapshot, kompatibilitas, dan invalidasi

Kontrak ini menetapkan default keputusan terbuka proposal: **draft tidak dihitung ulang diam-diam**. Hasil tetap ditampilkan, tetapi dianggap stale jika work_revision tahun rujukan berubah atau tahun menjadi belum siap. Activity tidak membuat stale. Perubahan hanya pada tahun yang tidak dirujuk tidak mengusangkan perhitungan tersebut.

Simpan basis, tanggal, canonical value, display, words, tokens, timezone, dan daftar year/work_revision pada snapshot pertanyaan. Backend menyertakan digest state tersebut dalam fingerprint/bukti generate dan penerbitan existing. Jangan mengganti schema renderer string-values jika tidak diperlukan: map token akhir tetap string literal. Snapshot lama tetap dibaca dengan default config null. Pemeriksaan work_revision saat refresh/generate/finalisasi terbit harus dilindungi shared lock pada record tahun sampai transaksi selesai agar perubahan kalender tidak menyelinap setelah pemeriksaan. Perluas urutan lock existing menjadi template → tahun kalender berurutan → surat → draft → klasifikasi → operasi; baca tahun kandidat tanpa lock lalu periksa ulang identitas/revision draft setelah lock, ulangi secara terbatas bila berubah. Mutasi kalender mengambil lock tahun berurutan lalu event dan tidak mengunci Surat. Jangan menahan lock DB selama fetch HTTP atau rendering.

Jika stale, tampilkan `calendar_state:{state:"stale",years:[...],changed_years:[...]}` pada detail draft. Generate/konfirmasi/issue ditolak sampai refresh eksplisit. Tahun belum siap juga menahan refresh. Integrator memperluas alur master-refresh existing menjadi refresh dependensi yang menampilkan perubahan durasi beserta token konfirmasi; tidak membuat endpoint draft-refresh baru. Apply refresh memeriksa draft revision dan kalender lagi, menaikkan revision draft, dan membatalkan pemeriksaan lama. Penyimpanan field lain mempertahankan hasil kalender lama; perubahan rentang oleh pengguna atau refresh eksplisit menghitung ulang.

Saat draft belum punya hasil valid, nilai mentah rentang boleh tersimpan mengikuti aturan draft belum lengkap existing, dengan state `missing`; generate dan issue tetap diblokir. Resolver form/read tidak pernah menyimpan snapshot. Revisi/pemulihan tetap memakai aturan tanggal/nomor resmi existing.

Copy versi mempertahankan konfigurasi/kode opsi dan memberi UUID record baru; penyesuaian draft memetakan pertanyaan berdasarkan aturan existing ditambah config baru. Nilai radio dipertahankan jika code masih ada; jika tidak, minta pilih ulang. Perubahan tipe/basis/scale yang membuat nilai tidak kompatibel ditampilkan dalam ringkasan, bukan dipaksa konversi.

Ekspor/impor templat membawa config baru dan opsi/kode, **tidak membawa kalender instansi, event, isian surat, atau hasil hitung**. Backend memiliki versioning manifest; menaikkan versi konfigurasi paket jika parser lama tidak dapat menolak fitur baru dengan aman. Pembaca baru tetap menerima paket lama dengan config null; pembaca yang tidak mendukung tipe baru harus menolak jelas, tidak menurunkannya menjadi teks. Integrator memastikan test round-trip dan kompatibilitas sebelum release.

## 9. UI dan persetujuan komponen

Komponen yang ditemukan saat kontrak dibuat: calendar kecil existing, input, switch, select, button, dialog/alert-dialog. Keberadaan calendar kecil tidak membuktikan tersedia event-calendar empat tampilan. Radio group dan input jam khusus perlu inventaris lebih lanjut; jangan membuat primitive sendiri untuk menghindari permintaan izin.

Frontend melakukan inventaris komponen pada awal pekerjaan dan mengajukan **satu daftar kebutuhan spesifik** ke pengguna. Jika ada yang belum tersedia, bagian UI terkait menunggu persetujuan; backend, fixture, schema, validation, serta test gateway tetap berjalan. Larangan ini tidak boleh diubah menjadi alasan seluruh pekerjaan berhenti.

Gunakan komponen shared/shadcn Base UI existing, Hugeicons, token dan layout proyek. Week/day menampilkan sumbu jam 00:00–24:00 dengan scroll ke 08:00 saat dibuka, all-day terpisah. Month/agenda menampilkan jam pada timed activity. Tidak mendukung drag resize/move pada v1; Ubah melalui dialog. Search dan filter mempertahankan year/date/view di URL; back/forward memulihkan query. Semua label Indonesia; status tetap teks, bukan hanya warna. Pengujian visual/cetak dilakukan pengguna.

## 10. Milestone, test, dan serah terima

| Milestone | Backend dapat selesai mandiri                     | Frontend dapat selesai mandiri                                | Gerbang integrasi                                    |
| --------- | ------------------------------------------------- | ------------------------------------------------------------- | ---------------------------------------------------- |
| M0        | Enum/config/validator dan test fixture            | Tipe, schema, mock gateway, inventaris komponen               | Kontrak fixture sama; komponen yang kurang diajukan  |
| M1        | Radio, numeric, terbilang, token/render, exchange | Editor pertanyaan dan field radio/numeric, readonly terbilang | Wiring templat/generate/Surat + regression           |
| M2        | Tahun/event/readiness/count API + konkurensi      | Empat tampilan kalender, dialog, pola mingguan, confirm       | Wayfinder/adapter HTTP, sidebar, halaman nyata       |
| M3        | Fetch/parser, preview/apply idempoten             | Tinjauan impor, pilihan cuti bersama, error/conflict          | Fixture HTML valid/rusak, HTTP fake, uji apply nyata |
| M4        | Rentang/snapshot/stale/refresh/issue guard        | Rentang, rincian hitung, stale/refresh UI                     | Alur Surat penuh dan histori tetap identik           |

M0–M4 adalah milestone integrasi satu pengembangan pada dokumen ini. Agent tidak menaikkan versi branch masing-masing. Integrator saja melakukan kenaikan patch pada tahap terintegrasi yang lulus, dan satu minor/reset patch saat seluruh pengembangan selesai; versi aktual dihitung dari base aplikasi terbaru, bukan hardcode `0.6.0`. Release/PR mengikuti aturan proyek; tidak auto-PR.

Test BE minimal: endpoint auth/scope, seluruh shape/enum baru, optional/null/zero/precision, token terpecah/header/footer, CAS tahun/event, rollback lintas tahun, source errors, dedup impor/ulang apply, readiness, count inklusif/leap/lintas tahun, aktivitas tidak mengubah work_revision, draft stale, dan dokumen historis tidak berubah. Test produksi tidak memanggil situs libur nyata; gunakan HTTP fake/fixture. Parser live smoke opsional terpisah, bukan syarat determinisme suite.

Test FE minimal: gateway mock success/422/409/410/502, isian tidak hilang, nama otomatis berhenti mengikuti label setelah manual/disimpan, radio tunggal, angka format Indonesia, response resolver lama diabaikan, empat query view, all-day/time, konflik impor tidak dapat dipilih, loading/double submit, dan stale refresh. Test DOM tidak diklaim sebagai uji visual.

Frontend dan backend memiliki database/storage test terpisah bila menjalankan Laravel secara paralel; jika tidak, jadwalkan satu runner DB saja. Git worktree tidak mengisolasi port, PostgreSQL, storage fake, atau cache. Jangan menjalankan dua suite yang memakai DB/storage sama bersamaan.

Handoff setiap milestone wajib memuat: contract ID, branch, SHA commit, file berubah, method/endpoint selesai, fixture yang lulus, perintah dan hasil test, bagian belum selesai, serta permintaan integrator. Commit terarah bahasa Inggris, tanpa atribusi assistant. Tidak membawa secret, config lokal, atau file pengguna yang tidak terkait.

Kriteria selesai: BE dan FE masing-masing lulus testnya; adapter nyata lulus endpoint; integrator menjalankan regression templat/Surat/penomoran/pertukaran, Pint, format check terarah, check:ui pada perubahan, types:check, check dan build. Checklist visual diberikan pada pengguna. Belum siap jika mock masih menjadi jalur produksi atau issue dapat melewati snapshot stale.

## 11. Perubahan kontrak dan instruksi siap kirim

Tidak boleh mengganti field, enum, route, format angka, atau perilaku error secara sepihak. Tulis permintaan `CONTRACT-CHANGE` berisi alasan, field terdampak, contoh sebelum/sesudah, kompatibilitas, dan test. Integrator memutuskan, memperbarui dokumen, lalu mengirim satu SHA kontrak yang sama ke kedua agent. Utamakan perubahan additive dengan default; perubahan breaking menaikkan Contract ID. Selama menunggu keputusan, agent melanjutkan bagian yang tidak terdampak dan tidak menciptakan kontrak tandingan.

Instruksi backend:

> Baca aturan proyek dan docs/planning/letter-inputs-calendar-contract.md. Kerjakan area backend pada branch feat/letter-inputs-calendar-api dari SHA baseline yang ditentukan. Jangan mengubah resources/js, routes bersama, atau versi. Implementasikan respons tepat sesuai kontrak dan uji tanpa menunggu UI. Kirim handoff per milestone; perubahan kontrak harus diajukan, bukan diterapkan sepihak.

Instruksi Antigravity/frontend:

> Baca aturan proyek, proposal, dan docs/planning/letter-inputs-calendar-contract.md. Kerjakan pada feat/letter-inputs-calendar-ui dari SHA baseline yang ditentukan. Mulai dari tipe, fixture, mock CalendarGateway/QuestionGateway, dan inventaris komponen. Backend nyata bukan prasyarat. Gunakan shadcn Base UI existing; minta persetujuan untuk kekurangan komponen. Jangan mengubah PHP, routes, generated Wayfinder, root wiring milik integrator, atau versi. Kirim handoff per milestone.

Instruksi integrator:

> Pegang feat/letter-inputs-calendar. Simpan baseline kontrak tanpa membawa pekerjaan lain; komunikasikan SHA. Pasang wrapper, routes, adapter HTTP Wayfinder, sidebar, dan root wiring dari hasil kedua agent. Integrasikan per milestone, selesaikan konflik file shared, jalankan regression, lalu kelola versi/release sesuai aturan. Jangan membuat PR tanpa persetujuan pengguna.
