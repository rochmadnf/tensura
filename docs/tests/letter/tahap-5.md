# Tahap 5 — Pengelolaan Draft Surat

Status implementasi: direncanakan. Status uji mandiri pengguna: belum dijalankan.

Acuan: [rencana teknis](../../planning/letter/teknis.md). Siapkan dua akun operator serta templat aktif dengan teks, tanggal, pegawai/referensi, multiple, dan penandatangan.

## Yang sudah dapat dilakukan setelah tahap selesai

Menu Surat membuka daftar dan halaman detail/kelola. Operator dapat membuat, menyimpan, melanjutkan, dan menghapus draft; belum dapat menerbitkan.

## Checklist mandiri

- [ ] Buat draft dengan perihal dan templat aktif. **Harapan:** isian mengikuti token yang dipakai, tanggal default hari ini, nomor resmi belum ada.
- [ ] Simpan lalu buka ulang. **Harapan:** isian, penandatangan, dan urutan pilihan multiple tetap sama.
- [ ] Coba field wajib kosong dan pilihan tidak valid. **Harapan:** pesan field jelas dan input tidak hilang.
- [ ] Ubah data master setelah draft disimpan. **Harapan:** draft tidak berubah diam-diam; aksi mengambil data terbaru memperlihatkan pembaruan.
- [ ] Aktifkan versi templat baru. **Harapan:** tersedia ringkasan dan tombol Sesuaikan ke versi terbaru; nilai kompatibel bertahan, yang ambigu diminta ulang.
- [ ] Nonaktifkan templat. **Harapan:** draft tetap dapat dibaca dan alasan penerbitan tertahan dijelaskan.
- [ ] Buka draft yang sama dengan dua operator; simpan operator A, lalu B. **Harapan:** simpan B ditolak dengan identitas pengubah; input B tetap terlihat untuk disalin.
- [ ] Coba mode baca/edit dan Batal/Escape. **Harapan:** satu editor aktif, Batal lokal tanpa navigasi, nilai tersimpan pulih.
- [ ] Hapus draft dengan konfirmasi. **Harapan:** hanya draft sasaran dihapus, surat lain tetap ada.
- [ ] Cari perihal, ubah filter, pagination, Back/Forward, dan ukuran layar. **Harapan:** daftar mengikuti kontrol bersama dan tetap dapat digunakan.

## Hasil pengguna

Tanggal dan temuan: belum diisi.
