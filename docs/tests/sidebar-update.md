# Pengujian Manual Pembaruan Sidebar

Status implementasi: pembaruan penanda menu aktif telah diterapkan. Status pengujian visual manual: belum dijalankan. Checklist ini merupakan kriteria penerimaan untuk diperiksa pengguna, bukan laporan bahwa pengujian visual sudah selesai.

Pengujian visual dilakukan secara mandiri oleh pengguna menggunakan Chromium. Tidak diperlukan pengujian visual otomatis.

## Persiapan

- Login ke aplikasi dan gunakan data existing untuk membuka halaman tambah, ubah, atau detail yang tersedia. Tidak perlu mengubah atau menghapus data untuk pengujian ini.
- Pastikan browser memuat aset frontend terbaru. Jika perubahan belum muncul, jalankan build atau server development yang biasa digunakan, lalu muat ulang halaman.
- Ulangi checklist pada tema terang dan gelap. Gunakan pengaturan tema aplikasi jika tersedia; jika tema mengikuti sistem, ubah preferensi sistem/browser.
- Periksa desktop dengan lebar sekitar 1440 px dan mobile sekitar 390 px melalui Device Toolbar Chromium. Gunakan zoom 100% terlebih dahulu.
- Centang item hanya jika hasilnya sesuai pada kedua tema dan ukuran layar yang relevan. Catat kasus yang belum dapat diperiksa.

## Warna dan Penanda Aktif

- [x] Buka salah satu modul, lalu jauhkan kursor dari sidebar. **Harapan:** menu aktif langsung terlihat melalui latar biru, teks/ikon yang tegas, dan garis vertikal 3 px di kiri.
- [x] Pada tema terang, periksa menu aktif. **Harapan:** latar Blue 100, teks dan ikon Blue 900, serta garis Blue 700; label mudah dibaca.
- [x] Pada tema gelap, periksa menu aktif. **Harapan:** latar Blue 950, teks dan ikon Blue 100, serta garis Blue 400; penanda tetap jelas terhadap latar sidebar.
- [x] Bandingkan menu aktif dan menu lain. **Harapan:** label aktif semibold, menu biasa netral, dan garis kiri hanya muncul pada menu aktif.
- [x] Arahkan kursor ke menu yang tidak aktif. **Harapan:** hover abu-abu ringan dan tetap berbeda dari menu aktif; menu aktif tidak ikut kehilangan penandanya.
- [x] Arahkan kursor ke menu aktif dan tahan klik sebentar sebelum melepasnya. **Harapan:** latar, warna teks/ikon, dan garis aktif tetap terlihat selama hover maupun saat ditekan.
- [x] Berpindah-pindah menu sambil memperhatikan posisi label dan ikon. **Harapan:** garis penanda tidak menggeser isi, mengubah lebar/tinggi baris, atau membuat menu melompat.

## Navigasi dan Konsistensi

- [x] Buka Dashboard, Surat, Penomoran Surat, Templat Surat, Daftar Referensi, Daftar Pegawai, Jabatan, dan Arsip secara bergantian. **Harapan:** menu tujuan yang tersedia memakai penanda konsisten, dan tepat satu menu navigasi utama/kelompok/sekunder aktif.
- [x] Dari sebuah daftar, buka halaman tambah, ubah, atau detail yang tersedia. **Harapan:** menu modul induk tetap aktif, tanpa menandai modul lain.
- [x] Berpindah ke modul lain, lalu gunakan Back dan Forward browser. **Harapan:** penanda aktif mengikuti halaman yang sedang ditampilkan.
- [x] Muat ulang halaman tambah/ubah/detail secara langsung. **Harapan:** menu induk tetap aktif setelah halaman selesai dimuat.
- [x] Periksa tombol **Buat Instan** pada tema terang dan gelap, termasuk saat hover, ditekan, dan mendapat fokus keyboard. **Harapan:** tombol memakai warna biru utama aplikasi dengan teks/ikon terang yang terbaca, hover tetap biru, dan ring fokus terlihat. Tombol tidak memiliki garis penanda halaman aktif.
- [x] Arahkan kursor ke nama aplikasi dan menu akun. **Harapan:** tidak muncul garis aktif atau warna seleksi navigasi yang tidak sesuai; perilakunya tetap seperti sebelumnya.

## Keyboard dan Aksesibilitas

- [x] Gunakan Tab dan Shift+Tab untuk menelusuri menu tanpa mouse. **Harapan:** fokus selalu terlihat dan tidak terpotong; memindahkan fokus tidak mengubah menu halaman aktif.
- [x] Fokuskan menu yang sedang aktif. **Harapan:** ring fokus tetap terlihat bersama latar dan garis penanda aktif.
- [x] Tekan Enter pada tautan menu lain. **Harapan:** halaman tujuan terbuka dan penanda aktif berpindah dengan benar.
- [x] Melalui tab Elements di DevTools, periksa tautan menu aktif dan nonaktif. **Harapan:** hanya tautan halaman/modul aktif memiliki `aria-current="page"`; menu lain tidak mengklaim sebagai halaman aktif.
- [x] Periksa kontras warna teks aktif dengan latarnya menggunakan pemeriksa kontras DevTools. **Harapan:** minimal 4,5:1 pada tema terang dan gelap. Periksa pasangan warna garis penanda terhadap latarnya dengan alat pemeriksa kontras; minimal 3:1. Catat nilai yang terukur, bukan hanya kesan visual.

## Mobile dan Pembesaran

- [x] Pada lebar sekitar 390 px, buka sidebar melalui tombol menu. **Harapan:** menu aktif langsung terlihat, warna konsisten dengan desktop, dan garis tidak terpotong oleh panel.
- [x] Pilih menu lain, lalu buka kembali sidebar bila panel tertutup. **Harapan:** penanda sudah mengikuti halaman tujuan; navigasi dan tombol buka/tutup tetap bekerja seperti sebelumnya.
- [x] Gulir sidebar sampai bagian Arsip dan akun terlihat. **Harapan:** seluruh item tetap dapat dijangkau, label terbaca, dan penanda aktif tidak hilang karena posisi gulir.
- [x] Ubah ukuran dari mobile ke desktop dan sebaliknya. **Harapan:** status aktif tetap sama dan sidebar mengikuti layout sesuai ukuran layar.
- [x] Ulangi pemeriksaan pada zoom 200%. **Harapan:** label, ikon, garis aktif, dan fokus keyboard tetap dapat dikenali; tidak ada kontrol navigasi yang tertutup atau tidak dapat dijangkau.

## Pemeriksaan Nonvisual oleh Agent

Pemeriksaan berikut telah dijalankan setelah perubahan pada 27 Oktober 2026. Hasil ini tidak menggantikan checklist visual manual di atas.

Setelah warna tombol **Buat Instan** diselaraskan, TypeScript, lint pada `nav-main.tsx`/`sidebar.tsx`, build produksi, dan pemeriksaan diff dijalankan ulang dan lulus. Pengujian visual tombol tetap menunggu pemeriksaan pengguna.

| Pemeriksaan                                                                                                                                        | Hasil                                                                                            |
| -------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------ |
| `pnpm run types:check`                                                                                                                             | Lulus                                                                                            |
| `pnpm exec vp lint --no-ignore resources/js/components/nav-main.tsx resources/js/components/nav-groups.tsx resources/js/components/ui/sidebar.tsx` | Lulus                                                                                            |
| `pnpm run build`                                                                                                                                   | Lulus; ada peringatan externalisasi modul `crypto` dari EmbedPDF dan ukuran chunk di atas 500 kB |
| `git diff --check`                                                                                                                                 | Lulus                                                                                            |

## Catatan Hasil Manual

Isi setelah pengujian. Item gagal harus diuji kembali setelah diperbaiki; item yang dilewati tetap dicatat sebagai belum diverifikasi.

| Informasi                                | Hasil        |
| ---------------------------------------- | ------------ |
| Tanggal dan penguji                      | Belum diisi  |
| Versi Chromium dan sistem operasi        | Belum diisi  |
| Commit/versi aplikasi                    | Belum diisi  |
| Tema dan ukuran layar yang diperiksa     | Belum diisi  |
| Rasio kontras teks/garis pada kedua tema | Belum diukur |
| Checklist yang belum dapat dijalankan    | Belum diisi  |
| Kesimpulan                               | Belum diuji  |

Untuk setiap temuan, catat menu/halaman, tema, ukuran layar/zoom, langkah reproduksi, hasil yang muncul, dan hasil yang diharapkan. Tambahkan screenshot bila membantu menunjukkan masalah.
