# Tahap 5 — Pengelolaan Draft Surat

Status implementasi: selesai, terverifikasi nonvisual. Status uji mandiri pengguna: belum dijalankan.

Acuan: [rencana teknis](../../planning/letter/teknis.md). Siapkan dua akun operator serta templat aktif dengan teks, tanggal, pegawai/referensi, multiple, dan penandatangan.

## Yang sudah dapat dilakukan setelah tahap selesai

Menu Surat membuka daftar dan halaman detail/kelola. Operator dapat membuat, menyimpan, melanjutkan, dan menghapus draft; belum dapat menerbitkan.

Jalur: **Surat → Surat Baru → pilih templat aktif yang telah terbit → lengkapi isian → Simpan**. Halaman detail mulai dalam mode baca; tombol ubah membuka satu editor. Menu tindakan menyediakan pembaruan master, penyesuaian templat (bila ada versi aktif berbeda), dan hapus draft baru. Nomor manual hanya pilihan tersimpan, bukan pemesanan nomor.

## Checklist mandiri

- [ ] Buat draft dengan perihal dan templat aktif. **Harapan:** isian mengikuti token yang dipakai, tanggal default hari ini, nomor resmi belum ada.
- [ ] Simpan lalu buka ulang. **Harapan:** isian, penandatangan, dan urutan pilihan multiple tetap sama.
- [ ] Coba field wajib kosong dan pilihan tidak valid. **Harapan:** pesan field jelas dan input tidak hilang.
- [ ] Pilih nomor manual dan tanggal/periode lampau atau mendatang. **Harapan:** bulan/tahun harus cocok dengan tanggal, posisi valid tersimpan, counter pada Penomoran Surat tidak bertambah; draft lain masih boleh memilih posisi yang sama.
- [ ] Ubah data master setelah draft disimpan. **Harapan:** draft tidak berubah diam-diam; aksi mengambil data terbaru memperlihatkan pembaruan.
- [ ] Aktifkan versi templat baru. **Harapan:** tersedia ringkasan dan tombol Sesuaikan ke versi terbaru; nilai kompatibel bertahan, yang ambigu diminta ulang.
- [ ] Ubah master setelah ringkasan pembaruan dibuka, lalu konfirmasi ringkasan lama. **Harapan:** ditolak tanpa perubahan snapshot; perbarui ringkasan sebelum mencoba lagi.
- [ ] Buat penyesuaian dengan slot penandatangan ambigu atau variabel baru yang wajib. **Harapan:** pilihan yang perlu ditinjau dikosongkan, draft ditandai belum lengkap, dan dapat dilengkapi lewat editor.
- [ ] Nonaktifkan templat. **Harapan:** draft tetap dapat dibaca dan alasan penerbitan tertahan dijelaskan.
- [ ] Buka draft yang sama dengan dua operator; simpan operator A, lalu B. **Harapan:** simpan B ditolak dengan identitas pengubah; input B tetap terlihat untuk disalin.
- [ ] Saat operator B masih mengedit, operator A menyesuaikan draft ke versi templat baru. Simpan dari B. **Harapan:** ditolak, tetapi field/input lama B tetap terlihat untuk disalin sampai B memilih Muat versi terbaru.
- [ ] Coba mode baca/edit dan Batal/Escape. **Harapan:** satu editor aktif, Batal lokal tanpa navigasi, nilai tersimpan pulih.
- [ ] Hapus draft dengan konfirmasi. **Harapan:** hanya draft sasaran dihapus, surat lain tetap ada.
- [ ] Cari perihal, ubah filter, pagination, Back/Forward, dan ukuran layar. **Harapan:** daftar mengikuti kontrol bersama dan tetap dapat digunakan.

## Hasil pengguna

Tanggal dan temuan: belum diisi.

## Bukti otomatis dan batas tahap

107 test PHPUnit/1.067 assertion terkait serta seluruh 345 test frontend lulus. Pint, lint terarah file perubahan, dan build lulus. TypeScript lulus memakai salinan konfigurasi HEAD di `/tmp` dengan path absolut, tanpa mengubah konfigurasi kerja. Pemeriksaan global standar terakhir tertahan oleh opsi `baseUrl` yang ditambahkan pada `tsconfig.json` di luar lingkup Surat; TypeScript 7 tidak mendukung opsi itu. Skema aplikasi lokal dan 11 route terautentikasi telah dikonfirmasi.

Snapshot, konflik revision, token ringkasan usang/antar-surat, penyesuaian UUID item, slot ambigu, rollback transaksi, penghapusan, constraint satu draft, filter/pagination, serta preservasi input/definisi editor diuji nonvisual. Preview/konfirmasi dokumen dan worker/SSE mengikuti tahap 6; penerbitan/nomor resmi/unduhan mengikuti tahap 7–8. Tidak ada item checklist visual di atas yang dinyatakan lulus oleh agent.
