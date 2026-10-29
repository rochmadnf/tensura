# Proposal: Pertanyaan Surat, Kalender Kerja, dan Terbilang

Tanggal rekapan: 30 Oktober 2026.

Status: proposal dan handoff lintas agent. Dokumen ini menyimpan keputusan percakapan; bukan laporan bahwa fitur sudah diimplementasikan atau seluruh kontrak teknis sudah final. Periksa repository terbaru sebelum bekerja. Bagian keputusan terbuka harus diselesaikan sebelum implementasi yang bergantung padanya.

**Urutan pelaksanaan terbaru:** [backend dahulu, frontend setelah handoff](letter-inputs-calendar-backend-first-plan.md). Antigravity belum mulai; pembagian paralel di bagian akhir proposal adalah riwayat yang sudah digantikan. [Kontrak v1 pertanyaan dan kalender](letter-inputs-calendar-contract.md) tetap menjadi acuan payload, endpoint, dan default teknis, sedangkan branch/pembagian tugas/strategi pelaksanaan mengikuti rencana aktif. Bagian keputusan terbuka di bawah merekam keadaan diskusi saat rekapan dibuat; default yang sudah ditetapkan kontrak menjadi acuan kerja kecuali pengguna mengoreksinya. Persetujuan komponen yang belum tersedia tetap harus diminta.

## 1. Tujuan dan konteks

Tensura sudah memiliki pengelolaan Templat Surat dan Surat. Pengembangan ini menambahkan pertanyaan pilihan tunggal, angka/nominal dengan terbilang, kalender kerja beserta kegiatan, pengambilan hari libur, serta rentang tanggal dengan durasi otomatis.

Pertahankan alur existing: konfigurasi dan versi templat, validasi DOCX, generate uji, draft Surat bersama, preview/pemeriksaan, penerbitan, revisi, pembatalan, pemulihan, penomoran, dan dokumen historis. Konfigurasi baru juga perlu diperhitungkan dalam ekspor/impor templat yang tersedia pada repository terbaru.

Permintaan menyimpan dokumen ini tidak mengotorisasi implementasi seluruh proposal, pemasangan penghubung agent, atau pembuatan PR. Pengguna sudah meminta penggunaan paket terbilang yang disebut di bawah, tetapi pemasangan belum dilakukan dalam pekerjaan dokumentasi ini.

## 2. Pilihan tunggal atau radio

### Konfigurasi dan hasil DOCX

- Satu pertanyaan berisi beberapa opsi; pengguna hanya boleh memilih satu dalam kelompok tersebut. Kelompok pertanyaan lain tetap independen.
- Opsi ditulis langsung pada templat, bukan dari Daftar Referensi.
- Enam atau delapan opsi tetap merupakan satu pertanyaan.
- Isi jika terpilih ditetapkan sekali per pertanyaan, default `✔`, dan dapat diubah admin.
- Placeholder opsi terpilih diganti isi tersebut; semua opsi lain diganti teks kosong, bukan otomatis menjadi kotak kosong.
- Posisi sel dan struktur tabel mengikuti DOCX sumber. Setiap lokasi tujuan memerlukan placeholder sendiri.

Contoh label pertanyaan **Jenis pelayanan**, variabel `jenis_pelayanan`:

| Opsi        | Kode opsi     | Placeholder                      |
| ----------- | ------------- | -------------------------------- |
| Rawat jalan | `rawat_jalan` | `${jenis_pelayanan:rawat_jalan}` |
| Rawat inap  | `rawat_inap`  | `${jenis_pelayanan:rawat_inap}`  |

Jika Rawat inap dipilih, placeholder Rawat inap menjadi `✔` dan Rawat jalan menjadi kosong. Form Surat memakai radio; susunan vertikal diusulkan agar pilihan mudah dibaca.

### Nama dan identitas

- Nama variabel disarankan dari label pertanyaan, kode opsi dari label opsi. Keduanya dapat diubah admin secara terpisah.
- Pada konfigurasi baru yang belum disimpan dan belum diberi nama manual, saran nama dapat mengikuti label.
- Setelah disimpan, perubahan label tidak otomatis mengubah nama/kode. Perubahan urutan tidak mengubah kode.
- Nama/kode tidak valid dan duplikat ditolak. Aturan keunikan rinci mengikuti keputusan teknis yang perlu ditetapkan.
- Placeholder tersedia untuk disalin. Jika kode yang sudah dipasang di DOCX diubah, tampilkan pengingat memperbarui sumber dan memvalidasi ulang.
- Perubahan templat terbit mengikuti lifecycle draft/versi existing.

Sintaks `:` adalah kebutuhan baru. Dukung secara konsisten dalam katalog token, konfigurasi frontend/backend, validasi, DOCX contoh, snapshot, renderer, penyesuaian draft, dan ekspor/impor. Jangan menganggap parser existing sudah mendukungnya.

## 3. Angka dan Nominal

### Jenis dan placeholder

| Jenis   | Pengaturan per pertanyaan                      | Nilai       | Terbilang             |
| ------- | ---------------------------------------------- | ----------- | --------------------- |
| Angka   | Bulat atau desimal                             | `${jumlah}` | `${jumlah:terbilang}` |
| Nominal | Rupiah bulat atau pecahan maksimal dua desimal | `${biaya}`  | `${biaya:terbilang}`  |

Contoh keluaran:

- `12` → `Dua Belas`.
- `12,5` → `Dua Belas Koma Lima`.
- `Rp1.250.000` → `Satu Juta Dua Ratus Lima Puluh Ribu Rupiah`.
- `Rp1.250.000,50` → `Satu Juta Dua Ratus Lima Puluh Ribu Rupiah dan Lima Puluh Sen`.

### Perilaku

- Terbilang selalu tersedia tanpa perlu pertanyaan tambahan. Pengguna cukup mengisi nilai.
- Terbilang tampil baca-saja pada form dan diperbarui mengikuti nilai.
- Backend menghitung terbilang; jangan mempercayai hasil kiriman frontend sebagai sumber kebenaran.
- Nama variabel otomatis tetap dapat diubah. Kedua placeholder tersedia untuk disalin dan bebas ditempatkan di DOCX, misalnya `${jumlah} (${jumlah:terbilang})`.
- Pisahkan format tampilan Indonesia dari representasi penyimpanan. Penanganan pemisah ribuan/desimal harus tidak ambigu.
- Jaga presisi nominal tanpa aritmetika floating point. Rupiah dan Sen diproses terpisah.
- Simpan nilai dan hasil terbilang dalam snapshot agar rendering dan dokumen historis konsisten.

### Paket wajib

Gunakan `riskihajar/terbilang`, sesuai permintaan eksplisit pengguna:

- Repository: <https://github.com/riskihajar/terbilang>.
- Clone referensi: `/home/rochmadnf/Sites/node/terbilang`.
- Manifest clone yang diperiksa mencantumkan PHP `^8.1` dan dukungan Laravel 10–13.
- API yang diperiksa: `Terbilang::make(...)`, menghasilkan `Illuminate\Support\Stringable`.
- Tetapkan locale Indonesia dan sesuaikan kapitalisasi, misalnya `Dua Belas`.
- Paket belum terpasang pada pemeriksaan percakapan; periksa ulang versi/constraint saat implementasi dan gunakan Composer. Clone lokal merupakan referensi, bukan path runtime wajib.
- Gunakan paket untuk angka menjadi kata. Perhitungan hari kerja tetap dilakukan aplikasi, bukan fungsi selisih tanggal paket.

## 4. Kalender Kerja instansi

### Cakupan dan kesiapan

- Menu **Kalender Kerja** berada di grup **Data Master**.
- Satu kalender berlaku bagi seluruh instansi, dengan pengaturan per tahun.
- Pola awal Senin–Sabtu sebagai hari kerja, Minggu libur rutin. Admin dapat mengubah pola mingguan.
- Pengecualian tanggal berupa Libur atau Hari kerja pengganti mengalahkan pola mingguan.
- Dukung satu tanggal maupun rentang tanggal beserta keterangan.
- Setiap tahun perlu konfirmasi admin sebelum dipakai menghitung hari kerja. Status **Belum siap** atau **Siap digunakan**.
- Perubahan pola mingguan atau pengecualian hari kerja/libur memerlukan konfirmasi ulang. Perubahan kegiatan biasa tidak membatalkan konfirmasi tahunan.
- Tahun berikutnya dapat menyalin pola mingguan, tanpa otomatis menyalin tanggal libur.
- Konfirmasi berarti admin menyatakan sudah memeriksa. Sistem tidak dapat menjamin semua hari libur sudah dimasukkan.
- Sediakan pemeriksaan contoh rentang tanggal beserta rincian tanggal yang dihitung/dilewati untuk membantu konfirmasi.

### Tampilan

Referensi lokal yang diberikan pengguna:

- `examples/application-example.html`.
- `examples/application.css`.
- `examples/dialog-application-example.html`.

Gunakan susunan kalender besar dengan tujuh kolom pada tampilan bulanan, kotak tanggal berisi ringkasan, dan navigasi sebelumnya/berikutnya. Toolbar menyediakan Hari ini, pemilihan bulan/tahun, pencarian/filter, Tambah kegiatan, dan Ambil hari libur nasional. Pengaturan tahun serta konfirmasi kalender perlu mudah dijangkau.

Empat tampilan disepakati: **Bulan, Minggu, Hari, Agenda**. Minggu/Hari mendukung penyajian kegiatan berdasarkan jam; Bulan/Agenda menampilkan ringkasan. Klik tanggal membuka pengaturan dengan tanggal awal terisi. Sediakan akses daftar pengecualian agar pengelolaan tidak hanya bergantung pada klik kotak kalender.

HTML/CSS merupakan acuan komposisi, bukan aset yang disalin seluruhnya. Jangan membawa script, pelacak, font, stylesheet global, data contoh, atau ketergantungan situs asal ke aplikasi.

## 5. Kegiatan dan dialog tambah

Pengguna memilih cakupan **Kegiatan dan jam sekarang**, bukan seluruh fungsi event pada contoh.

| Field               | Perilaku                                        |
| ------------------- | ----------------------------------------------- |
| Judul               | Nama kegiatan atau pengecualian                 |
| Jenis               | Kegiatan biasa, Libur, Hari kerja pengganti     |
| Tanggal mulai–akhir | Satu tanggal atau beberapa tanggal              |
| Sepanjang hari      | Untuk kegiatan biasa                            |
| Jam mulai–selesai   | Muncul jika kegiatan biasa tidak sepanjang hari |
| Lokasi              | Opsional                                        |
| Keterangan          | Opsional                                        |

- Kegiatan biasa mendukung jam dan beberapa hari tetapi tidak memengaruhi perhitungan hari kerja/cuti.
- Libur dan Hari kerja pengganti berlaku sepanjang hari; belum ada pengurangan cuti berbasis jam/setengah hari.
- Pisahkan jenis yang memengaruhi hari kerja dari kategori kegiatan seperti rapat/pelatihan; kategori tambahan belum disepakati sebagai field tahap awal.
- Peserta, pengulangan, dan status kegiatan ditunda. Warna mengikuti jenis dan token proyek, tanpa pemilih warna bebas.
- Contoh dialog sumber juga memiliki kategori, status, pengulangan, peserta, dan warna; keberadaannya pada contoh bukan persetujuan mengimplementasikan seluruhnya.

## 6. Pengambilan hari libur Indonesia

### Referensi dan helper PHP

Referensi pengguna: `/home/rochmadnf/Sites/node/api-hari-libur`.

File utama: `src/libraries/scraper.ts`, `src/libraries/holiday.ts`, dan `src/constants/month.ts`.

Kode referensi mengambil `https://tanggalans.com/kalender-{tahun}`, membaca blok `.entry-content .kalender-indo`, judul bulan `.kal-title .kal-title-link`, dan `.kal-libur-list li` beserta `.kal-libur-day`. Nama bulan Indonesia dipetakan ke angka; rentang hari dipecah menjadi tanggal harian. Cuti bersama dikenali dari nama yang mengandung `cuti bersama`.

Port pengambilan/normalisasi ke helper atau layanan PHP. Tidak membutuhkan server Deno. Hasil memuat tanggal, nama libur, jenis, dan sumber. Pengambilan data dipisahkan dari penyimpanan kalender. Frontend mengakses endpoint Laravel melalui Wayfinder; URL sumber ditentukan backend, bukan URL bebas dari pengguna.

Struktur situs terkini belum berhasil diverifikasi pada pemeriksaan percakapan. Verifikasi ulang dan uji parser dengan fixture sebelum menyatakan siap. Jika mengadaptasi kode MIT, pertahankan pemberitahuan lisensinya.

### Alur tombol

1. Admin membuka tahun lalu memilih **Ambil hari libur nasional**.
2. Tersedia **Sertakan cuti bersama**, default tidak aktif.
3. Ambil data setahun, bukan hanya bulan yang tampil.
4. Tampilkan tinjauan tanggal/nama, data baru, duplikat, dan benturan dengan pengaturan manual.
5. Admin mengonfirmasi **Terapkan** sebelum penyimpanan.
6. Perubahan hari libur membuat tahun perlu dikonfirmasi ulang. Mengambil atau meninjau saja tidak mengubah status.

Pengambilan ulang tidak membuat duplikat atau menimpa data manual otomatis. Kegagalan jaringan/struktur HTML tidak boleh dianggap hasil kosong sukses dan tidak boleh menghapus data kalender existing. Kebijakan penyelesaian konflik manual masih perlu dikunci.

## 7. Rentang tanggal dan durasi

- Pengguna memilih tanggal mulai dan akhir. Admin templat memilih dasar perhitungan **hari kalender** atau **hari kerja** per pertanyaan.
- Hitung inklusif: tanggal mulai dan akhir ikut diperiksa. Tanggal awal/akhir boleh jatuh pada hari libur.
- Hari kalender menghitung semua tanggal. Hari kerja mengikuti pola dan pengecualian kalender instansi yang siap.
- Rentang lintas tahun memerlukan semua tahun terkait siap.
- Tampilkan jumlah dan rincian tanggal yang dihitung atau dilewati.
- Jika rentang menghasilkan nol hari kerja, minta perbaikan tanggal.
- Keluaran tersedia sebagai tanggal mulai/akhir, angka jumlah hari, terbilang, dan gabungan `12 (Dua Belas) hari`.
- Durasi dihitung aplikasi lalu angka dikonversi menggunakan paket terbilang.
- Nama suffix placeholder rentang tanggal belum final. Gunakan pola turunan yang konsisten setelah keputusan dikunci.

## 8. Draft, snapshot, dan histori

Pertahankan dokumen resmi/historis ketika kalender, font, master, atau templat terbaru berubah. Simpan dasar hitung, hasil, dan terbilang pada snapshot yang dipakai renderer. Preview, konfirmasi pemeriksaan, dan penerbitan harus mengikat data yang sama.

**Usulan belum mendapat persetujuan eksplisit:** perubahan kalender memberi pemberitahuan pada draft terdampak dan mewajibkan penghitungan ulang secara eksplisit sebelum penerbitan. Jangan mengubah durasi draft yang sudah diperiksa secara diam-diam. Kunci kebijakan ini sebelum implementasi yang bergantung padanya.

## 9. Aturan implementasi

- Baca AGENTS.md, FEATURE_WORKFLOW.md, LARAVEL_PATTERNS.md, aturan yang berlaku, dan skill relevan terbaru. Periksa versi paket, skema, serta source existing.
- Selalu gunakan komponen **shadcn berbasis Base UI yang tersedia**. Pengguna mewajibkan bertanya sebelum menambah atau membuat komponen yang belum tersedia.
- Gunakan Hugeicons, semantic tokens, shared components, dan copy Indonesia. Tidak menyalin Radix `asChild`, Lucide, warna bebas, atau menambah pustaka UI tanpa persetujuan.
- Tes visual/browser/cetak dilakukan pengguna secara mandiri; agent melakukan pengujian otomatis/nonvisual dan melaporkan batas verifikasi.
- PHP memakai Pint; frontend/dokumen memakai Oxfmt melalui Vite Plus; Prettier hanya untuk Blade sesuai aturan terbaru.
- Gunakan PostgreSQL disposable untuk test, bukan database aplikasi. Jangan mengubah/menghapus perubahan pengguna yang tidak terkait.
- Commit berbahasa Inggris. Ikuti kenaikan patch per tahap, minor per fitur, dan reset patch saat minor naik. Periksa versi saat mulai; jangan menghitung versi target dari dokumen lama.
- Dokumen proposal saja tidak menaikkan versi atau membuat release. Untuk pekerjaan release/PR, ikuti pemeriksaan GitHub, berkas catatan rilis Markdown, tag, push, dan persetujuan PR pada aturan terbaru. Jangan membuat PR otomatis.

## 10. Verifikasi yang diperlukan

| Bagian            | Kasus utama                                                                                                                                                                         |
| ----------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Radio             | Tepat satu pilihan, opsi tidak sah, aturan wajib/opsional, kode duplikat, perubahan label/urutan, centang DOCX, sumber dengan token terpecah/header/footer sesuai renderer existing |
| Angka             | Bulat/desimal, format Indonesia, input tidak valid, terbilang, snapshot                                                                                                             |
| Nominal           | Presisi Rupiah/Sen, pecahan nol, batas nilai, penyimpanan tanpa float                                                                                                               |
| Kalender          | Pola mingguan, pengecualian, kesiapan tahun, tahun kabisat, lintas tahun, perubahan tanpa kehilangan data                                                                           |
| Kegiatan          | Sepanjang hari/jam, urutan mulai–akhir, lintas tanggal, kegiatan tidak mengubah hitungan kerja, empat tampilan                                                                      |
| Pengambilan libur | Sumber valid/rusak, HTTP gagal, pemetaan bulan/rentang, cuti bersama opsional, duplikat, konflik manual, tinjauan tanpa mutasi, penerapan konkuren                                  |
| Surat/templat     | Validasi/generate uji, preview, pemeriksaan, penerbitan, revisi/pemulihan, histori, penyesuaian draft, copy versi, ekspor/impor konfigurasi                                         |

Jalankan test terdampak, Pint jika PHP berubah, serta format check, `pnpm check:ui`, `pnpm types:check`, `pnpm check`, dan build sesuai perubahan dan aturan proyek. Jangan mengklaim checklist visual pengguna sudah lulus karena test DOM/build lulus.

## 11. Keputusan terbuka

1. Kebijakan draft ketika kalender yang dirujuk berubah, termasuk kapan pemeriksaan menjadi usang.
2. Batas nilai, angka negatif, nol, dan maksimal presisi Angka desimal; aturan wajib/opsional tiap jenis baru.
3. Nama placeholder turunan Rentang tanggal, format tanggal, dan benturan namespace token antarjenis.
4. Dua pengecualian berbeda pada tanggal yang sama serta rentang yang tumpang tindih.
5. Pilihan tindakan jika impor libur berbenturan dengan data manual atau sumber mengubah/menghapus data lama.
6. Timezone, batas kegiatan, dan aturan kegiatan lintas tahun.
7. Inventaris komponen radio, kalender besar, input jam, dan pendukung yang tersedia; minta persetujuan sebelum menambah yang belum ada.
8. Kontrak teknis: skema/API/resource/error, versioning pertanyaan/snapshot/paket pertukaran, konkurensi, dan invalidasi bukti.

Jangan menganggap usulan pada bagian ini sudah diputuskan pengguna. Klarifikasi keputusan produk; temukan fakta teknis melalui repository terlebih dahulu.

## 12. Urutan dan kolaborasi lintas agent

Urutan usulan: **radio → Angka/Nominal dan terbilang → Kalender Kerja/kegiatan → pengambilan libur → Rentang tanggal dan integrasi Surat**. Ini urutan logis, belum pembagian tahap final atau jadwal.

### Cara kerja yang dapat dipakai sekarang

- Codex dan Antigravity dapat memakai repository yang sama sebagai sumber, tetapi disarankan checkout/worktree dan branch terpisah untuk menghindari satu index/staging area yang dipakai bersama.
- Dokumen ini menjadi handoff. Masing-masing agent membaca aturan terbaru serta source, tidak hanya rekapan.
- Kunci dulu kontrak data/API, daftar komponen, dan keputusan produk terbuka. Jangan membuat frontend final dari payload rekaan yang berbeda dari backend.
- Pembagian usulan: Codex menangani backend, renderer/snapshot, integrasi dan pengujian; Antigravity menangani frontend kalender sesuai kontrak dan komponen yang disetujui. Pembagian ini belum penugasan aktif.
- Tetapkan pemilik file bersama seperti routes, resource/types, editor templat, dan catatan rilis. Satu integrator menangani benturan, integrasi, serta versi/release.
- Setiap handoff menyertakan branch, commit, file berubah, kemampuan selesai, kontrak yang berubah, hasil test, dan kendala. Jangan menyertakan secret atau environment pribadi.
- Git branch/worktree tidak mengisolasi database/storage test. Gunakan database dan direktori sementara terpisah untuk test paralel, atau jalankan bergantian.
- Sinkronkan commit handoff ke masing-masing branch melalui Git sebelum agent lain mulai. Perubahan pada dokumen di satu worktree tidak otomatis muncul di worktree lain.

Contoh instruksi untuk Antigravity setelah dokumen tersedia pada branch-nya:

> Baca AGENTS.md, FEATURE_WORKFLOW.md, LARAVEL_PATTERNS.md, dan docs/planning/letter-questions-work-calendar-proposal.md. Fokus pada frontend Kalender Kerja. Mulai dengan inventaris komponen shadcn berbasis Base UI yang tersedia dan kebutuhan komponen yang belum ada. Laporkan kekurangan untuk persetujuan pengguna. Jangan implementasikan sebelum kontrak API dan pembagian file disepakati; jangan mengubah backend atau membuat PR/release otomatis.

### Jika menginginkan komunikasi otomatis

Sesi ini belum memiliki tool untuk mengirim tugas langsung ke Antigravity. Subagent internal Codex bukan otomatis agent Antigravity. Dokumen bersama memungkinkan handoff melalui pengguna, bukan percakapan otomatis.

Codex mendukung penggunaan tool melalui MCP; Codex App Server menyediakan antarmuka untuk integrasi client. Membuat komunikasi dua arah tetap memerlukan bridge/orchestrator, dukungan akses tugas pada sisi Antigravity, serta pengaturan izin, status, pembatalan, dan kepemilikan file. Memasang MCP yang sama pada dua client tidak otomatis menghubungkan percakapan atau konteks keduanya.

Dukungan Antigravity yang terpasang belum diperiksa; jangan mengarang endpoint, perintah, atau format konfigurasi. Menyiapkan bridge merupakan pekerjaan terpisah dan tidak dilakukan saat menyimpan rekapan ini.

Referensi resmi untuk menilai opsi integrasi:

- [Codex MCP](https://developers.openai.com/codex/mcp).
- [Codex App Server](https://developers.openai.com/codex/app-server).
