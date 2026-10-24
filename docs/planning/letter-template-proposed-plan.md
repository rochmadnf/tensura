# Fondasi Database Templat Surat MVP 1

Status: usulan fondasi database hasil diskusi terakhir; belum diimplementasikan. Ini belum merupakan spesifikasi implementasi lengkap seluruh fitur.

Acuan proses bisnis: [letter-template-management-probis.md](letter-template-management-probis.md). Pilihan tabel relasional dan pemblokiran penghapusan master yang dirujuk, termasuk oleh versi historis atau arsip, sudah disepakati. Rincian kolom dan constraint di bawah merupakan rancangan teknis terakhir.

## 1. Ringkasan

Gunakan tujuh tabel relasional. Identitas templat tetap satu; DOCX dan seluruh konfigurasi berada pada versi masing-masing. Migration menggunakan Schema Builder Laravel, kompatibel dengan PostgreSQL 18 dan SQLite untuk test.

## 2. Tabel dan relasi

Semua entitas mengikuti pola repository: primary key numerik, UUID publik berindeks, dan timestamps.

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

## 3. Constraint dan perilaku penyimpanan

- Unique: nama templat; pasangan templat–nomor versi; nama variabel per versi; urutan slot per versi; pasangan slot–pegawai; nama placeholder kolom per variabel.
- Foreign key ke templat, versi, klasifikasi, grup referensi, dan pegawai menggunakan `restrictOnDelete`. Tidak ada cascade yang menghapus riwayat.
- Identitas pelaku audit tetap disimpan sebagai salinan nama/identitas; akun pelaku yang dihapus tidak menghapus audit.
- Validasi aplikasi memastikan seluruh placeholder variabel, kolom tabel, dan penandatangan tidak bertabrakan dalam satu versi.
- Pembuatan draft dan publikasi mengunci baris templat dalam transaksi agar maksimal satu draft berjalan dan satu versi terbit aktif.
- Versi terbit dengan `first_used_at` kosong dapat dikembalikan menjadi draft; pointer aktif dan bukti pemeriksaan dibatalkan.
- Jika `first_used_at` terisi, perubahan menyalin konfigurasi ke versi baru. Sumber DOCX versi asal tidak ditimpa.
- Fitur Surat nanti wajib mengisi `first_used_at` saat penggunaan pertama dalam transaksi yang berkoordinasi dengan penguncian versi.
- Perubahan draft mempertahankan DOCX, tetapi membatalkan bukti validasi/uji/pemeriksaan terkait.

## 4. Urutan pekerjaan

1. Buat migration dan model sesuai ketergantungan tabel; tambahkan foreign key versi aktif setelah tabel versi tersedia.
2. Tambahkan enum, relasi Eloquent, factory, serta validasi konfigurasi relasional.
3. Implementasikan penyimpanan empat tahap dan operasi versi melalui transaksi; lanjutkan integrasi DOCX, halaman, config, dan audit.
4. Gunakan PHPWord pada commit yang sudah dipin di `composer.json`: `91a1d0d978ff2cff382134c5d0f80c4a03dffd2e`.

## 5. Pengujian

Test SQLite mencakup:

- Draft awal hanya memerlukan nama dan klasifikasi.
- Keunikan, relasi, penolakan penghapusan master, dan isolasi konfigurasi antarversi.
- Satu draft berjalan; publikasi hanya setelah validasi, uji, dan pemeriksaan.
- Edit versi belum digunakan; penyalinan versi yang sudah digunakan.
- Perubahan konfigurasi membatalkan hasil uji tanpa menghapus DOCX asal.
- Pembentukan tabel berulang dan perlindungan hasil DOCX historis.

Test SQLite tidak membuktikan perilaku row lock atau konkurensi PostgreSQL. Batas pembuktian tersebut harus dicatat saat verifikasi.

## 6. Kelanjutan perencanaan

Rincian config dan nilai batasnya, endpoint, formulir, validasi DOCX, serta kontrak implementasi lengkap akan dirinci berdasarkan fondasi ini sebelum implementasi terkait. Jangan menganggap rincian yang belum ditetapkan sebagai keputusan final.

Penyimpanan dokumen ini tidak menjalankan migration, mengubah kode aplikasi, atau mengubah dependency.
