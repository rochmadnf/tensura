# Enhancement: Import dan ekspor templat Tensura

Status: diimplementasikan pada versi 0.5.0.

## Isi dan pilihan ekspor

- `.tnex` (Tensura Exchange) sebagai ekstensi file proprietary resmi untuk fitur ekspor dan impor aplikasi Tensura
- Satu paket `.tnex` membawa satu versi pilihan: identitas templat, variabel, urutan pertanyaan, pemetaan atribut, format tanggal, teks alternatif, dan konfigurasi slot penandatangan.
- **Sertakan DOCX:** default aktif. Simpan DOCX asli agar dapat dipulihkan identik.
- **Sertakan font:** default nonaktif. Jika aktif, hanya sertakan berkas font yang benar-benar digunakan DOCX, termasuk varian regular/bold/italic yang diperlukan. Tidak membawa seluruh koleksi font server.
- Pilihan font tetap tersedia ketika DOCX tidak disertakan; kebutuhannya dihitung dari DOCX sumber.
- Tampilkan perkiraan ukuran berdasarkan konfigurasi, DOCX, dan font yang dipilih. Ukuran akhir tersedia setelah paket selesai dibuat.
- Ekspor tersedia untuk draft, versi terbit, atau arsip yang memiliki konfigurasi dan DOCX sumber valid. Surat, data pegawai, isi referensi, penghitung nomor, riwayat, serta bukti pemeriksaan tidak dibawa.

## Enkripsi dan pemeriksaan paket

- Kata sandi wajib, minimum 12 karakter, dengan konfirmasi ulang saat ekspor.
- Gunakan Argon2id untuk derivasi kunci dan Sodium Secretstream XChaCha20-Poly1305 untuk enkripsi terautentikasi, dengan salt dan header stream baru untuk setiap paket.
- Enkripsi seluruh payload. Header luar hanya memuat identitas format, versi, serta informasi teknis dekripsi. Tidak memakai `APP_KEY`, kunci bersama, atau menyimpan kata sandi dalam berkas maupun log.
- Format versi pertama menetapkan parameter derivasi kunci yang tetap; jangan menerima biaya komputasi sembarang dari berkas impor.
- Setelah dekripsi, validasi manifest berversi, integritas setiap berkas, struktur DOCX, konfigurasi, font, batas ukuran, dan jumlah entri.
- Periksa atribut yang digunakan terhadap struktur pegawai dan katalog pemetaan tujuan, termasuk atribut turunan serta relasi jabatan. Atribut hilang atau tidak kompatibel memblokir impor; kolom tambahan diperbolehkan.

## Alur impor

- Unggah paket dan masukkan kata sandi → tampilkan ringkasan serta kompatibilitas → pilih tujuan dan petakan data lokal → konfirmasikan penyimpanan.
- Admin memilih klasifikasi nomor, grup referensi, dan kandidat penandatangan dari instalasi tujuan. Tidak mencocokkan otomatis berdasarkan nama.
- Tujuan berupa templat baru atau draft versi baru pada templat yang ada. Jika sudah ada draft, admin boleh menggantinya melalui konfirmasi khusus.
- Saat mengganti draft, periksa kembali token perubahan agar pekerjaan yang berubah sejak tahap pemeriksaan tidak tertimpa diam-diam.
- **Paket tanpa DOCX menghasilkan draft tanpa DOCX**, termasuk ketika mengganti draft. Admin wajib mengunggah dokumen kembali.
- Semua hasil impor berstatus draft dan harus melewati validasi, uji, serta konfirmasi lokal sebelum publikasi.

## Penanganan font dan penyimpanan

- Temukan font yang digunakan melalui pemeriksa font dokumen yang sudah tersedia; jangan memasukkan font fallback sebagai pengganti font asli.
- Jika font yang diminta untuk disertakan tidak tersedia, tampilkan daftar kekurangan dan minta admin melengkapinya atau menonaktifkan penyertaan font.
- Impor font melalui validasi pengelolaan font yang ada. Gunakan ulang berkas identik; jika keluarga/varian yang sama memiliki isi berbeda, blokir penyimpanan sampai konflik diselesaikan, tanpa menimpa otomatis.
- Tahap pemeriksaan memakai penyimpanan sementara privat, token terikat akun, dan masa berlaku 30 menit. Bersihkan setelah selesai, dibatalkan, atau kedaluwarsa.
- Simpan konfigurasi, dokumen, dan font secara konsisten menggunakan transaksi serta penguncian. Kegagalan tidak boleh meninggalkan hasil parsial atau merusak draft lama.
- Gunakan komponen UI, Wayfinder, hak akses, dan audit yang sudah ada. Catat operasi tanpa rahasia.
- Setelah fitur selesai, naikkan versi minor dan tambahkan catatan rilis.

## Pengujian dan penerimaan

- Uji seluruh kombinasi penyertaan DOCX/font, termasuk hanya Tahoma saat DOCX memakai Tahoma saja.
- Pastikan konfigurasi dan byte DOCX/font tetap identik setelah ekspor–impor antarinstalasi.
- Uji kata sandi salah, paket berubah/terpotong, versi format tidak dikenal, isi berbahaya/tidak valid, dan batas ukuran.
- Uji kompatibilitas atribut, pemetaan belum lengkap, font hilang atau konflik, serta kewajiban unggah DOCX untuk paket tanpa dokumen.
- Uji templat baru, versi baru, penggantian draft, perubahan bersamaan, rollback, isolasi akun, dan pembersihan sementara.
- Jalankan tes terkait, Pint, pemeriksaan format, TypeScript, pemeriksaan UI, dan build.
