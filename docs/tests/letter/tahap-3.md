# Tahap 3 — Token Sistem dan Mesin Dokumen

Status implementasi: direncanakan. Status uji mandiri pengguna: belum dijalankan.

Acuan: [rencana teknis](../../planning/letter/teknis.md). Agent menjalankan test nonvisual terkait; perbandingan dokumen dilakukan pengguna.

## Yang sudah dapat dilakukan setelah tahap selesai

Pada Templat Surat, gunakan `${nomor_surat}` dan `${tanggal_surat}`, atur format tanggal, lalu validasi/generate uji. Generate uji tidak mengambil nomor Surat.

## Checklist mandiri

- [ ] Buka pengaturan templat. **Harapan:** token sistem dan bantuan penggunaannya terlihat serta dapat disalin.
- [ ] Masukkan kedua token ke DOCX, unggah, lalu validasi. **Harapan:** dikenali tanpa harus membuat variabel formulir dengan nama yang sama.
- [ ] Buat variabel/kolom yang bentrok dengan token sistem. **Harapan:** ditolak dengan petunjuk mengganti nama.
- [ ] Generate hasil uji. **Harapan:** nomor contoh dan tanggal tampil; angka pada master/periode tidak bertambah.
- [ ] Ganti format tanggal, termasuk bulan dalam bahasa Indonesia. **Harapan:** contoh dan hasil memakai format baru; pemeriksaan sebelumnya perlu diulang.
- [ ] Unduh DOCX panduan. **Harapan:** bantuan token sistem dan format tanggal sesuai konfigurasi tersimpan.
- [ ] Uji kembali templat tanpa token baru, placeholder di header/footer, teks panjang, tabel multiple kosong/terisi, dan urutan pilihan B lalu A. **Harapan:** perilaku existing tetap berfungsi.
- [ ] Isi teks literal yang menyerupai `${nama}`. **Harapan:** tetap menjadi teks input, bukan diproses sebagai placeholder kedua.

## Hasil pengguna

Tanggal, sampel, dan temuan: belum diisi.
