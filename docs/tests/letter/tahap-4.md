# Tahap 4 — Pemeriksaan dan Unggah Font

Status implementasi: direncanakan. Status uji mandiri pengguna: belum dijalankan.

Acuan: [rencana teknis](../../planning/letter/teknis.md). Gunakan font uji yang boleh dipasang/disajikan oleh aplikasi. Pemeriksaan visual milik pengguna.

## Yang sudah dapat dilakukan setelah tahap selesai

Kartu DOCX Sumber menampilkan Font Dokumen dan peringatan font yang kurang. Tombol Font tersedia membuka daftar/pencarian varian dan unggah `.ttf`/`.otf`.

## Checklist mandiri

- [ ] Validasi DOCX dengan font tersedia. **Harapan:** keluarga dan varian yang digunakan tercatat sebagai tersedia.
- [ ] Validasi DOCX dengan font yang kurang, termasuk dari style/tema dan header/footer. **Harapan:** nama yang kurang tampil dengan aksi Unggah font; bukan galat struktur.
- [ ] Lanjutkan generate uji dan publikasi templat tanpa melengkapi font. **Harapan:** tetap diperbolehkan bila syarat lain terpenuhi; warning tidak disembunyikan.
- [ ] Buka Font tersedia, cari keluarga dan varian. **Harapan:** hasil mudah ditemukan tanpa menampilkan path privat server.
- [ ] Unggah font valid. **Harapan:** status baru tersedia setelah font terbaca proses konversi; daftar dan pemeriksaan terkait dapat diperbarui.
- [ ] Unggah berkas sama lagi, file palsu/rusak, file terlalu besar, dan font berbeda dengan keluarga/varian sama. **Harapan:** duplikat aman; yang tidak valid/konflik dijelaskan tanpa overwrite diam-diam.
- [ ] Buka viewer pada komputer yang tidak memasang font tersebut. **Harapan:** font yang disajikan aplikasi dapat dimuat; jika hanya tersedia di server dan tidak tersedia bagi browser, peringatannya jujur.
- [ ] Lengkapi font yang relevan setelah preview dibuat. **Harapan:** preview/pemeriksaan draft perlu diperbarui; font tidak relevan tidak membatalkan semua draft.
- [ ] Bandingkan Word sebelum/sesudah pengelolaan font. **Harapan:** pengaturan font dokumen tidak ditulis ulang oleh aplikasi.

## Hasil pengguna

Tanggal, keluarga/varian font uji, dan temuan: belum diisi.
