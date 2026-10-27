# Tahap 1 — Viewer DOCX dan Cetak

Status implementasi: selesai; pemeriksaan visual dan cetak belum dinilai. Status uji mandiri pengguna: belum dijalankan.

Acuan: [rencana teknis](../../planning/letter/teknis.md). Agent tidak menjalankan pemeriksaan visual atau cetak dan tidak mengisi hasil checklist ini.

## Yang sudah dapat dilakukan setelah tahap selesai

Dari kelola Templat Surat, generate hasil uji lalu buka viewer DOCX hanya-baca, zoom, navigasi halaman, dan Cetak. Fitur penerbitan Surat belum tersedia.

## Persiapan

Gunakan templat/data sintetis. Siapkan sampel satu halaman dengan kop/logo dan header/footer; tabel pegawai berulang satu halaman dan beberapa halaman; paragraf panjang; portrait/landscape; nomor halaman; font biasa dan font yang belum tersedia. Buka Word asli sebagai pembanding. Jangan memakai dokumen rahasia di situs demo eksternal.

## Checklist mandiri

- [ ] Buka hasil generate. **Harapan:** dokumen tampil tanpa harus mengunduh atau mengonversi PDF.
- [ ] Bandingkan isi, kop/logo, margin, header/footer, nomor halaman, tabel, dan tanda tangan dengan Word. **Harapan:** tidak ada isi hilang, teks menumpuk, atau tabel terpotong; catat setiap perbedaan.
- [ ] Generate tabel berulang hingga lebih dari satu halaman. **Harapan:** seluruh baris terbaca; periksa pemenggalan dan header tabel, bukan hanya jumlah halaman.
- [ ] Coba orientasi portrait/landscape dan font campuran. **Harapan:** geometri terbaca dan substitusi yang terjadi dicatat, tanpa mengklaim font pasti sama.
- [ ] Coba zoom, fit width, pindah halaman, lalu tutup/buka viewer. **Harapan:** kontrol berfungsi dan tidak menggandakan isi.
- [ ] Coba mengetik, paste, dan shortcut pengeditan pada dokumen. **Harapan:** isi tidak dapat diubah melalui viewer; tidak ada tombol Simpan/editor.
- [ ] Cetak ke printer atau simpan hasil dialog cetak sebagai PDF browser. **Harapan:** toolbar tidak tercetak, isi lengkap, halaman tidak terpotong atau memiliki lembar kosong tambahan yang tidak dapat diterima.
- [ ] Buka pada desktop dan layar sempit. **Harapan:** kontrol dapat diakses tanpa membuat seluruh halaman aplikasi melebar.

## Keputusan kelayakan oleh pengguna

- Hasil: belum diputuskan — layak / layak dengan batasan / belum layak.
- Sampel dan perbedaan yang masih diterima: belum diisi.
- Bila belum layak, laporkan temuannya sebelum DOCX dijadikan preview utama dan dropdown unduhan diterapkan. Tahap lain yang tidak bergantung pada viewer dapat dilanjutkan.
