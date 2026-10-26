# Tahap 7 — Penerbitan dan Unduh Word

Status implementasi: selesai, terverifikasi nonvisual. Status uji mandiri pengguna: belum dijalankan.

Acuan: [rencana teknis](../../planning/letter/teknis.md). Gunakan database uji dengan klasifikasi/periode yang disiapkan dan draft yang telah diperiksa. Pengguna mengizinkan dropdown tetap dibuat meskipun viewer belum dinilai; semua pengujian tampilan/cetak di bawah tetap milik pengguna.

## Yang sudah dapat dilakukan setelah tahap selesai

Jalur: **Surat → detail draft → Generate preview → Preview DOCX → Saya sudah memeriksa dokumen → Terbitkan surat**. Dialog menampilkan tanggal/periode; jika draft melewati bulan, centang pilihan mempertahankan tanggal/periode tersebut. Untuk mengganti tanggal, batalkan dialog, simpan perubahan, dan periksa preview baru.

Setelah terbit, detail menampilkan nomor/versi/penerbit, preview resmi yang bisa dicetak, serta satu dropdown **Unduh → Word (.docx)**. PDF diberi keterangan belum tersedia sampai tahap 8. **Penomoran Surat → detail → Lihat pemakaian** menampilkan surat yang memakai nomor pada setiap periode.

Migration sudah diterapkan pada database aplikasi lokal. Jalankan ulang `composer run dev` agar worker memuat kode penerbitan terbaru. Jika worker dijalankan terpisah, restart `php artisan queue:work letters --queue=letters --tries=3 --timeout=90`; jalankan scheduler untuk recovery. Karena fingerprint kini mengikat kode klasifikasi, preview yang dibuat sebelum pembaruan ini harus digenerate dan dikonfirmasi ulang. Isian draft tidak dihapus.

## Checklist mandiri

- [ ] Terbitkan tanpa pemeriksaan atau setelah isi berubah. **Harapan:** ditahan dengan petunjuk pemeriksaan terbaru.
- [ ] Terbitkan pada posisi otomatis `23.44`. **Harapan:** mendapat `445/23.45/PKM-KMJ/X/2026` untuk Oktober; posisi periode menjadi `23.45`.
- [ ] Gunakan beberapa templat dengan klasifikasi sama. **Harapan:** berbagi urutan; klasifikasi/periode lain tidak ikut bertambah.
- [ ] Uji posisi `01.99` pada data uji. **Harapan:** berikutnya `02.01`.
- [ ] Saat otomatis berikutnya `01.10`, pilih manual `01.20`. **Harapan:** surat mendapat `01.20`, berikutnya otomatis tetap `01.10` dan kelak melewati nomor manual tersebut.
- [ ] Pilih nomor manual yang sedang dipakai. **Harapan:** ditolak dan disarankan nomor tersedia sesudahnya dalam periode yang sama, tanpa mengganti pilihan diam-diam.
- [ ] Pilih tanggal/periode berbeda atau gunakan draft yang melewati pergantian bulan. **Harapan:** minta penyelarasan/pilihan periode dan pemeriksaan ulang bila tanggal berubah.
- [ ] Klik Terbitkan berulang, refresh saat selesai, atau coba dari dua operator. **Harapan:** satu hasil terbit, tidak mengambil nomor kedua atau menimpa perubahan baru.
- [ ] Buka dropdown Unduh dan pilih Word. **Harapan:** satu tombol dropdown, Word resmi dapat dibuka dan nomor/tanggal/data sesuai; PDF belum aktif dengan keterangan jelas.
- [ ] Buka **Lihat pemakaian** pada satu periode. **Harapan:** hanya klaim pada periode/klasifikasi itu yang tampil, pagination berfungsi, dan tautan membuka surat yang benar.
- [ ] Coba mengubah kode klasifikasi yang sudah dipakai surat. **Harapan:** kode dilindungi dan alasannya dijelaskan; nama klasifikasi masih bisa diperbarui.
- [ ] Operator B masih mengedit ketika operator A menerbitkan surat. Simpan dari B. **Harapan:** ditolak, editor/input lama tetap terlihat untuk disalin, dan Batal/Muat versi terbaru membuka status resmi.
- [ ] Buka Word resmi setelah preview kedaluwarsa atau font/master berubah. **Harapan:** unduhan tetap memakai berkas resmi yang sama tanpa penomoran/generate ulang.
- [ ] Periksa periode dan riwayat setelah penerbitan gagal pada lingkungan uji. **Harapan:** nomor tidak habis dan draft dapat dilanjutkan.

## Hasil pengguna

Tanggal, nomor sampel, dan temuan: belum diisi.

## Bukti otomatis dan batas tahap

Sebanyak **189 test PHPUnit/1.743 assertion** pada suite terkait lulus, disertai regresi **3 test SSE** dan pengulangan kasus recovery penerbitan. Seluruh **372 test frontend**, Pint, lint global, pemeriksaan tipe standar, dan build produksi lulus. Build masih memberi peringatan ukuran chunk aplikasi yang sudah ada. Tidak ada browser/inspeksi visual/cetak oleh agent.

Pengujian mencakup alokasi otomatis/manual, rollover, kapasitas maksimum, periode baru, pemindahan angka lama, riwayat manual yang dilepas, indeks unik posisi/surat aktif, klasifikasi bersama lintas templat, bukti per aktor, perubahan sumber/font/templat/revision/bulan, idempotensi dan dua operator, konflik saat render, batas tiga render, recovery, rollback audit/nomor/berkas, scope unduhan, integritas Word, proteksi master, daftar pemakaian, dan filter surat terbit. Subprocess Word resmi diuji sesungguhnya tanpa inspeksi visual dokumen.

Frontend menguji satu dropdown dengan PDF nonaktif, konfirmasi periode setelah pergantian bulan, request dengan respons terputus, serta kelangsungan editor setelah surat diterbitkan operator lain. Uji konkurensi antarkoneksi menyeluruh tetap tahap 10; simulasi konflik dan constraint database di tahap ini tidak diklaim menggantikannya. PDF, revisi, pembatalan, serta pemulihan surat belum tersedia pada tahap ini.
