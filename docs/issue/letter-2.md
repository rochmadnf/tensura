# Pusat notifikasi tanpa popup SSE

Status: selesai diimplementasikan pada 27 Oktober 2026.

## Latar belakang

Popup hasil proses surat terkadang langsung muncul beruntun setelah pengguna login. Setiap koneksi SSE dibuka, server mengirim snapshot yang memuat operasi aktif dan hasil operasi hingga 7 hari terakhir. Frontend memperlakukan hasil terminal dari snapshot sebagai pemicu popup, sedangkan catatan pencegah duplikasi hanya disimpan per tab melalui `sessionStorage`.

Hapus popup hasil proses dari SSE. SSE tetap memperbarui status surat secara diam-diam, sedangkan hasil penting disimpan dalam pusat notifikasi per akun.

## Pengalaman pengguna

- Tambahkan lonceng di header dengan jumlah belum dibaca; sembunyikan angka saat nol dan tampilkan `99+` jika melebihi 99.
- Klik lonceng membuka panel samping memakai komponen `Sheet` yang sudah tersedia, responsif untuk desktop dan ponsel.
- Sediakan filter **Semua / Belum dibaca**, tindakan **Tandai semua dibaca**, dan pemuatan lanjutan per 20 notifikasi.
- Setiap entri menampilkan hasil proses, subjek surat, keterangan, waktu, dan tindakan **Buka surat**.
- Membuka panel tidak mengubah status dibaca. Membuka satu entri menandai entri itu sebagai dibaca; sediakan tindakan tandai dibaca tanpa berpindah halaman.
- Tampilkan kondisi kosong, memuat, dan gagal dengan tombol coba lagi di panel.
- Login, reconnect, dan pemuatan riwayat tidak memunculkan popup, suara, atau membuka panel otomatis. Notifikasi baru memberi animasi lonceng dan suara singkat sesuai ketentuan di bawah.
- Gangguan koneksi ditampilkan secara tenang di panel, tanpa banner yang menggeser halaman.
- Ikuti komponen, warna, tipografi, dan ikon aplikasi. Dukung keyboard, label tombol yang jelas, serta indikator belum dibaca yang tidak mengandalkan warna saja.

## Penyimpanan dan sumber notifikasi

Gunakan tabel `notifications` yang mengikuti struktur Laravel, dengan UUID notifikasi dan relasi pengguna memakai primary key numerik yang sudah ada. Tambahkan `event_key` dengan constraint unik per penerima untuk mencegah duplikasi.

| Hasil proses                                           | Masuk pusat notifikasi              |
| ------------------------------------------------------ | ----------------------------------- |
| Surat berhasil diterbitkan                             | Ya                                  |
| PDF berhasil tersedia                                  | Ya                                  |
| Preview berhasil                                       | Tidak; cukup perbarui halaman surat |
| Preview, penerbitan, atau PDF berstatus gagal/usang    | Ya                                  |
| Antrean, sedang berjalan, dan percobaan ulang otomatis | Tidak                               |

- Catat notifikasi pada perubahan status akhir, dalam transaksi yang sama dengan perubahan operasi. Rollback tidak boleh meninggalkan notifikasi.
- Satu hasil operasi menghasilkan maksimal satu notifikasi per penerima. Pengulangan worker tidak membuat entri baru atau mengembalikan notifikasi yang sudah dibaca menjadi belum dibaca.
- Penerima adalah peminta operasi dan pengguna yang terdaftar sebagai peminta PDF bersama, mengikuti batas akses SSE saat ini.
- Jika pengguna baru meminta PDF yang sudah tersedia, catat hasil untuk pengguna tersebut secara idempoten.
- Simpan ringkasan hasil dan identitas surat di data notifikasi agar riwayat tetap terbaca setelah operasi dibersihkan.
- Snapshot SSE dan login hanya membaca data; keduanya tidak membuat notifikasi.
- Riwayat dimulai dari hasil baru tanpa backfill. Versi pertama tidak menambah penghapusan otomatis, email, push browser, atau sumber notifikasi modul lain.

## API dan sinkronisasi

- Tambahkan endpoint terautentikasi untuk ringkasan jumlah belum dibaca, daftar dengan filter dan cursor, menandai satu entri dibaca, serta menandai semua dibaca.
- Semua akses dibatasi melalui relasi notifikasi pengguna yang login; UUID milik pengguna lain menghasilkan 404. Operasi tandai dibaca bersifat idempoten.
- Gunakan Resource dan tipe TypeScript bersama untuk data notifikasi. Navigasi dan permintaan frontend memakai Wayfinder.
- Hapus callback popup `onTerminal` dan penyimpanan deduplikasi popup di `sessionStorage`. Pertahankan deduplikasi revisi operasi untuk pembaruan status halaman.
- Gunakan satu koneksi SSE yang sudah ada. Tambahkan sinyal perubahan notifikasi pada transport yang sama, termasuk setelah status dibaca berubah, agar tab lain ikut memperbarui jumlahnya.
- Pertahankan kompatibilitas payload UUID operasi; bedakan sinyal notifikasi dari sinyal operasi sebelum memprosesnya.
- Gabungkan sinyal beruntun menjadi satu pengambilan ulang ringkasan. Muat ulang daftar hanya saat panel terbuka, abaikan respons usang, dan sinkronkan kembali saat reconnect atau tab aktif.
- Tutup koneksi dan bersihkan state saat logout atau berganti akun.

## Pengujian dan penerapan

- Login dengan banyak operasi lama, reload, dan reconnect tidak menghasilkan popup maupun notifikasi baru.
- Hasil penting tersimpan sekali; preview sukses dan retry sementara tidak tersimpan.
- Uji rollback, worker berulang, beberapa peminta PDF, dan peminta PDF yang bergabung setelah proses selesai.
- Status dibaca bertahan setelah login ulang dan sinkron di beberapa tab.
- Uji isolasi pengguna, filter, cursor, jumlah belum dibaca, serta tindakan dibaca yang diulang.
- Pastikan status preview/PDF/penerbitan di halaman surat tetap diperbarui setelah popup dihapus.
- Periksa panel pada desktop dan ponsel, keyboard, kondisi kosong, gangguan jaringan, logout, dan pergantian akun.
- Jalankan tes backend/frontend terkait, TypeScript, build, dan Pint.

## Asumsi dan keputusan

- Aplikasi masih development dan belum production, sesuai keterangan pengguna.
- Pengguna memilih tanpa popup SSE dan meminta pusat notifikasi dirancang sebagai penggantinya.
- Versi pertama mencatat hasil penting surat, bukan seluruh transisi proses.
- Tidak diperlukan pemindahan riwayat lama. Gunakan migration Laravel; refresh database development dapat mengikuti arahan pengguna saat implementasi.
- Implementasi menambahkan tabel `notifications` melalui migration Laravel. Migration sudah dijalankan pada database development tanpa refresh database.

## Hasil verifikasi implementasi

- Tes backend mencakup akses per akun, cursor pagination, status dibaca, hasil penting surat, transaksi rollback, pencegahan duplikasi, peminta PDF bersama/terlambat, serta kompatibilitas SSE operasi surat.
- Tes frontend mencakup riwayat SSE tanpa popup, reconnect, pembatalan permintaan, respons terlambat, filter, pagination, serta perubahan status dibaca.
- Pemeriksaan TypeScript, build produksi, lint frontend yang berubah, dan Pint dijalankan.
- Panel kosong diperiksa pada browser dengan akun development yang sudah ada. Tampilan berisi notifikasi dan interaksi filter/tandai dibaca diperiksa menggunakan data contoh yang hanya dicegat di browser, tanpa membuat record development.
- Pemeriksaan browser pada lebar 1440, 768, dan 390 piksel memastikan tidak ada luapan horizontal atau error JavaScript. Escape menutup panel dan mengembalikan fokus ke lonceng.
- Riwayat lama tidak dimasukkan ke pusat notifikasi. Proses baru yang memenuhi kriteria akan mengisi daftar secara otomatis.

## Tambahan animasi dan suara notifikasi

- Ikon header memakai `resources/js/components/ui/notification-01.tsx`. Animasi dipicu sekali untuk kedatangan notifikasi baru dan mengikuti preferensi reduced motion.
- Audio memakai `switch007Sound` dari `resources/js/lib/switch-007.ts` melalui sound engine yang sudah tersedia, dengan volume `0.35`.
- Sinyal SSE membedakan `created`, `read`, dan `sync`. Hanya `created` dengan UUID notifikasi yang valid memicu animasi dan audio; perubahan badge tidak dipakai sebagai pemicu.
- Sinyal pembuatan baru hanya diterbitkan saat record pertama kali tersimpan, dalam transaksi yang sama. Payload UUID operasi dan sinyal perubahan notifikasi lama tetap didukung.
- Web Locks dan catatan `localStorage` berversi per akun mengoordinasikan audio: maksimal satu tab pada browser dan origin yang sama memainkan satu notifikasi, termasuk dari tab latar belakang. Catatan dibatasi pada 1.000 UUID tanpa menyimpan isi notifikasi.
- Kedatangan beruntun dibatasi maksimal satu bunyi dan satu animasi per detik, tanpa antrean bunyi susulan.
- Audio diaktifkan melalui interaksi pengguna pertama. Panel menyediakan **Aktifkan suara** jika audio belum siap. Aktivasi tidak memainkan bunyi notifikasi dan tidak mengulang kedatangan lama.
- Audio yang ditolak browser tidak memunculkan popup. Jika koordinasi tab atau penyimpanan tidak tersedia, audio tetap diam; badge dan animasi tetap bekerja.
- Logout atau pergantian akun membatalkan audio tertunda dan menghentikan pemutaran milik akun tersebut. Tidak ada dependency atau migration tambahan.
- Pemeriksaan browser dua tab membuktikan satu pemutaran untuk UUID yang sama saat keduanya berada di latar belakang; duplikasi dan burst tidak menambah bunyi. Reduced motion menonaktifkan gerakan lonceng.
- Verifikasi tambahan lulus: 57 tes backend terkait, 137 tes frontend terkait, TypeScript, build produksi, lint pada file yang berubah, dan Pint. Tes audio mencakup pembatalan saat resume/decode, cleanup pemutaran, kegagalan storage/lock, serta pergantian akun.
- Suara memerlukan browser yang mengizinkan Web Audio setelah interaksi pengguna. Perangkat tidur atau tab yang ditangguhkan browser tidak menjamin notifikasi berbunyi.
