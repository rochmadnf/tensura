# Tahap 6 — Worker, SSE, dan Preview Draft

Status implementasi: selesai, terverifikasi nonvisual. Status uji mandiri pengguna: belum dijalankan.

Acuan: [rencana teknis](../../planning/letter/teknis.md). Migration tahap 6 sudah diterapkan pada database aplikasi lokal. Semua checkbox visual di bawah tetap milik pengguna.

## Persiapan lingkungan

Jalankan ulang `composer run dev`. Pada lingkungan lokal Linux, proses `letters` menjalankan antrean khusus Surat, `scheduler` menjalankan pemulihan operasi, dan server development memakai delapan worker HTTP dengan `--no-reload` untuk SSE. Port server mengikuti port eksplisit `APP_URL`, atau 8000 bila tidak ada. Restart worker setelah perubahan kode; `queue:work` adalah proses berumur panjang.

Jika menjalankan proses terpisah:

```sh
php artisan queue:work letters --queue=letters --tries=3 --timeout=90
php artisan schedule:work
```

Kedua perintah berjalan pada terminal/proses masing-masing. Untuk server development mandiri, gunakan `PHP_CLI_SERVER_WORKERS=8 php artisan serve --no-reload` dengan host/port yang biasa digunakan. Server HTTP satu worker tidak memadai: SSE menahan satu worker per tab login. Sisakan kapasitas worker untuk request biasa. Host produksi perlu PHP-FPM/proxy dengan buffering SSE nonaktif, timeout lebih dari 60 detik, dan koneksi PostgreSQL langsung untuk listener. Health check dan panduan deployment menyeluruh mengikuti tahap 10.

Pemulihan manual bila perlu: `php artisan letters:recover-operations`. Operasi queued yang tertinggal minimal dua menit atau lease worker habis dapat dilanjutkan; tidak langsung dinyatakan gagal hanya karena tab ditutup.

## Yang sudah dapat dilakukan setelah tahap selesai

Jalur: **Surat → detail draft → Pemeriksaan Dokumen → Generate preview**. Setelah selesai, buka **Preview DOCX**, tunggu render siap, lalu pilih **Saya sudah memeriksa dokumen**. Anda dapat berpindah halaman sambil menunggu dan memakai aksi **Buka hasil** pada notifikasi. Konfirmasi tersimpan per operator.

Belum ada nomor resmi atau tombol unduh Word draft. Viewer hanya-baca menyediakan zoom, halaman, sesuaikan lebar, serta Cetak. Hasil tersedia 24 jam; isian draft tetap disimpan setelah preview kedaluwarsa. Kelayakan layout/cetak viewer tetap perlu Anda nilai.

## Checklist mandiri

- [ ] Minta preview lalu klik kembali saat proses berjalan. **Harapan:** tidak membuat pekerjaan identik berkali-kali; status Menunggu/Diproses terlihat.
- [ ] Pindah ke halaman Pegawai sambil menunggu. **Harapan:** worker tetap berjalan dan notifikasi selesai/gagal muncul dengan aksi Buka hasil, tanpa memaksa navigasi.
- [ ] Refresh halaman atau putus/sambungkan jaringan ketika job berjalan. **Harapan:** status pulih dan hasil tidak hilang/tidak digandakan.
- [ ] Tutup tab, tunggu proses selesai, lalu buka aplikasi/detail lagi. **Harapan:** hasil tetap tersedia tanpa harus membuat job baru.
- [ ] Logout lalu login sebagai akun berbeda. **Harapan:** tidak menerima event atau tautan berkas privat dari sesi sebelumnya.
- [ ] Buka hasil DOCX. **Harapan:** nomor `445/XX.XX/PKM-KMJ/...` berwarna warning; tidak ada tombol unduh Word draft. Cetak draft memiliki penanda DRAFT.
- [ ] Konfirmasi pemeriksaan setelah render siap. **Harapan:** terikat ke hasil yang dibuka; ubah isian lalu coba memakai konfirmasi lama, harus diminta periksa ulang.
- [ ] Ubah font/versi templat yang relevan selama job berjalan. **Harapan:** hasil usang tidak menggantikan hasil draft terbaru atau menjadi bukti pemeriksaan yang sah.
- [ ] Ubah master yang dipilih setelah menyimpan draft, lalu buat preview. **Harapan:** nilai snapshot lama tetap digunakan; mengambil nilai baru memerlukan aksi pembaruan master eksplisit.
- [ ] Coba konfirmasi dengan operator lain. **Harapan:** ia harus membuka dan menyelesaikan render sendiri; pemeriksaannya tidak menimpa milik operator pertama.
- [ ] Biarkan preview kedaluwarsa atau ubah isian. **Harapan:** hasil lama tidak dapat digunakan sebagai bukti; Generate preview tersedia kembali dan isian tetap ada.
- [ ] Saat editor berisi input belum disimpan, biarkan pekerjaan selesai. **Harapan:** notifikasi/status preview berubah tanpa mereset field atau menutup editor.
- [ ] Pada lingkungan uji, jalankan kasus worker gagal atau berhenti. **Harapan:** draft/input tersimpan, status dapat dipahami dan retry tersedia setelah kegagalan teridentifikasi.
- [ ] Periksa notifikasi setelah beberapa kali navigasi/reconnect. **Harapan:** tidak muncul toast selesai yang sama berulang-ulang pada tab yang sama.

## Hasil pengguna

Tanggal, kondisi jaringan/worker, dan temuan: belum diisi. SSE bukan bukti job selesai bila artefaknya belum tersedia.

## Bukti otomatis dan batas tahap

Sebanyak **94 test PHPUnit/849 assertion** pada suite terkait dan **367 test frontend** lulus. Pint, lint global, pemeriksaan tipe standar, dan build produksi lulus. Build masih memberi peringatan ukuran chunk aplikasi yang sudah ada. Jadwal pemulihan dan konfigurasi proses development dikonfirmasi melalui Artisan.

Test mencakup operasi ganda, kunci idempotensi, scope aktor/UUID, lease/retry/recovery, rollback audit/berkas, hasil usang/kedaluwarsa/rusak, perubahan renderer, konfirmasi per aktor setelah render, serta tidak adanya alokasi nomor. Render DOCX lewat subprocess sesungguhnya diuji nonvisual. LISTEN/NOTIFY diuji dengan koneksi PostgreSQL terpisah, termasuk commit dan rollback. Frontend mencakup reconnect/backoff, deduplikasi per tab/aktor, logout/cleanup, event terlambat, serta kesiapan viewer sebelum pengakuan render.

Status SSE bersumber dari metadata durable; belum ada pengujian browser, inspeksi visual dokumen, atau cetak oleh agent. Konkurensi worker menyeluruh, cleanup artefak, serta health check deployment dilengkapi pada tahap 10. PDF opsional belum tersedia dan tidak dipakai sebagai bukti pemeriksaan pada tahap ini.
