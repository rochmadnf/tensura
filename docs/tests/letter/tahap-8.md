# Tahap 8 — PDF Atas Permintaan dan EmbedPDF

Status implementasi: direncanakan. Status uji mandiri pengguna: belum dijalankan.

Acuan: [rencana teknis](../../planning/letter/teknis.md). Prasyarat: Linux, LibreOffice, worker, dan font fallback siap. Tidak memakai viewer cloud eksternal.

## Yang sudah dapat dilakukan setelah tahap selesai

Lihat/Cetak PDF pada preview serta pilih Word atau PDF melalui satu dropdown Unduh setelah terbit. PDF dibuat ketika diminta, bukan setiap review DOCX.

## Checklist mandiri

- [ ] Review DOCX tanpa meminta PDF. **Harapan:** tidak ada konversi PDF otomatis atau indikator konversi yang tidak diminta.
- [ ] Pilih Lihat/Cetak PDF untuk draft. **Harapan:** status proses terlihat, hasil menggunakan `XX.XX`, lalu viewer PDF hanya-baca terbuka.
- [ ] Pilih Unduh → PDF pada surat terbit. **Harapan:** PDF resmi bernomor tersedia setelah konversi; menu Word tetap dapat dipakai.
- [ ] Minta PDF yang sama berulang dan dari dua tab/operator. **Harapan:** menggunakan hasil/job yang sama; tidak menambah nomor atau versi surat.
- [ ] Pindah halaman saat konversi. **Harapan:** notifikasi SSE menyediakan aksi membuka hasil, tanpa mengunduh otomatis di halaman lain.
- [ ] Gunakan font yang kurang. **Harapan:** PDF tetap diproses dengan pengganti dan peringatan nama font, bukan galat publikasi surat.
- [ ] Simulasikan converter gagal pada lingkungan uji. **Harapan:** surat tetap Terbit, Word tetap bisa diunduh, PDF memiliki aksi Coba Lagi tanpa nomor baru.
- [ ] Bandingkan Word sebelum dan sesudah konversi PDF. **Harapan:** pengaturan font/isi Word resmi tetap sama; hanya hasil PDF memakai substitusi.
- [ ] Coba zoom, halaman, Cetak, keyboard, dan layar sempit pada EmbedPDF. **Harapan:** kontrol sesuai aplikasi, tanpa anotasi/editor; hasil cetak lengkap.
- [ ] Pasang font baru setelah PDF resmi tersimpan. **Harapan:** PDF historis yang ada tidak berubah diam-diam; draft terkait dapat dibuat ulang memakai font baru.

## Hasil pengguna

Tanggal, sampel, perbandingan Word/PDF/cetak, dan temuan: belum diisi.
