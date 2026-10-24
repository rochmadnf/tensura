# Archive Reference — Proses Bisnis

Status: rekap rencana dan proses bisnis hasil diskusi. Belum diimplementasikan.

Dokumen ini mencatat rencana Archive tahap awal untuk modul Reference, termasuk lima mode restore, Delete Forever, serta pemetaan daftar dan detail arsip.

## 1. Tujuan, cakupan, dan akses

- Data yang dihapus dipindahkan dari tabel aktif ke tabel arsip terstruktur agar tetap dapat dipulihkan.
- Gunakan tabel arsip terpisah, bukan penanda `deleted_at` atau `archived_at` pada tabel aktif.
- Tahap awal mencakup grup dan item Reference. Perilaku penghapusan modul lain belum berubah.
- Semua pengguna yang sudah login dapat melihat arsip, melakukan restore, dan melakukan Delete Forever.
- Restore mendukung pemulihan sebagian: data yang aman dipulihkan, sedangkan data konflik tetap di arsip.
- Setiap restore melalui pratinjau dan konfirmasi.
- Ribuan baris tidak dengan sendirinya membuktikan soft delete lambat. Pemisahan arsip dipilih untuk menjaga tabel aktif hanya berisi data aktif; performa tetap bergantung pada query dan indeks.

## 2. Navigasi

| Pintu masuk | Perilaku |
| --- | --- |
| Menu **Archive** di sidebar | Membuka daftar arsip terpusat; tahap awal hanya memuat Reference |
| Tombol **Arsip** pada daftar Reference | Membuka daftar yang sama dengan konteks dan filter modul Reference |

- Kedua pintu masuk menggunakan sumber data yang sama.
- Pencarian, filter, dan pagination tersimpan di URL.
- Modul lain dapat ditambahkan ke daftar terpusat pada tahap berikutnya.

## 3. Pengarsipan

| Aksi | Data yang diarsipkan |
| --- | --- |
| Hapus grup | Grup beserta seluruh item yang masih aktif sebagai satu paket |
| Hapus item | Satu item mandiri beserta identitas grup asalnya |

- Simpan data asal, ID, UUID, timestamp asli, waktu pengarsipan, dan pengguna yang menghapus.
- Bedakan item yang dihapus mandiri dengan item yang ikut dalam paket penghapusan grup.
- Item yang sebelumnya dihapus mandiri tetap menjadi entri terpisah ketika grupnya kemudian dihapus.
- Penyimpanan arsip dan penghapusan data aktif berjalan dalam satu transaksi. Kegagalan membatalkan seluruh operasi.
- Dialog hapus menjelaskan bahwa data dipindahkan ke arsip dan dapat dipulihkan.
- Endpoint delete Reference yang ada tetap digunakan, dengan perilaku backend diubah menjadi pengarsipan.

## 4. Daftar arsip

Satu baris mewakili paket grup yang dihapus atau item yang dihapus mandiri. Children dalam paket ditampilkan di detail agar daftar tidak berulang.

### Kolom

| Kolom | Isi |
| --- | --- |
| **Data** | Nama grup atau konten item yang diarsipkan; dapat diklik untuk membuka detail |
| **Modul** | Nama modul; hanya tampil pada daftar arsip terpusat |
| **Jenis** | Grup atau Item |
| **Induk** | Nama grup asal untuk item; tanda `—` untuk grup |
| **Item tersisa** | Jumlah children yang masih diarsipkan; tanda `—` untuk item mandiri |
| **Status induk** | Masih diarsipkan, Aktif, atau Tidak tersedia |
| **Diarsipkan pada** | Tanggal dan waktu penghapusan |
| **Diarsipkan oleh** | Nama pengguna yang menghapus |
| **Aksi** | Menu tindakan per baris |

Status konflik bukan kolom tetap karena kondisi data aktif dapat berubah. Konflik diperiksa pada pratinjau restore.

### Aksi per baris

| Aksi | Perilaku |
| --- | --- |
| **Detail** | Membuka isi arsip, children, dan pilihan restore |
| **Pulihkan…** | Membuka detail langsung ke bagian pemulihan; pengguna memilih mode lalu melihat pratinjau |
| **Hapus Permanen…** | Membuka konfirmasi Delete Forever untuk paket atau item pada baris tersebut |

### Kontrol daftar

- Pencarian berdasarkan nama grup atau konten item, termasuk konten children yang masih berada dalam paket arsip.
- Filter Modul pada daftar terpusat.
- Filter Jenis: Semua, Grup, atau Item.
- Reset filter, pagination, dan pengaturan jumlah baris.
- Daftar utama tidak menyediakan checkbox karena operasi lintas paket belum termasuk cakupan awal.

## 5. Detail arsip dan pemilihan item

Alur pemulihan:

**Daftar arsip → Detail/pratinjau → Penyelesaian konflik bila diperlukan → Konfirmasi → Hasil pemulihan**

- Tampilkan informasi asal, pelaku, waktu penghapusan, status induk, tujuan pemulihan, dan children tersisa.
- Ketika induk belum aktif, tampilkan **Pulihkan Induk Saja** dan **Pulihkan Induk + Item**.
- Ketika induk aktif, tampilkan **Pulihkan Semua Item**, tombol **Pulihkan** per baris, checkbox, dan **Pulihkan Terpilih** dengan jumlah pilihan.
- Checkbox berlaku lintas halaman tabel dalam detail paket yang sama. Checkbox header hanya memilih halaman saat ini.
- Tampilkan jumlah pilihan total dan aksi untuk menghapus seluruh pilihan.
- Pratinjau memuat tepat data sesuai mode dan pilihan pengguna.
- Perubahan pilihan penggabungan atau nama menghasilkan pratinjau baru sebelum konfirmasi.
- Setelah pemulihan, tampilkan jumlah berhasil dan rincian data yang tertahan.
- Bersihkan pilihan checkbox setelah operasi berhasil agar percobaan berikutnya disengaja.
- Aksi restore dan Delete Forever ditampilkan secara terpisah dan jelas.

## 6. Lima mode restore

| Mode | Perilaku | Syarat |
| --- | --- | --- |
| **1. Induk saja** | Pulihkan grup tanpa children | Identitas dan nama grup tidak konflik |
| **2. Induk + children** | Pulihkan grup beserta seluruh children yang aman | Konflik grup harus diselesaikan dahulu |
| **3. Semua children** | Pulihkan seluruh children yang masih diarsipkan | Induk sudah aktif |
| **4. Satu child** | Pulihkan satu item melalui tombol pada baris | Induk sudah aktif |
| **5. Children terpilih** | Pulihkan item yang dipilih melalui checkbox | Induk sudah aktif dan minimal satu item dipilih |

- Mode 3–5 tersedia setelah mode 1 berhasil, atau untuk sisa children setelah mode 2.
- Penggabungan yang dikonfirmasi secara eksplisit dapat menetapkan grup aktif lain sebagai tujuan children.
- Item yang diarsipkan mandiri menggunakan pemulihan satu item, dengan grup asal yang aktif sebagai tujuan.
- Mode children tidak ikut memulihkan item di luar cakupan yang diminta.

### Identitas, relasi, dan urutan

- Pemulihan biasa mempertahankan ID, UUID, dan timestamp asli. Benturan identitas menahan data terkait di arsip.
- Penggabungan menggunakan identitas grup aktif sebagai induk tujuan.
- Mode 2 mempertahankan urutan item asal.
- Mode 3–5 dan penggabungan menambahkan item di akhir dengan urutan relatif sesuai arsip.
- Jika induk aktif kembali dihapus, tahan pemulihan children sampai induk tersedia atau pengguna mengonfirmasi grup tujuan lain.

## 7. Konflik dan pemulihan sebagian

| Konflik | Perilaku |
| --- | --- |
| Nama atau identitas induk bentrok | Tahan paket sampai konflik diselesaikan |
| Konten child sudah ada dalam grup tujuan | Lewati child tersebut; child lain yang aman tetap dipulihkan |
| Identitas child bentrok | Tahan child terkait tanpa menimpa data aktif |
| Grup tujuan item belum aktif | Tahan pemulihan dan tampilkan arahan untuk memulihkan induk dahulu |

### Penyelesaian konflik nama grup

- Tampilkan grup arsip dan grup aktif berdampingan, mencakup nama, deskripsi, daftar item, dan konten yang bentrok.
- Pengguna dapat mengonfirmasi penggabungan ke grup aktif, mengubah nama grup arsip, atau membuka edit grup aktif lalu mencoba kembali.
- Nama yang sama tidak otomatis dianggap sebagai identitas grup yang sama.
- Penggabungan memulihkan item yang aman dan menyisakan item konflik di arsip.
- Simpan tujuan penggabungan pada sisa arsip agar percobaan ulang menuju grup yang sama.

### Sisa arsip

- Hapus entri arsip yang berhasil dipulihkan; sisakan data yang belum pulih.
- Setelah induk dipulihkan, hapus salinan arsip induk yang selesai, tetapi pertahankan catatan paket dan kaitannya ke induk aktif selama masih ada children tersisa.
- Catatan paket yang tersisa bukan kandidat restore induk ulang dan tetap dapat dibuka melalui daftar arsip.
- Bersihkan metadata paket ketika seluruh data yang diarsipkan sudah selesai diproses dan informasi tersebut tidak lagi diperlukan.

## 8. Delete Forever

| Aksi | Perilaku |
| --- | --- |
| **Hapus paket permanen** | Hapus grup yang masih diarsipkan, metadata paket, dan seluruh children arsip tersisa |
| **Hapus satu child permanen** | Hapus satu item arsip |
| **Hapus children terpilih permanen** | Hapus item arsip yang dipilih melalui checkbox |

- Pengguna wajib mengonfirmasi sebelum eksekusi. Konfirmasi menampilkan nama data, jumlah children terdampak, dan keterangan bahwa data tidak dapat dipulihkan.
- Delete Forever hanya menghapus data arsip. Induk maupun children yang sudah aktif tetap utuh.
- Penghapusan sebagian children mempertahankan metadata paket yang masih dibutuhkan.
- Jika child terakhir dihapus sementara induk belum dipulihkan, arsip induk tetap tersedia untuk restore.
- Jika induk sudah dipulihkan dan seluruh children selesai diproses, bersihkan metadata paket.
- Penghapusan paket dan children terpilih berjalan dalam transaksi; kegagalan membatalkan operasi terkait.
- Permintaan berulang tidak boleh menghapus data aktif atau data di luar sasaran awal.

## 9. Pemetaan aplikasi dan kontrak

- Gunakan tabel arsip grup dan item terstruktur, bukan snapshot JSON sebagai penyimpanan utama.
- Rawat kesesuaian skema arsip ketika skema data aktif berubah.
- Pisahkan penanganan pengarsipan dan pemulihan Reference dari controller tanpa membangun mekanisme generik untuk seluruh modul pada tahap awal.
- Tambahkan endpoint terautentikasi untuk daftar/detail arsip, pratinjau, perubahan nama grup arsip, konfirmasi restore, dan Delete Forever paket/item terpilih.
- Gunakan UUID, named routes, dan Wayfinder untuk integrasi frontend dengan backend.
- Pratinjau dan konfirmasi menerima identitas arsip, mode restore, pilihan tujuan bila ada, serta UUID item untuk mode terpilih.
- Backend memvalidasi bahwa setiap item termasuk dalam paket yang dituju dan bahwa tujuan pemulihan tersedia.
- Pratinjau tidak mengubah database. Hasilnya bukan dasar otoritatif untuk eksekusi; backend memeriksa ulang kondisi saat konfirmasi.
- Koordinasikan transaksi dengan tambah, edit, hapus, reorder, restore, dan Delete Forever agar perubahan bersamaan tidak menyebabkan kehilangan atau duplikasi.
- Konfirmasi restore berulang tidak membuat duplikasi atau memulihkan ulang induk yang sudah aktif.
- Gunakan komponen daftar, pagination, dialog, formulir, dan feedback yang sudah tersedia dalam aplikasi.

## 10. Pengujian dan verifikasi

- Pengarsipan grup/item menyimpan data lengkap dan hanya menghapus sasaran yang dipilih.
- Kegagalan penyimpanan arsip membatalkan penghapusan aktif.
- Kedua pintu navigasi menampilkan sumber arsip yang sama dengan konteks filter yang sesuai.
- Pratinjau tidak mengubah data dan konfirmasi hanya memproses cakupan yang dipilih.
- Kelima mode restore menjaga identitas, relasi, dan urutan sesuai aturan.
- Uji pilihan kosong, item dari paket lain, serta pilihan lintas halaman.
- Uji konflik nama, konten, identitas, penggabungan, restore sebagian, dan percobaan ulang.
- Uji induk yang belum aktif, induk yang dipulihkan tanpa children, dan induk yang dihapus kembali.
- Uji ketiga aksi Delete Forever, rollback, penghapusan child terakhir, dan perlindungan data aktif.
- Uji perubahan setelah pratinjau, konfirmasi berulang, restore versus Delete Forever, serta operasi bersamaan lainnya.
- Tolak tamu, UUID tidak valid, dan item yang tidak termasuk paket tujuan.
- Jalankan pengujian Reference/Archive yang terdampak, pemeriksaan TypeScript, build frontend, serta Pint untuk perubahan PHP.

## 11. Batas tahap awal

- Implementasi pengarsipan hanya untuk Reference.
- Operasi berlaku pada satu paket atau item mandiri per alur; pemilihan beberapa children dalam satu paket didukung.
- Bulk restore dan Delete Forever lintas paket belum termasuk.
- Tidak ada kedaluwarsa atau penghapusan otomatis arsip.
- Data arsip yang berhasil dipulihkan dihapus; tidak ada penyimpanan riwayat pemulihan lengkap dalam tabel arsip.
