# Tahap 2 — Penomoran per Periode

Status implementasi: direncanakan. Status uji mandiri pengguna: belum dijalankan.

Acuan: [rencana teknis](../../planning/letter/teknis.md). Gunakan data sintetis/database uji; agent tidak menjalankan uji visual ini.

## Yang sudah dapat dilakukan setelah tahap selesai

Data Master → Penomoran Surat memiliki detail klasifikasi dan Urutan per Periode. Admin dapat menetapkan periode untuk angka terakhir master. Penerbitan Surat belum tersedia.

## Checklist mandiri

- [ ] Buka detail klasifikasi `445`. **Harapan:** kode/nama jelas dan tabel periode tersedia; navigasi kembali/ubah berfungsi.
- [ ] Siapkan angka terakhir `23.44` untuk Oktober 2026. **Harapan:** periode Oktober menampilkan posisi `23.44` dan perkiraan berikutnya `23.45`.
- [ ] Muat ulang halaman. **Harapan:** angka dan periode tetap tersimpan; persiapan angka lama tidak diterapkan dua kali.
- [ ] Coba membuat periode klasifikasi/bulan/tahun yang sama. **Harapan:** ditolak dengan pesan jelas, tanpa mengganti posisi tersimpan.
- [ ] Siapkan periode kosong. **Harapan:** tertulis belum digunakan dengan perkiraan `01.01`, bukan nomor resmi yang dipesan.
- [ ] Coba bulan/tahun/angka tidak valid. **Harapan:** pesan field sesuai dan data lama tetap utuh.
- [ ] Tambahkan klasifikasi baru. **Harapan:** tidak perlu mengisi counter global lama; identitas dan persiapan periode terpisah.
- [ ] Periksa tabel pada layar sempit dan tombol/header melalui keyboard. **Harapan:** seluruh informasi/aksi tetap dapat diakses.
- [ ] Periksa bahwa counter lama belum hilang sebelum proses transisi selesai. **Harapan:** tidak ada angka existing yang dibuang diam-diam.

## Hasil pengguna

Tanggal, hasil aktual, dan temuan: belum diisi. Aksi Lihat pemakaian boleh masih kosong sampai tahap penerbitan tersedia; jangan menganggap tahap 2 sudah dapat menerbitkan surat.
