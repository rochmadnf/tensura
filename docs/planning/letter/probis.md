# Surat — Proses Bisnis

Status: proses bisnis utama telah selesai disepakati berdasarkan diskusi sampai 26 Oktober 2026. Dokumen ini menjadi acuan rancangan teknis dan belum menyatakan fitur Surat sudah diimplementasikan.

Acuan fondasi: [proses bisnis Templat Surat](../letter-template-management-probis.md). Ketentuan berikut melengkapi fitur templat untuk pembuatan surat sebenarnya. Rencana teknis bertahap ada di [teknis.md](teknis.md); perubahan keputusan selama pembahasan teknis yang memengaruhi perilaku pengguna diselaraskan di dokumen ini.

## 1. Tujuan, cakupan, dan pelaku

- Tahap pertama berfokus pada pembuatan surat dari templat oleh admin/operator.
- Permintaan surat diterima di luar aplikasi. Pengajuan mandiri pegawai, surat masuk, dan disposisi belum termasuk cakupan ini.
- Operator menyelesaikan surat tanpa alur persetujuan dalam aplikasi. Pemilihan penandatangan mengisi data dokumen; tanda tangan dilakukan di luar aplikasi.
- Seluruh pengguna yang sudah login dapat melihat dan mengelola surat bersama, termasuk surat yang dibuat operator lain.
- Pembuat surat dan pelaku setiap tindakan tetap tercatat.

## 2. Identitas dan alur utama

Setiap surat memiliki judul/perihal yang wajib diisi operator. Judul digunakan untuk identitas, pencarian, dan penyebutan surat dalam riwayat atau tautan. Judul tidak otomatis dimasukkan ke isi DOCX.

**Pilih templat → Isi perihal, data, penandatangan, dan tanggal → Simpan draft → Preview dan konfirmasi pemeriksaan → Terbitkan → Download Word.**

| Tahap | Ketentuan |
| --- | --- |
| Draft | Belum mengambil nomor resmi; dapat dilengkapi dan diperiksa |
| Pemeriksaan | Operator wajib membuka preview terbaru dan mengonfirmasi sudah memeriksa |
| Terbit | Nomor resmi diberikan setelah pemeriksaan ketersediaan pada saat penerbitan |
| Revisi | Perubahan disiapkan sebagai draft revisi; versi terbit sebelumnya tetap resmi sampai revisi diterbitkan |
| Batal | Wajib alasan; surat dan riwayat tetap disimpan, sedangkan nomor boleh digunakan kembali |
| Pemulihan | Surat tetap Batal selama draft pemulihan disiapkan; kembali Terbit setelah pemeriksaan dan penerbitan pemulihan berhasil |

Perubahan isi setelah pemeriksaan mewajibkan preview dan konfirmasi ulang. Alur perubahan templat, konflik pengeditan, dan kegagalan pemrosesan mengikuti ketentuan di bawah.

## 3. Preview dan unduhan

| Aksi | Perilaku |
| --- | --- |
| Preview | Diutamakan DOCX langsung di browser, hanya-baca, jika hasil uji kelayakannya diterima pengguna |
| Cetak | Mencetak dari viewer; draft tetap ditandai DRAFT dan belum bernomor resmi |
| Lihat/Cetak PDF | Konversi LibreOffice atas permintaan sebagai alternatif pemeriksaan/cetak |
| Unduh | Setelah terbit, satu dropdown Unduh menyediakan Word (.docx) dan PDF (.pdf), bersyarat kelayakan preview DOCX |

- Preview draft menampilkan nomor seperti `445/XX.XX/PKM-KMJ/X/2026`, dengan warna teks warning/kuning. Bagian halaman dan urutan belum merupakan nomor resmi; bulan dan tahun mengikuti periode surat.
- Preview surat terbit menggunakan nomor resminya.
- Word draft tidak tersedia untuk diunduh.
- Surat baru, revisi, dan pemulihan wajib melalui preview draft terbaru dan konfirmasi pemeriksaan sebelum diterbitkan. Bukti pemeriksaan mengikuti jenis preview yang benar-benar dibuka dan versi draft yang diperiksa.
- Preview DOCX belum dianggap layak hanya berdasarkan build atau test otomatis. Pengguna menilai tampilan dan cetaknya secara mandiri; bila belum layak, jalur PDF dipertahankan dan desain yang bersyarat DOCX belum dinyatakan final tersedia.
- Pada jalur DOCX, PDF tidak dibuat otomatis setiap review atau penerbitan. PDF dibuat ketika diminta dan hasil tersimpan digunakan kembali untuk versi yang sama.
- Jika PDF resmi gagal dibuat, surat tetap Terbit dan Word dapat diunduh. Konversi menampilkan status gagal dan aksi Coba Lagi tanpa mengambil nomor baru. PDF opsional yang gagal tidak membatalkan pemeriksaan DOCX yang sah; bila pemeriksaan dipilih melalui PDF, hasil PDF tersebut harus tersedia.
- Word unduhan mempertahankan pengaturan font asli DOCX; substitusi hanya untuk rendering/konversi PDF. Tidak ada dua tombol unduh Word/PDF terpisah pada desain dropdown.
- Tahap pertama mendukung Linux dengan LibreOffice headless dan queue worker. SSE memberitahukan status pekerjaan di layout utama sehingga perpindahan halaman tidak menghentikan proses; hasil tetap tersimpan setelah refresh/tab ditutup. Windows belum termasuk target verifikasi awal.

### Pemeriksaan dan pengelolaan font

- Validasi DOCX pada Templat Surat juga memeriksa font yang digunakan dan membandingkannya dengan font tersedia bagi proses konversi.
- Semua font tersedia tidak menjamin tata letak sama persis dengan Word; pemeriksaan visual tetap dilakukan pengguna.
- Font yang kurang hanya menghasilkan peringatan, bukan galat struktur, dan tidak menghalangi generate, publikasi, penerbitan Surat, atau unduhan.
- Hasil pemeriksaan menampilkan Font Dokumen pada kartu DOCX Sumber dan aksi Unggah font untuk yang kurang. Tombol Font tersedia pada header kartu membuka daftar/pencarian keluarga serta varian dan aksi unggah.
- Admin dapat mengunggah `.ttf`/`.otf`; font dinyatakan siap setelah validasi dan proses rendering benar-benar dapat membacanya. Font yang belum dilengkapi memakai pengganti tersedia pada PDF, dengan peringatan nama font asal/penggantinya.
- Font server tidak otomatis tersedia di browser; viewer perlu memuat font yang disediakan aplikasi atau memberitahukan fallback yang dipakai.
- Setelah font relevan dilengkapi, preview draft terkait dibuat ulang dan diperiksa kembali. Dokumen resmi/historis yang sudah tersedia tidak berubah diam-diam.

## 4. Format dan alokasi nomor

Contoh nomor resmi: **`445/23.44/PKM-KMJ/X/2026`**.

| Bagian | Makna |
| --- | --- |
| `445` | Kode klasifikasi dari master `classification_code`, mengikuti klasifikasi templat |
| `23` | Nomor halaman, `page_number` |
| `44` | Nomor urut, `sequence_number` |
| `PKM-KMJ` | Kode instansi statis |
| `X` | Bulan dalam angka Romawi |
| `2026` | Tahun periode penomoran |

### Placeholder sistem

- `${nomor_surat}` diisi otomatis dengan nomor lengkap. Draft menggunakan penanda `XX.XX`, sedangkan dokumen resmi menggunakan nomor hasil penerbitan.
- `${tanggal_surat}` mengambil tanggal surat pilihan operator, bukan tanggal saat dokumen digenerate.
- Format tanggal diatur pada templat, dengan default `d-m-Y`.
- Kedua placeholder merupakan isian sistem; operator tidak perlu mengetik ulang nilainya sebagai variabel formulir dinamis.
- Jika DOCX templat yang sudah terbit diubah untuk menggunakan placeholder tersebut, perubahan tetap harus melalui validasi dan pemeriksaan ulang sesuai siklus versi templat.

### Urutan otomatis

- Nomor resmi baru dialokasikan saat surat diterbitkan, bukan ketika draft disimpan atau dipreview.
- Urutan berlaku bersama untuk seluruh surat dalam klasifikasi yang sama, termasuk surat dari templat berbeda. Tiga surat dengan klasifikasi `445` mengambil urutan dari sumber penomoran klasifikasi `445` yang sama.
- Nomor halaman dan urut menggunakan minimal dua digit. Setelah `01.99`, nomor berikutnya adalah `02.01`.
- Setiap pergantian bulan, urutan periode baru kembali ke `01.01`. Periode dibedakan berdasarkan bulan dan tahun.
- Angka pada master berarti **nomor terakhir terpakai**. Jika tercatat `23.44`, alokasi otomatis berikutnya adalah `23.45`.
- Angka existing dipindahkan ke periode pilihan admin melalui detail Penomoran Surat → Urutan per Periode. Increment operasional berada pada periode klasifikasi/bulan/tahun, bukan lagi pada baris master klasifikasi.
- Setelah pemindahan lengkap, kolom counter lama pada master dihapus melalui transisi teknis yang menjaga angka existing; master tetap memiliki identitas klasifikasi. Periode baru yang belum disiapkan mulai dari `01.01`.
- Alokasi otomatis melewati nomor yang telah digunakan surat yang belum dibatalkan.
- Nomor yang dilepas karena pembatalan digunakan kembali melalui pilihan manual; pembatalan tidak memundurkan urutan otomatis atau membuat alokasi otomatis mengisi celah nomor batal.

### Nomor manual

- Admin boleh mengubah halaman, urutan, bulan, dan tahun. Kode klasifikasi mengikuti templat dan kode instansi tetap `PKM-KMJ`.
- Nomor manual diperbolehkan selama tersedia pada klasifikasi dan periode bulan/tahun yang dipilih.
- Nomor manual **tidak menggeser urutan otomatis**. Jika alokasi otomatis berikutnya `01.10` dan admin menerbitkan manual `01.20`, alokasi otomatis berikutnya tetap `01.10`.
- Ketika alokasi otomatis kemudian mencapai `01.20` yang masih digunakan, nomor tersebut dilewati.
- Jika nomor pilihan sudah digunakan, penerbitan ditolak dan aplikasi menyarankan nomor tersedia terdekat setelah nomor tersebut pada klasifikasi dan periode yang sama. Setelah urutan `99`, pencarian berlanjut ke halaman berikutnya. Saran bukan pemesanan nomor dan tetap diperiksa saat penerbitan.
- Ketersediaan diperiksa berdasarkan klasifikasi, bulan, tahun, halaman, dan urutan. Pemeriksaan akhir dilakukan saat penerbitan agar operator yang bekerja bersamaan tidak memperoleh nomor yang sama.
- Satu nomor hanya boleh digunakan oleh satu surat yang berstatus Terbit. Versi revisi tetap merupakan bagian dari surat yang sama; draft tidak memesan nomor.

### Tanggal surat dan periode

- Tanggal surat dapat dipilih admin, dengan default hari berjalan.
- Tanggal boleh lampau maupun mendatang, lintas bulan dan tahun.
- Default bulan/tahun nomor mengikuti waktu penerbitan. Admin boleh memilih periode lain.
- Bulan dan tahun tanggal surat wajib sama dengan periode pada nomor. Ketidaksesuaian harus diselesaikan sebelum terbit.
- Jika draft melewati pergantian bulan, penerbitan ditahan sampai operator memilih tetap memakai tanggal/periode lama atau menggantinya. Contoh: draft 31 Oktober yang diterbitkan 1 November memerlukan pilihan eksplisit tersebut; tanggal tidak otomatis ditimpa.
- Perubahan tanggal mewajibkan preview dan konfirmasi pemeriksaan ulang. Periode nomor harus sesuai dengan tanggal yang dipilih.

## 5. Draft, data master, dan versi templat

- Draft menyimpan nilai pegawai dan referensi saat disimpan; perubahan master tidak otomatis menimpa nilai tersebut.
- Operator dapat mengambil data master terbaru secara eksplisit. Perubahan nilai mewajibkan preview dan konfirmasi ulang.
- Draft surat baru, draft revisi, dan draft pemulihan wajib mengikuti **versi templat aktif terbaru**, untuk mengikuti perubahan regulasi sebagaimana kebutuhan pengguna.
- Jika templat dinonaktifkan, draft tetap tersimpan tetapi tidak dapat diterbitkan sampai templat aktif kembali. Surat yang telah terbit beserta dokumennya tetap dapat diakses.
- Draft surat baru dapat dihapus dengan konfirmasi.
- Draft revisi dapat dibuang dengan konfirmasi tanpa menghapus surat terbit maupun riwayat versi resminya.

### Penyesuaian versi templat

1. Draft menampilkan pemberitahuan bahwa versi templat berubah dan menahan penerbitan sampai penyesuaian selesai.
2. Operator memilih **Sesuaikan ke versi terbaru** dan melihat ringkasan perubahan; penyesuaian tidak dijalankan diam-diam ketika draft dibuka.
3. Isian yang nama variabel, jenis, dan sumber datanya tetap sama dipertahankan. Variabel baru dilengkapi, variabel yang berubah jenis/sumber diperiksa ulang, dan isian variabel yang dihapus tidak digunakan dalam dokumen baru.
4. Pilihan penandatangan dipertahankan hanya jika masih memenuhi konfigurasi terbaru.
5. Preview dan konfirmasi sebelumnya dibatalkan. Operator menyelesaikan penyesuaian, membuat preview terbaru, dan mengonfirmasi pemeriksaan sebelum menerbitkan.

Penyesuaian versi tidak otomatis mengambil ulang nilai pegawai/referensi yang masih kompatibel. Pembaruan data master tetap dilakukan secara eksplisit.

### Pengeditan bersama

- Jika dua operator mengedit draft yang sama, simpan pertama diterima. Penyimpanan berikutnya yang masih berdasarkan versi data lama ditolak agar tidak menimpa perubahan terbaru.
- Pesan kepada operator: **“Draft telah diperbarui oleh Ani. Muat versi terbaru sebelum menyimpan.”** Nama disesuaikan dengan pelaku pembaruan.
- Input yang belum tersimpan tetap ditampilkan agar dapat disalin. Setelah memuat versi terbaru, operator dapat menyesuaikan dan menyimpan kembali.
- Pemeriksaan yang sama berlaku saat penerbitan agar data lama tidak menerbitkan atau menimpa perubahan terbaru.

## 6. Revisi dan riwayat dokumen

- Revisi dibuat pada surat yang sama dengan nomor tetap, melalui draft revisi.
- Hanya satu draft revisi diperbolehkan per surat; operator melanjutkan draft yang sama.
- Alasan revisi wajib sebelum revisi diterbitkan dan ditampilkan dalam riwayat.
- Isi dan pilihan penandatangan boleh diubah. Nomor dan tanggal surat tetap.
- Revisi menggunakan versi aktif terbaru dari templat yang sama. Keputusan ini menggantikan ketentuan awal yang mempertahankan versi templat asal.
- Versi terbit sebelumnya tetap menjadi versi resmi selama draft revisi belum diterbitkan.
- Setelah revisi diterbitkan, versi baru menjadi versi resmi dan versi lama tersedia dalam riwayat.
- Versi terbit terdahulu tetap dapat dipreview dan diunduh dalam format Word melalui riwayat, dengan keterangan versi historis.
- Halaman utama surat menyediakan versi resmi terbaru.
- Penyimpanan nilai dan dokumen historis harus menjaga hasil versi terbit meskipun data master atau templat kemudian berubah; rincian snapshot mengikuti pembahasan teknis selanjutnya dan acuan Templat Surat.

## 7. Pembatalan dan penggunaan ulang nomor

- Pembatalan surat terbit wajib disertai alasan. Surat, alasan, waktu pembatalan, pelaku, dan riwayat tetap tercatat.
- Jika masih terdapat draft revisi, draft tersebut ikut dibuang saat pembatalan. Konfirmasi wajib menjelaskan dampak ini; versi yang pernah terbit dan riwayat tetap disimpan.
- Nomor surat batal **boleh digunakan kembali**. Ketentuan ini menggantikan keputusan awal bahwa nomor batal tidak dapat digunakan ulang.
- Surat pengganti tetap melalui pemeriksaan ketersediaan nomor saat penerbitan.
- Selama nomor sedang digunakan surat lain, nomor pada **detail surat batal saja** berwarna merah dengan tooltip: **“Nomor ini telah digunakan kembali oleh surat ‘X’.”**
- `X` merujuk judul/perihal surat yang saat ini menggunakan nomor; tersedia tautan untuk membuka detailnya.
- Jika nomor kembali tersedia, tampilkan **“Nomor tersedia untuk digunakan kembali.”** Penanda merah tidak dipertahankan hanya karena pernah digunakan ulang.
- Semua kejadian penggunaan ulang tetap tercatat dalam timeline, termasuk jika surat pengganti kemudian dibatalkan atau surat asal dipulihkan. Jika digunakan ulang beberapa kali, tautan penanda mengikuti pemakai saat ini dan pemakai sebelumnya dapat ditelusuri melalui riwayat.
- Nomor pada surat pengganti tampil normal.
- Word surat batal tetap dapat diunduh melalui riwayat setelah konfirmasi bahwa surat telah dibatalkan. Alasan dan waktu pembatalan ditampilkan.
- Penggunaan ulang nomor batal dipilih manual; urutan otomatis tetap berjalan sesuai bagian penomoran.

## 8. Pemulihan surat batal

- Surat batal dapat dipulihkan melalui **draft pemulihan**, dengan alasan pemulihan wajib.
- Surat tetap berstatus Batal sampai draft pemulihan selesai diperiksa dan berhasil diterbitkan.
- Pemulihan menggunakan versi templat aktif terbaru dari templat yang sama dan mempertahankan tanggal surat asal.
- Nomor lama digunakan jika masih tersedia. Jika sudah digunakan surat lain, cari dan sarankan nomor tersedia berdasarkan aturan saran nomor pada klasifikasi serta bulan/tahun tanggal surat asal.
- Operator memeriksa nomor yang diusulkan, preview terbaru, dan mengonfirmasi pemeriksaan sebelum penerbitan pemulihan. Ketersediaan nomor tetap diperiksa ulang saat penerbitan.
- Setelah pemulihan berhasil, surat kembali berstatus Terbit. Jika nomor berubah, detail menampilkan nomor resmi terbaru; nomor sebelumnya, dokumen lama, dan riwayat perubahan tetap disimpan.

## 9. Kegagalan generate dan penerbitan

| Kejadian | Perlakuan |
| --- | --- |
| Generate Word gagal | Isian draft tetap tersimpan; tampilkan penyebab yang dapat ditindaklanjuti dan aksi Coba Lagi |
| Render preview draft utama gagal | Draft tetap tersimpan; pemeriksaan dan penerbitan tertahan sampai hasil yang diperiksa tersedia |
| Konversi PDF alternatif gagal | Dapat dicoba ulang; tidak menghalangi pemeriksaan DOCX yang sah. Jika memilih pemeriksaan melalui PDF, tunggu PDF tersedia |
| Penerbitan gagal sebelum berhasil | Surat baru tetap draft; nomor tidak dianggap terpakai dan operator dapat mencoba kembali |
| Word resmi berhasil dibuat dan penerbitan berhasil, tetapi PDF resmi gagal | Surat tetap Terbit, Word dapat diunduh, dan konversi PDF dapat dicoba ulang tanpa nomor baru |
| Penerbitan berhasil tetapi respons ke operator terputus | Saat dibuka atau dicoba kembali, aplikasi mengembalikan surat yang sudah terbit tanpa penerbitan ganda atau nomor kedua |

- Pada kegagalan revisi, versi resmi sebelumnya tetap tersedia sampai penerbitan revisi berhasil.
- Pada kegagalan pemulihan sebelum berhasil terbit, surat tetap Batal dan draft pemulihan tetap dapat dilanjutkan.
- Percobaan ulang konversi PDF resmi menggunakan versi Word resmi yang bersangkutan, sehingga tidak mengubah isi historis karena pembaruan data master atau templat.
- Kegagalan PDF resmi tidak membatalkan konfirmasi pemeriksaan preview draft yang sah sebelum penerbitan. Pada jalur DOCX, konversi resmi hanya dijalankan atas permintaan.

## 10. Daftar dan timeline Surat

- Satu baris mewakili satu surat, bukan setiap versi revisinya.
- Kolom: Perihal, Nomor, Templat, Tanggal Surat, Status, Pembuat, dan Aksi.
- Status utama: Draft, Terbit, dan Batal. Penanda tambahan sesuai kondisi: Ada draft revisi, Ada draft pemulihan, dan Preview PDF gagal.
- Surat terbit yang sedang direvisi tetap ditampilkan sebagai **Terbit · Ada draft revisi**.
- Pencarian mencakup perihal dan nomor.
- Filter mencakup status, templat, periode nomor, dan pembuat.
- Urutan awal berdasarkan waktu pembaruan terbaru.

Timeline mencatat:

- Pembuatan draft dan perubahan yang disimpan.
- Penyesuaian versi templat dan pembaruan data master.
- Konfirmasi pemeriksaan, penerbitan awal, dan penerbitan revisi.
- Pembatalan, pemulihan, serta perubahan dan penggunaan ulang nomor.
- Kegagalan pemrosesan.

Setiap kejadian menyertakan waktu dan pelaku; proses otomatis diidentifikasi sebagai proses sistem. Alasan wajib ditampilkan untuk revisi, pembatalan, dan pemulihan. Membuka detail atau mengunduh dokumen tidak menambah entri timeline.

## 11. Acuan implementasi dan pengujian

Proses bisnis di atas sudah disepakati. [Rencana teknis](teknis.md) membagi implementasi menjadi 10 tahap, dengan hasil web, pemeriksaan otomatis, pesan commit bahasa Inggris, dan checklist mandiri per tahap. Rencana tersebut menjabarkan:

- Struktur data surat, draft, versi resmi, snapshot nilai/dokumen historis, alasan, timeline, dan hubungan penggunaan ulang nomor.
- Integrasi placeholder sistem serta konfigurasi format tanggal dengan validasi, pengujian, dan siklus versi Templat Surat yang sudah ada.
- Pencatatan urutan per klasifikasi/periode, periode awal angka master, pemisahan alokasi otomatis dari pemakaian manual, batas kapasitas halaman, dan pengamanan alokasi/pemulihan nomor bersamaan.
- Penyimpanan dan pemrosesan Word/PDF, kelayakan konversi pada platform server, serta batas keberhasilan penerbitan agar kegagalan PDF resmi tetap dapat dicoba ulang setelah surat terbit.
- Pengamanan percobaan ulang agar tidak menerbitkan dua kali, deteksi perubahan draft oleh operator lain, dan penyimpanan isian saat penyesuaian versi.
- Rancangan halaman, integrasi route, skenario pengujian penerimaan dan konkurensi, serta tahapan implementasi mengikuti konvensi repository.

Agent tidak menjalankan pengujian visual/browser manual maupun inspeksi visual hasil dokumen. Pengguna menjalankannya secara mandiri melalui [checklist tahap 1–10](../../tests/letter/tahap-1.md) dan file tahap berikutnya. Pemeriksaan otomatis nonvisual tetap dilakukan; hasil manual tidak ditandai lulus sebelum laporan pengguna.
