# Blok berulang dan panduan penggunaannya untuk admin

Status: selesai diimplementasikan pada 27 Oktober 2026 (v0.3.0).

## Keputusan tambahan

- `masalah/template.docx` menjadi dasar pengujian multiple. Single combobox sudah diuji pengguna dan hasilnya sesuai.
- Penanda penutup boleh berada pada baris kosong setelah Jabatan. Baris tersebut menjadi pemisah antarorang, mengikuti tinggi baris di Word.
- Jika baris penutup hanya memuat penanda dan spasi, pertahankan antarorang dan hapus pada pengulangan terakhir. Jika ada teks, placeholder data, gambar, atau isi lain, baris tetap dipertahankan pada semua pengulangan.

## Latar belakang

Surat tugas dapat menunjuk lebih dari satu orang. Format identitas harus tetap tersusun ke bawah seperti bagian pemohon pada `masalah/surat-01a17bde-caf5-7333-83f8-5e88acafe3ba-v1.docx`: Nama, NIP, Pangkat/Gol, dan Jabatan.

Sebelum perubahan ini, renderer hanya mendukung pengulangan satu baris tabel. Untuk susunan vertikal, admin perlu dapat menandai awal dan akhir blok agar seluruh identitas satu orang diulang sebagai satu kesatuan. Template akan memakai tabel tanpa garis untuk menjaga posisi label, titik dua, dan nilai saat dikonversi ke PDF.

## Perilaku pengulangan

Dukung dua susunan template:

- **Horizontal:** satu orang menempati satu baris. Semua placeholder kelompok berada pada baris yang sama; sistem menggandakan baris tersebut. Penanda blok tidak diperlukan.
- **Vertikal:** satu orang menempati beberapa baris. Admin memasang `${blok:pemohon}` pada baris awal dan `${/blok:pemohon}` pada baris akhir; sistem menggandakan seluruh rentang baris, termasuk isi di antaranya.

Nama `pemohon` mengikuti nama variabel yang dikonfigurasi admin. Nomor, label, dan jarak tetap mengikuti desain template.

## Panduan yang terlihat oleh admin

Saat **“Pilihan lebih dari satu”** diaktifkan, tampilkan bagian **“Cara menyusun tabel berulang di DOCX”** tepat di dekat konfigurasi atributnya.

### Panduan horizontal

> Gunakan satu baris contoh untuk satu orang. Letakkan semua variabel pada baris yang sama. Baris tersebut akan diulang sesuai jumlah pilihan.

| Nama | NIP | Jabatan |
|---|---|---|
| `${pemohon_nama}` | `${pemohon_nip}` | `${pemohon_jabatan}` |

### Panduan vertikal

> Gunakan beberapa baris untuk satu orang. Pasang penanda awal sebelum isi baris pertama dan penanda akhir setelah isi baris terakhir. Seluruh baris dalam blok akan diulang sebagai satu kesatuan.

| Label | Pemisah | Nilai |
|---|---|---|
| `${blok:pemohon}`Nama | : | `${pemohon_nama}` |
| NIP | : | `${pemohon_nip}` |
| Pangkat/Gol | : | `${pemohon_pangkat}` / `${pemohon_golongan}` |
| Jabatan | : | `${pemohon_jabatan}${/blok:pemohon}` |

### Ketentuan bantuan

- Contoh di aplikasi menggunakan nama variabel dan placeholder milik admin, bukan nama yang ditetapkan secara statis.
- Sediakan tombol **“Salin penanda awal”** dan **“Salin penanda akhir”**, menggunakan pola tombol salin yang sudah tersedia.
- Jelaskan bahwa penanda tidak muncul dalam hasil surat; nomor urut hanya muncul jika admin memasang placeholder kolom dengan atribut `row_number`.
- Petunjuk tampil sebagai bantuan tetap di konfigurasi, sehingga tidak bergantung pada notifikasi sementara.

## Implementasi dan contoh unduhan

- Tambahkan pembaca penanda bersama untuk validasi dan renderer. Gandakan seluruh baris dari penanda awal sampai akhir, lalu isi atribut dari snapshot orang yang sama.
- Penanda wajib berpasangan, berurutan, satu pasangan per kelompok, dan berada dalam satu tabel di isi surat. Tolak blok bersarang, bertumpang tindih, atau bercampur kelompok lain.
- Semua placeholder kelompok bertanda harus berada di dalam blok. Gabungan sel vertikal dan tabel bersarang tetap tidak didukung.
- Penanda boleh terpecah menjadi beberapa bagian format teks Word, tetapi tidak terpotong antarparagraf.
- Hapus penanda dari hasil. Pilihan opsional kosong menghapus seluruh blok; isi di luar blok dipertahankan. Hapus tabel hanya jika tidak memiliki baris.
- Pertahankan template horizontal lama dan batas ukuran hasil render. Hitung batas ukuran terhadap seluruh blok sebelum penggandaan.
- Gunakan renderer bersama untuk uji template, preview, dan penerbitan surat. Konversi PDF tetap menggunakan DOCX hasil tersebut.
- Sediakan **“Unduh contoh horizontal”** dan **“Unduh contoh vertikal”**. Setiap berkas memuat satu susunan yang valid berdasarkan konfigurasi tersimpan.
- Tambahkan parameter opsional `layout=horizontal|vertical` pada endpoint contoh yang ada; nilai bawaan `horizontal`, dan nilai lain ditolak.
- Tidak perlu perubahan formulir pemilihan pegawai, migrasi database, atau dependensi baru.

## Pengujian dan penerimaan

- Horizontal dan vertikal bekerja untuk 1, 2, dan 3 orang sesuai urutan pilihan. Setiap blok berisi atribut orang yang benar.
- Uji blok kosong, penanda tidak lengkap atau terbalik, pasangan ganda, blok bersarang atau bertumpang tindih, batas lintas tabel, serta pencampuran kelompok.
- Uji format teks Word yang memecah penanda, placeholder kelompok di luar blok, serta batas ukuran hasil render.
- Pastikan format tabel, baris kosong, nomor buatan admin, dan teks statis di dalam blok ikut digandakan.
- Pastikan penanda hilang dari hasil; judul, penandatangan, dan isi di luar blok tetap utuh.
- Pastikan bantuan dan tombol salin menggunakan nama konfigurasi yang benar.
- Kedua contoh unduhan lolos validasi dan dapat dirender; tautan lama tetap menghasilkan contoh horizontal.
- Pertahankan tes regresi template satu baris dan snapshot lama.
- Periksa DOCX dan PDF dengan jabatan panjang: titik dua sejajar, atribut tidak tercampur antarorang, dan penanda tidak tersisa. Perpindahan halaman mengikuti template.
- Jalankan tes terkait, pemeriksaan TypeScript untuk perubahan frontend, serta Pint untuk perubahan PHP.

## Asumsi dan penerapan

- Versi pertama mendukung blok dalam tabel tanpa garis, sesuai pilihan admin dalam pembahasan. Penanda pada paragraf biasa di luar tabel belum termasuk.
- Penomoran dan jarak antarblok tidak ditambahkan secara otomatis; admin menentukannya dalam template.
- Untuk penggunaan nyata, admin membuat versi template baru, mengunggah susunan vertikal, lalu memvalidasi dan memeriksa hasilnya sebelum menerbitkan versi tersebut.

## Hasil implementasi dan verifikasi

- Validasi dan renderer memakai pembaca penanda yang sama. Format snapshot lama tetap didukung tanpa perubahan database.
- Tabel horizontal lama tetap bekerja. Blok vertikal menggandakan seluruh baris dan format, menjaga urutan pilihan, menghapus blok opsional kosong, serta mempertahankan isi di luar blok.
- Baris penutup kosong dipertahankan hanya antarorang. Baris penutup yang berisi data atau objek tidak diperlakukan sebagai pemisah kosong.
- Bantuan tetap tampil pada konfigurasi multiple, baik saat melihat maupun mengedit. Nama kelompok dan placeholder mengikuti konfigurasi, dengan tombol salin serta contoh horizontal/vertikal.
- Endpoint contoh menerima `layout=horizontal|vertical`; URL lama tetap menghasilkan contoh horizontal.
- Pengujian memakai `masalah/template.docx` sebagai dasar, menambahkan penanda pada salinan sementara tanpa mengubah berkas asli. DOCX untuk 1, 2, dan 3 orang serta PDF tiga orang berhasil dibuat. Jabatan panjang membungkus pada kolom nilai; titik dua tetap sejajar. Jarak dan perpindahan halaman tetap mengikuti template.
- Tes mencakup penanda terpecah oleh format Word, pasangan salah/ganda, blok bersarang/bertumpang tindih, lintas tabel, header, tabel bersarang, gabungan sel vertikal, pencampuran kelompok, placeholder di luar blok, batas ukuran, dan kompatibilitas snapshot lama.
- Panduan diperiksa pada browser dengan lebar 1440, 768, dan 390 piksel. Teks bantuan membungkus pada ponsel; tabel contoh dapat digeser untuk melihat kolom lain.
