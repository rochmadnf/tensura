# Tahap 10 — Integrasi Akhir dan Transisi Counter

Status implementasi: direncanakan. Status uji mandiri pengguna: belum dijalankan.

Acuan: [rencana teknis](../../planning/letter/teknis.md). Agent tetap menjalankan pengujian otomatis/integrasi/konkurensi nonvisual; daftar ini untuk uji mandiri pengguna pada lingkungan uji.

## Yang sudah dapat dilakukan setelah tahap selesai

Seluruh siklus Surat terintegrasi, cleanup/recovery berjalan, dan counter operasional hanya berada di periode. Counter lama baru dihapus setelah seluruh angka existing selesai dipindahkan.

## Checklist mandiri

- [ ] Periksa detail setiap klasifikasi existing. **Harapan:** angka awal terikat periode yang benar sebelum transisi counter selesai.
- [ ] Pastikan persiapan satu klasifikasi belum lengkap pada lingkungan uji. **Harapan:** penghapusan counter lama tertahan dengan petunjuk, tanpa kehilangan angka.
- [ ] Setelah transisi lengkap, buat/ubah klasifikasi. **Harapan:** tidak ada lagi input counter global; angka hanya dikelola/dibaca pada periode.
- [ ] Jalankan alur tambah → preview → periksa → terbit → unduh Word/PDF → revisi → batal → pulih. **Harapan:** status, nomor, dokumen, alasan, dan timeline konsisten.
- [ ] Jalankan dua penerbitan pada klasifikasi/periode sama dari dua operator. **Harapan:** tidak ada nomor ganda dan tidak menimpa draft orang lain.
- [ ] Putus koneksi/restart worker pada lingkungan uji saat pekerjaan berjalan. **Harapan:** recovery/retry tidak membuat versi atau nomor kedua; hasil akhir dapat ditemukan kembali.
- [ ] Biarkan preview draft kedaluwarsa. **Harapan:** isian tetap ada, preview dapat dibuat ulang; Word/PDF resmi dan historis tidak hilang.
- [ ] Buka surat terbit setelah master pegawai/referensi atau font berubah. **Harapan:** dokumen historis tersimpan tetap sama.
- [ ] Periksa daftar, filter, pagination, Back/Forward, editor, tooltip nomor, dropdown Unduh, notifikasi SSE, keyboard, dan layar sempit. **Harapan:** mengikuti pola UI bersama dan tidak mengganggu input saat job lain selesai.
- [ ] Periksa bahwa hasil tahap 1 yang belum layak tidak dilabeli seolah-olah sudah lolos. **Harapan:** keputusan viewer dan keterbatasan yang disetujui tercatat dengan jelas.

## Rekap penerimaan pengguna

- Tanggal/lingkungan: belum diisi.
- Tahap yang sudah diuji: belum diisi.
- Temuan yang menghalangi penggunaan: belum diisi.
- Batasan tampilan/cetak yang diterima: belum diisi.
- Keputusan penerimaan akhir: belum diputuskan.
