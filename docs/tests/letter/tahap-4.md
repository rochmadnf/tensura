# Tahap 4 — Pemeriksaan dan Unggah Font

Status implementasi: selesai, terverifikasi nonvisual. Status uji mandiri pengguna: belum dijalankan.

Acuan: [rencana teknis](../../planning/letter/teknis.md). Gunakan font uji yang boleh dipasang/disajikan oleh aplikasi. Pemeriksaan visual milik pengguna.

## Yang sudah dapat dilakukan setelah tahap selesai

Kartu DOCX Sumber menampilkan Font Dokumen dan peringatan font yang kurang. Tombol Font tersedia membuka daftar/pencarian varian dan unggah `.ttf`/`.otf`.

Jalur: **Templat Surat → ubah templat/versi → DOCX Sumber → Validasi DOCX / Font tersedia**. Daftar di Font Dokumen mengikuti DOCX tervalidasi; daftar di viewer mengikuti teks hasil generate. Setelah menambah font yang relevan, generate dan periksa ulang hasil uji.

Unggahan awal mendukung satu varian font statis `.ttf`/`.otf` per berkas, maksimal 10 MB. Font variabel/koleksi belum didukung. Jika font sudah tersedia di server dan perlu dimuat dari aplikasi ke browser, unggah berkas font yang sama melalui **Font tersedia**.

## Checklist mandiri

- [ ] Validasi DOCX dengan font tersedia. **Harapan:** keluarga dan varian yang digunakan tercatat sebagai tersedia.
- [ ] Validasi DOCX dengan font yang kurang, termasuk dari style/tema dan header/footer. **Harapan:** nama yang kurang tampil dengan aksi Unggah font; bukan galat struktur.
- [ ] Lanjutkan generate uji dan publikasi templat tanpa melengkapi font. **Harapan:** tetap diperbolehkan bila syarat lain terpenuhi; warning tidak disembunyikan.
- [ ] Buka Font tersedia, cari keluarga dan varian. **Harapan:** hasil mudah ditemukan tanpa menampilkan path privat server.
- [ ] Unggah font valid. **Harapan:** status baru tersedia setelah font terbaca proses konversi; daftar dan pemeriksaan terkait dapat diperbarui.
- [ ] Unggah berkas sama lagi, file palsu/rusak, file terlalu besar, dan font berbeda dengan keluarga/varian sama. **Harapan:** duplikat aman; yang tidak valid/konflik dijelaskan tanpa overwrite diam-diam.
- [ ] Buka viewer pada komputer yang tidak memasang font tersebut. **Harapan:** font yang disajikan aplikasi dapat dimuat; jika hanya tersedia di server dan tidak tersedia bagi browser, peringatannya jujur.
- [ ] Lengkapi font yang relevan setelah preview dibuat. **Harapan:** preview/pemeriksaan draft perlu diperbarui; font tidak relevan tidak membatalkan semua draft.
- [ ] Uji DOCX dengan tabel bersyarat, bullet otomatis, atau teks DrawingML. **Harapan:** keterbatasan pemeriksaan ditampilkan; tidak diklaim sudah teridentifikasi seluruhnya.
- [ ] Isi data contoh dengan aksara lain (misalnya Arab/Jepang). **Harapan:** kebutuhan font viewer mengikuti teks hasil generate dan tema/bahasa yang dapat diidentifikasi.
- [ ] Bandingkan Word sebelum/sesudah pengelolaan font. **Harapan:** pengaturan font dokumen tidak ditulis ulang oleh aplikasi.

## Hasil pengguna

Tanggal, keluarga/varian font uji, dan temuan: belum diisi.

## Bukti implementasi

Pengujian mencakup upload dengan Fontconfig asli pada storage pengujian terisolasi, duplikat/konflik/rusak/ukuran, kegagalan cache/inventory, autentikasi dan berkas privat, style/tema/header/footer/script hasil render, warning nonblocking hingga publikasi, fingerprint relevan, dan pemuatan font browser nonvisual. Profile konversi PDF telah disiapkan; fitur konversi/unduh PDF baru pada tahap 8. Agent belum menjalankan checklist visual pengguna.

Hasil otomatis: 225 test PHPUnit/1.685 assertion pada regresi terkait, 12 test font pada pemeriksaan akhir, 65 test frontend, Pint, lint, TypeScript, dan build lulus. Skema `document_fonts` aktif dikonfirmasi memiliki constraint unik UUID/hash/keluarga-varian dan relasi pengunggah.
