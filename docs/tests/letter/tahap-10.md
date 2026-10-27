# Tahap 10 — Integrasi Akhir dan Transisi Counter

Status implementasi: selesai, diverifikasi otomatis/nonvisual. Pengguna melaporkan pengujian fungsional sebelumnya berjalan baik di Linux; rincian uji visual dan penerimaan tahap akhir belum dicatat.

Acuan: [rencana teknis](../../planning/letter/teknis.md). Agent tetap menjalankan pengujian otomatis/integrasi/konkurensi nonvisual; daftar ini untuk uji mandiri pengguna pada lingkungan uji.

## Yang sudah dapat dilakukan setelah tahap selesai

Seluruh siklus Surat terintegrasi, cleanup/recovery berjalan, dan counter operasional hanya berada di periode. Counter lama baru dihapus setelah seluruh angka existing selesai dipindahkan.

## Verifikasi otomatis

- Regresi luas: 500 kasus; empat ekspektasi panduan DOCX diselaraskan dengan token nomor/tanggal tahap 3 dan lulus saat diulang. Kasus lainnya lulus pada suite luas.
- Runner konkurensi Templat/master: 24 test / 325 assertion lulus tanpa skip.
- Runner konkurensi Surat: 9 test / 153 assertion lulus, tanpa skip; memakai proses dan koneksi PostgreSQL terpisah serta skema setelah retirement.
- Frontend Penomoran: 12 test, lint terarah, TypeScript, dan build lulus.
- Pint dan whitespace lulus; dry-run cleanup aplikasi tidak menghapus berkas. Sembilan pemeriksaan health lokal berhasil setelah worker dan scheduler dijalankan untuk verifikasi.
- Agent tidak menjalankan browser, screenshot, pengujian visual, atau cetak. Checklist berikut tetap untuk pengguna.

## Perintah pemeriksaan

- `php artisan letters:retire-counters --check`: preflight tanpa perubahan; setelah siap, `php artisan letters:retire-counters` menjalankan migration penghapusan counter lama. Database aplikasi lokal sudah menjalani transisi ini. Gunakan database disposable untuk menguji kasus prasyarat belum lengkap.
- `php artisan letters:cleanup --dry-run`: menampilkan kandidat tanpa menghapus; `php artisan letters:cleanup` melakukan cleanup berkas sementara. Scheduler menjalankannya setiap jam.
- `php artisan letters:health --json`: seluruh pemeriksaan harus `ok: true` pada lingkungan yang siap. Restart `composer run dev` setelah pembaruan agar worker dan scheduler memakai kode baru; ulangi health check saat layanan terus berjalan.
- Runner konkurensi: lihat konfigurasi database disposable dan perintah pada [rencana teknis](../../planning/letter/teknis.md). Runner mereset database pengujian khusus, bukan database aplikasi.

## Checklist mandiri

- [ ] Periksa detail setiap klasifikasi existing. **Harapan:** angka awal terikat periode yang benar sebelum transisi counter selesai.
- [ ] Pastikan persiapan satu klasifikasi belum lengkap pada lingkungan uji. **Harapan:** penghapusan counter lama tertahan dengan petunjuk, tanpa kehilangan angka.
- [ ] Setelah transisi lengkap, buat/ubah klasifikasi. **Harapan:** tidak ada lagi input counter global; angka hanya dikelola/dibaca pada periode.
- [ ] Jalankan alur tambah → preview → periksa → terbit → unduh Word/PDF → revisi → batal → pulih. **Harapan:** status, nomor, dokumen, alasan, dan timeline konsisten.
- [ ] Jalankan dua penerbitan pada klasifikasi/periode sama dari dua operator. **Harapan:** tidak ada nomor ganda dan tidak menimpa draft orang lain.
- [ ] Putus koneksi/restart worker pada lingkungan uji saat pekerjaan berjalan. **Harapan:** recovery/retry tidak membuat versi atau nomor kedua; hasil akhir dapat ditemukan kembali.
- [ ] Biarkan preview draft kedaluwarsa. **Harapan:** isian tetap ada, preview dapat dibuat ulang; Word/PDF resmi dan historis tidak hilang.
- [ ] Buka surat terbit setelah master pegawai/referensi atau font berubah. **Harapan:** dokumen historis tersimpan tetap sama.
- [ ] Setelah restart layanan, jalankan health check dan dry-run cleanup. **Harapan:** semua health check berhasil, dokumen resmi/historis tidak termasuk kandidat cleanup.
- [ ] Pada Windows, siapkan runtime di WSL2 dan jalankan aplikasi/worker/scheduler di sana, lalu coba seluruh siklus dari browser Windows. **Harapan:** Word/PDF/font dan notifikasi berjalan; Docker tidak diperlukan.
- [ ] Periksa daftar, filter, pagination, Back/Forward, editor, tooltip nomor, dropdown Unduh, notifikasi SSE, keyboard, dan layar sempit. **Harapan:** mengikuti pola UI bersama dan tidak mengganggu input saat job lain selesai.
- [ ] Periksa bahwa hasil tahap 1 yang belum layak tidak dilabeli seolah-olah sudah lolos. **Harapan:** keputusan viewer dan keterbatasan yang disetujui tercatat dengan jelas.

## Rekap penerimaan pengguna

- Tanggal/lingkungan: belum diisi.
- Tahap yang sudah diuji: belum diisi.
- Temuan yang menghalangi penggunaan: belum diisi.
- Batasan tampilan/cetak yang diterima: belum diisi.
- Keputusan penerimaan akhir: belum diputuskan.
