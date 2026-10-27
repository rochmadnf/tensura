# Tahap 8 — PDF Atas Permintaan dan EmbedPDF

Status implementasi: selesai, terverifikasi nonvisual. Status uji mandiri pengguna: belum dijalankan.

Acuan: [rencana teknis](../../planning/letter/teknis.md). Prasyarat: Linux, LibreOffice, worker, dan font fallback siap. Tidak memakai viewer cloud eksternal.

## Yang sudah dapat dilakukan setelah tahap selesai

Jalur: **Surat → detail draft → Generate preview → Lihat/Cetak PDF**. Permintaan pertama menampilkan status antrean; setelah siap, buka viewer PDF, periksa, dan pilih **Saya sudah memeriksa PDF** bila ingin memakai PDF sebagai bukti penerbitan. Pemeriksaan DOCX tetap dapat dipakai sendiri.

Pada surat terbit: **Unduh → PDF (.pdf)** meminta konversi pertama. Setelah notifikasi selesai, pilih kembali **Unduh → PDF (.pdf)** untuk mengunduh atau **Lihat/Cetak PDF** untuk membuka viewer. Word selalu tersedia lewat dropdown yang sama. Tidak ada unduhan otomatis ketika pekerjaan selesai di halaman lain.

Migration sudah diterapkan pada database aplikasi lokal. Jalankan ulang `composer run dev` untuk memuat dependensi viewer dan kode worker. Worker terpisah tetap memakai `php artisan queue:work letters --queue=letters --tries=3 --timeout=90`, dan scheduler menjalankan recovery. LibreOffice, Fontconfig, serta salah satu font fallback perlu tersedia; Word tetap dapat dipakai ketika converter belum siap.

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
- [ ] Setelah PDF draft selesai dirender, konfirmasikan dengan operator A; coba menerbitkan dari operator B tanpa pemeriksaannya sendiri. **Harapan:** pemeriksaan terpisah per akun; B tetap harus membuka dan mengonfirmasi hasilnya sendiri.
- [ ] Ubah isi draft saat konversi berjalan pada data uji. **Harapan:** hasil lama tidak menjadi bukti draft baru; isian editor tetap tersedia, nomor belum diambil.
- [ ] Putus koneksi lalu coba lagi. **Harapan:** status pekerjaan/hasil dapat ditemukan kembali, tidak mengambil nomor/versi kedua.
- [ ] Periksa Network browser ketika membuka viewer PDF. **Harapan:** WASM berasal dari aset aplikasi, worker lokal, dan tidak ada pengambilan font/dokumen dari CDN.
- [ ] Uji keyboard Tab, Escape, dan tombol navigasi halaman pada viewer. **Harapan:** fokus tetap dapat dijangkau; menutup viewer kembali ke pemicunya.
- [ ] Pasang font baru setelah PDF resmi tersimpan. **Harapan:** PDF historis yang ada tidak berubah diam-diam; draft terkait dapat dibuat ulang memakai font baru.

## Hasil pengguna

Tanggal, sampel, perbandingan Word/PDF/cetak, dan temuan: belum diisi.

## Bukti otomatis dan batas tahap

Verifikasi tahap 8: **75 test PHPUnit/838 assertion** pada PDF, preview, penerbitan, SSE, font, dan alokasi nomor lulus; **35 test frontend terarah** lulus. Suite frontend penuh menghasilkan **382 lulus/1 gagal**: `employee-transfer.test.jsx` masih mengharapkan batas Excel 2 MB, sedangkan perubahan pengguna di luar Surat menaikkannya menjadi 5 MB. Perubahan Pegawai tersebut tidak diubah atau dimasukkan commit tahap 8. Pint, lint global, pemeriksaan tipe standar, dan build produksi lulus. Build memberi peringatan ukuran chunk aplikasi/engine PDF serta externalisasi modul Node `crypto` dari paket EmbedPDF; viewer/engine dimuat lazy dan WASM disertakan sebagai aset aplikasi. Pengujian visual dan cetak browser belum dilakukan oleh agent.

Pengujian backend mencakup konversi hanya saat diminta, dedup lintas aktor, akses status/SSE subscriber, scope induk/UUID/login, pemeriksaan DOCX/PDF per aktor, penerbitan dari bukti PDF, profil berubah, hasil rusak, retry manual/infrastruktur, lease, recovery, rollback audit/berkas, serta PDF resmi yang tetap memakai hasil historis. Konversi LibreOffice nyata diuji dengan dokumen dua halaman, ekstraksi teks/penanda DRAFT, font fallback tersemat, nomor resmi tanpa DRAFT, dan perbandingan byte Word sebelum/sesudah. Pemeriksaan signature/EOF bukan parser PDF umum.

Frontend menguji pilihan dropdown, Word tetap tersedia saat PDF sibuk, tanpa konversi saat mount, kunci retry setelah respons putus, hasil SSE yang lebih baru, source yang sesuai, pembatasan byte/MIME/cancellation, serta pengakuan render sebelum konfirmasi PDF. Seluruh pemeriksaan visual/browser/cetak di atas tetap belum dijalankan oleh agent. Uji worker serentak/crash menyeluruh dan cleanup tetap tahap 10.

Referensi API: [EmbedPDF headless](https://www.embedpdf.com/docs/react/headless/getting-started), [engine PDFium](https://www.embedpdf.com/docs/react/headless/engine), dan [parameter ekspor PDF LibreOffice](https://help.libreoffice.org/latest/en-US/text/shared/guide/pdf_params.html).
