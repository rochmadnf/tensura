# Templat Surat MVP 1 — Proses Bisnis

Status: rekap proses bisnis yang disetujui melalui diskusi. Belum diimplementasikan.

Dokumen ini mencatat keputusan terbaru khusus fitur Templat Surat. Jika berbeda dengan `letter-template-mvp1.md`, keputusan dalam dokumen ini menjadi acuan untuk fitur Templat Surat. Rancangan fondasi database ada di [letter-template-proposed-plan.md](letter-template-proposed-plan.md).

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

- Referensi: seluruh grup/item yang masih tersedia. MVP 1 tidak menambah status aktif pada master referensi.
- Pegawai aktif.

### Tabel berulang

Admin memilih kolom dan nama placeholder berdasarkan data existing.

| Sumber | Data yang tersedia |
| --- | --- |
| Pegawai | Nomor urut, nama beserta gelar, NIP, jabatan |
| Referensi | Nomor urut dan isi item |

Contoh Surat Tugas Perjalanan:

| No | Nama | NIP | Jabatan |
| --- | --- | --- | --- |
| `${pegawai_no}` | `${pegawai_nama}` | `${pegawai_nip}` | `${pegawai_jabatan}` |

- Admin menyiapkan satu baris contoh dalam DOCX.
- PHPWord menggandakan baris sesuai jumlah pilihan dan mengisi setiap kolom.
- Nomor urut dibuat otomatis.
- Belum ada isian tambahan per baris atau perhitungan transaksi seperti qty, amount, atau total.
- Contoh 2–5 pegawai bukan batas wajib.

## 4. Penandatangan

- Form menyediakan satu slot secara default; admin dapat menonaktifkannya jika tidak diperlukan.
- Kandidat berasal dari pegawai aktif.
- Templat dapat memiliki beberapa slot, masing-masing dengan kandidat.
- Saat digunakan, setiap slot harus diisi orang berbeda.
- Satu slot menggunakan `${penandatangan}`.
- Beberapa slot menggunakan `${penandatangan_1}`, `${penandatangan_2}`, dan seterusnya.
- Slot aktif wajib memiliki kandidat dan placeholder yang sesuai sebelum publikasi.

## 5. Validasi dan pengujian DOCX

- Ukuran maksimal DOCX sumber default 10 MB, dibaca dari config.
- Placeholder yang tidak dikenal ditolak.
- DOCX boleh menggunakan sebagian variabel yang dikonfigurasi.
- Placeholder slot penandatangan aktif tetap wajib ditemukan.
- Dukungan pengulangan tabel, paragraf, header/footer, teks panjang, serta placeholder terpecah antar text run harus diuji pada versi PHPWord yang dipin.
- Struktur berbahaya atau tidak didukung harus ditolak dengan keterangan jelas.
- Publikasi memerlukan hasil uji generate dan konfirmasi pemeriksaan visual admin setelah membuka DOCX.
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
- Konfigurasi historis mencakup variabel, pemetaan tabel, klasifikasi, serta penandatangan.
- Draft dapat diarsipkan.
- Templat dapat dinonaktifkan tanpa menghapus riwayat.
- Tidak ada hapus permanen.
- Operasi penting dicatat dalam audit.
- Master klasifikasi, grup referensi, dan pegawai kandidat tidak dapat dihapus selama masih dirujuk, termasuk oleh versi historis atau arsip.

## 7. Konfigurasi terpusat

Rencanakan `config/letter-templates.php` sebagai sumber aturan teknis.

| Kelompok | Pengaturan |
| --- | --- |
| Upload | Ukuran maksimal DOCX sumber; default 10 MB |
| Storage | Disk privat dan path sumber, hasil uji, serta berkas sementara |
| Hasil uji | Masa simpan hasil generate sementara |
| Keamanan ZIP | Batas jumlah entry, total ukuran dekompresi, dan rasio kompresi |
| Variabel | Batas panjang nama placeholder, teks pendek, dan teks panjang |
| Generate | Batas waktu proses |

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
- Nilai batas selain ukuran upload 10 MB belum ditetapkan; jangan menganggapnya sebagai keputusan final.
- Pengaturan dokumen fitur Surat akan dibahas pada fitur tersebut.

## 8. Halaman daftar dan kelola

- Kolom daftar: Nama, Klasifikasi, Versi Aktif, Status Aktif/Nonaktif, penanda Ada Draft, dan Aksi.
- Pencarian berdasarkan nama templat serta kode/nama klasifikasi.
- Urutan default nama A–Z.
- Filter status dan klasifikasi, masing-masing pilihan tunggal.
- Paginasi mengikuti pola repository.
- Halaman kelola memuat empat tahap pengelolaan serta timeline versi.

## 9. Ketentuan implementasi

| Bagian | Keputusan |
| --- | --- |
| Model / domain | `LetterTemplate` / `MasterData` |
| URL | `/letter-templates` |
| Binding / alias route | `letterTemplate` menggunakan UUID / `lt` |
| Nama halaman | Templat Surat, Tambah Templat Surat, Ubah Templat Surat |
| Menu / pageId | UUID existing `a3063dc3-7414-4385-bf3f-0cf0742e3980` |
| Database aplikasi / test | PostgreSQL 18 / SQLite |
| Acuan implementasi | JobTitle dan `FEATURE_WORKFLOW.md`; kontrak backend mengikuti `LARAVEL_PATTERNS.md` |
| Seeder | Tidak diperlukan |
| Changelog | Ditambahkan dalam bahasa Indonesia saat fitur diimplementasikan |
| Penyimpanan konfigurasi versi | Tabel relasional terpisah, bukan JSON untuk variabel, kolom, slot, dan kandidat |
| PHPWord | Sudah terpasang; ikuti pin `dev-master#91a1d0d978ff2cff382134c5d0f80c4a03dffd2e` pada `composer.json` |

Alokasi nomor, pembuatan surat sebenarnya, revisi surat, dan penyerahan kepada pemohon belum masuk pekerjaan ini. Rincian teknis yang belum diputuskan tetap harus dirinci sebelum implementasi terkait.
