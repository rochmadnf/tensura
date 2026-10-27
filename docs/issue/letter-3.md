# Penyederhanaan preview dokumen surat

Status: selesai diimplementasikan pada 27 Oktober 2026 (v0.3.0).

## Masalah dan keputusan

Pengguna menemukan banyak ketidaksesuaian pada preview DOCX. Percobaan beberapa library belum memenuhi harapan. Sebelum perubahan, aplikasi menggunakan `docx-preview` untuk menyusun tampilan dokumen di browser, sehingga tampilan ini tidak dapat dijadikan pemeriksaan tata letak Word yang andal.

Jika tidak tersedia integrasi yang layak, pengguna memilih menghapus preview DOCX. Tindakan dokumen menjadi unduh DOCX langsung serta preview/unduh PDF. Pemeriksaan visual dalam aplikasi dilakukan melalui PDF; DOCX dapat diperiksa pada aplikasi Word setelah diunduh.

## Evaluasi integrasi

- [Microsoft 365 untuk web](https://learn.microsoft.com/en-us/microsoft-365/cloud-storage-partner-program/online/) memerlukan keanggotaan Cloud Storage Partner Program dan implementasi WOPI. Program ditujukan bagi penyedia penyimpanan cloud, bukan pelanggan Microsoft 365 langsung. Integrasi ini tidak sesuai sebagai penggantian sederhana viewer surat aplikasi ini.
- [Google Drive files](https://developers.google.com/workspace/drive/api/reference/rest/v3/files) menyediakan `webViewLink` untuk membuka file dalam viewer/editor Google. Ini merupakan alur file yang disimpan di Drive dan memerlukan pengelolaan akses terpisah; bukan renderer lokal untuk endpoint dokumen privat aplikasi. Tidak ada integrasi Google atau pengunggahan dokumen yang dilakukan.
- Keputusan implementasi: gunakan konversi PDF lokal yang sudah tersedia. Tidak menambahkan layanan eksternal, akun layanan, atau biaya integrasi. Kesamaan PDF dengan Word tetap bergantung pada converter dan ketersediaan font, sehingga hasil harus diperiksa.

## Implementasi dan penerimaan

- Hapus viewer DOCX dari hasil uji template, draft, dokumen resmi, dan historis. Pertahankan unduhan DOCX beserta otorisasi dan konfirmasi surat batal.
- Sediakan preview/unduh PDF dari DOCX hasil proses yang sama, dengan penanganan proses gagal atau hasil kedaluwarsa.
- Konfirmasi preview draft untuk penerbitan hanya menggunakan PDF terbaru dan akun yang memeriksa; bukti DOCX lama tidak dapat dipakai untuk penerbitan baru.
- Hasil uji template tetap dapat diunduh dan diperiksa di Word; sediakan PDF untuk pemeriksaan dalam aplikasi.
- Pertahankan fingerprint, pemeriksaan perubahan sumber/font, penanda draft, serta pembatasan ukuran.
- Uji akses, unduhan, PDF, bukti pemeriksaan, kegagalan konversi, perubahan sumber, dan regresi penerbitan.

## Hasil implementasi dan verifikasi

- Viewer DOCX, kode pemuatan font browser khusus viewer, dan dependensi `docx-preview` dihapus. DOCX draft, resmi, dan historis dikirim sebagai unduhan. URL Word lama tetap didukung sebagai alias unduhan, termasuk pemeriksaan konfirmasi surat batal.
- Pemeriksaan draft dalam aplikasi hanya menerima PDF. Endpoint rendered/confirm menolak DOCX, dan penerbitan menolak bukti DOCX lama meskipun sebelumnya sudah dikonfirmasi.
- Preview dan unduhan PDF hasil uji template memakai konverter yang sama, memeriksa token hasil uji sebelum/sesudah konversi, menambahkan watermark DRAFT, memeriksa profil font serta batas ukuran, dan membersihkan direktori kerja. PDF dibuat atas permintaan; DOCX tetap tersedia saat konversi gagal.
- Admin tetap dapat menyatakan pemeriksaan visual hasil uji template setelah membukanya melalui PDF atau DOCX unduhan. Penerbitan surat membutuhkan konfirmasi PDF draft terbaru oleh akun penerbit.
- Tes mencakup unduhan privat, penolakan bukti DOCX lama, pemeriksaan PDF per akun, pembatasan sumber usang, kegagalan konversi, perubahan hasil uji saat konversi, watermark draft, dokumen resmi, dan regresi siklus surat.
- Konversi LibreOffice nyata dan hasil PDF dari template pengguna diperiksa. Pemeriksaan browser pada desktop/tablet/ponsel tidak menemukan tombol Preview DOCX atau error JavaScript.
- Profil renderer diperbarui. Hasil uji/preview yang menjadi usang perlu dibuat dan diperiksa ulang; dokumen resmi tersimpan tetap menggunakan berkas aslinya.
- Catatan rilis tersedia di `resources/markdown/releases/0.3.0.md`. Dokumen perencanaan/tahapan lama yang menyebut viewer DOCX merupakan riwayat sebelum keputusan issue ini.
