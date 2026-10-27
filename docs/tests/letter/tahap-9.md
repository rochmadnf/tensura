# Tahap 9 — Revisi, Pembatalan, Pemulihan, dan Riwayat

Status implementasi: selesai, terverifikasi nonvisual. Status uji mandiri pengguna: belum dijalankan.

Acuan: [rencana teknis](../../planning/letter/teknis.md). Siapkan surat A terbit dan surat B sebagai pemakai ulang nomor pada data uji.

## Yang sudah dapat dilakukan setelah tahap selesai

Jalankan seluruh siklus Surat, buka versi historis, batalkan, gunakan ulang nomor, dan pulihkan melalui draft. Timeline menjelaskan pelaku/waktu/alasan serta perubahan nomor.

Jalur: **Surat → detail surat Terbit → Buat revisi**, atau **Batalkan surat → isi alasan → konfirmasi → Pulihkan surat**. Isian dan alasan revisi/pemulihan disimpan melalui form draft, lalu generate/konfirmasi preview sebelum menerbitkan. Tombol **Buang draft** mempertahankan dokumen resmi. Bagian **Versi Dokumen** menyediakan dokumen historis beserta Word/PDF per versi.

Migration tahap 9 sudah diterapkan pada database aplikasi lokal. Restart `composer run dev` atau worker yang terpisah. Preview sebelum kontrak pemeriksaan 3 harus digenerate dan dikonfirmasi ulang; isian tetap tersimpan. Status token pada dialog mencegah konfirmasi lama mengenai versi/draft yang sudah berubah.

## Checklist mandiri

- [ ] Buat revisi surat A, lalu minta revisi lagi. **Harapan:** melanjutkan satu draft yang sama; versi resmi sebelumnya tetap tersedia.
- [ ] Revisi isi/penandatangan dan coba ubah nomor/tanggal. **Harapan:** isi dapat diperbaiki, nomor/tanggal tetap, alasan wajib saat menerbitkan.
- [ ] Aktifkan versi templat baru sebelum revisi selesai. **Harapan:** wajib penyesuaian eksplisit dan pemeriksaan ulang; tidak memakai versi lama diam-diam.
- [ ] Terbitkan revisi. **Harapan:** nomor tetap, versi baru resmi, versi sebelumnya dapat dipreview/diunduh melalui riwayat dengan penanda historis.
- [ ] Buang draft revisi. **Harapan:** versi resmi dan dokumen historis tidak ikut terhapus.
- [ ] Batalkan surat A ketika ada draft revisi. **Harapan:** alasan wajib, konfirmasi menjelaskan draft dibuang, surat menjadi Batal dan nomor tersedia kembali.
- [ ] Pakai nomor A secara manual pada surat B. **Harapan:** nomor pada detail A merah dengan tooltip/tautan menuju B; nomor B normal; urutan otomatis tidak mundur.
- [ ] Batalkan B. **Harapan:** A menampilkan nomor tersedia kembali; timeline tetap mencatat pemakaian B.
- [ ] Pulihkan A saat nomor tersedia. **Harapan:** melalui draft, alasan wajib, tanggal asal tetap, templat terbaru, nomor lama diperiksa lagi saat terbit.
- [ ] Ulangi pemulihan ketika nomor lama dipakai surat lain. **Harapan:** saran nomor pengganti masih dalam periode tanggal asal, dipilih eksplisit; nomor/dokumen lama tetap tercatat.
- [ ] Unduh dokumen surat batal melalui riwayat. **Harapan:** konfirmasi status Batal muncul sebelum unduhan.
- [ ] Buka/tutup detail dan unduh beberapa kali. **Harapan:** timeline tidak bertambah oleh aktivitas baca/unduh; revisi/batal/pulih tetap memiliki alasan dan pelaku.

## Hasil pengguna

Tanggal, rangkaian surat/nomor uji, dan temuan: belum diisi.

## Batas pengujian

Pengguna melaporkan alur fungsional di Linux berjalan baik dan sebagian pemeriksaan visual telah dilakukan. Checkbox di atas tetap menunggu rincian item yang sudah diuji. Agent menjalankan pemeriksaan nonvisual dan tidak menjalankan browser, screenshot, atau cetak manual.

## Bukti otomatis

102 test PHPUnit/1.269 assertion untuk lifecycle, draft, preview, penerbitan, PDF, font, serta alokasi nomor lulus. Test mencakup templat terbaru dengan klasifikasi berbeda, nomor/tanggal revisi tetap, alasan, draft bersama, editor usang, pembuangan draft, pembatalan pekerjaan tertunda, klaim pemulihan, nomor dipakai ulang, token modal usang, konfirmasi unduhan batal, dan riwayat baca tanpa audit tambahan.

Suite frontend penuh menghasilkan 397 lulus/1 gagal di luar Surat: judul impor/ekspor Pegawai berbeda antara “Sesuai Filter” dan “Sesuai Pencarian/Filter”. 26 test frontend terarah lulus. Test Surat menguji token dialog yang dibekukan saat dibuka, aksi pemulihan/pembuangan, konfirmasi unduhan batal, serta regresi pemeriksaan PDF/DOCX dan penerbitan. Pint, lint, pemeriksaan tipe, dan build produksi lulus. Uji konkurensi proses terpisah dilengkapi pada tahap 10.
