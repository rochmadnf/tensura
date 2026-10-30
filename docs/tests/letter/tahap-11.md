# Tahap 11 — Frontend Pertanyaan Surat dan Kalender Kerja

Status implementasi: dalam pengerjaan (fase frontend).
Acuan: [Rencana aktif](../../planning/letter-inputs-calendar-backend-first-plan.md), [Kontrak v1](../../planning/letter-inputs-calendar-contract.md), dan [Handoff backend](../../planning/letter-inputs-calendar-backend-handoff.md).

Pengujian visual dilakukan pengguna secara mandiri pada lingkungan browser. Checklist di bawah ini disediakan untuk pengujian pengguna; pengujian yang belum dilaporkan pengguna tidak dicentang otomatis.

---

## Yang Ditambahkan pada Tahap Ini

1. **Konfigurasi Pertanyaan Templat Surat**:
    - Pilihan tunggal (Radio): penambahan opsi dinamis, kode opsi unik, nama variabel dan kode opsi otomatis yang dapat disesuaikan manual tanpa tertimpa, tanda terpilih default ✔, token per opsi `${variabel:kode_opsi}`.
    - Angka: mode bulat (integer) dan desimal (scale 1–6), opsi izinkan nilai negatif, token `${variabel}` dan `${variabel:terbilang}`.
    - Nominal: format Rupiah bulat atau desimal 2 digit, token `${variabel}` dan `${variabel:terbilang}` (menyebut Rupiah dan Sen).
    - Rentang Tanggal: basis hari kalender atau hari kerja, format tanggal, token `${variabel:mulai}`, `${variabel:akhir}`, `${variabel:jumlah_hari}`, `${variabel:terbilang}`, `${variabel:durasi}`.
    - Tombol salin token pada editor untuk seluruh varian token turunan.
2. **Isian Surat dan Form Generate Uji**:
    - Input radio, angka, nominal, dan rentang tanggal pada pembuatan/perubahan draft Surat dan form generate uji templat.
    - Feedback langsung melalui endpoint resolver (`lt.values.resolve`) untuk terbilang, nominal terformat, dan durasi hari kerja.
    - Debounce request, pembatalan/pengabaian respons lama, status menghitung, dan penanganan galat tanpa menghilangkan isian.
    - Tampilan baca draft Surat yang memformat rentang tanggal dengan benar tanpa memunculkan `[object Object]`.
3. **Kalender Kerja (Data Master)**:
    - Menu navigasi Kalender Kerja di sidebar Data Master (`wc.index`).
    - Halaman Inertia `work-calendar/index` dengan empat tampilan: Bulan (Month), Minggu (Week), Hari (Day), dan Agenda.
    - Pengaturan tahun kalender, pola mingguan hari kerja (Senin–Sabtu), dan konfirmasi kesiapan tahun.
    - Tambah/ubah/hapus kegiatan biasa (dengan jam dan sepanjang hari), libur nasional, dan hari kerja pengganti.
    - Penanganan konflik revisi (CAS 409) dengan pemulihan input.
4. **Pengambilan Hari Libur Indonesia**:
    - Tombol pengambilan libur nasional dengan alur: request preview -> dialog tinjauan/pemilihan item -> apply terkonfirmasi.
    - Penandaan status item (baru, duplikat, konflik), opsi cuti bersama default mati, dan pencegahan duplikasi data manual.
5. **Integrasi Dependensi Kalender pada Surat**:
    - Indikator status kalender (`calendar_state`: current, stale, missing) pada draft Surat beserta rincian alasan penahanan penerbitan.
    - Alur refresh dependensi master terkonfirmasi melalui preview dan apply.

---

## Checklist Pengujian Mandiri Pengguna

### 1. Konfigurasi Templat Surat

- [ ] Buka templat surat draft, tambahkan pertanyaan jenis **Pilihan tunggal (Radio)**.
    - **Harapan:** Muncul field daftar opsi. Saat mengisi label pertanyaan/opsi untuk pertama kali, nama variabel dan kode opsi terisi otomatis.
    - **Harapan:** Mengubah nama variabel atau kode opsi secara manual tidak tertimpa saat label diubah kembali.
    - **Harapan:** Tersedia tombol salin token untuk setiap kode opsi: `${nama:kode_opsi}`.
- [ ] Tambahkan pertanyaan jenis **Angka**.
    - **Harapan:** Dapat memilih mode Bulat atau Desimal, mengatur presisi (scale), dan toggle izinkan nilai negatif. Tersedia tombol salin `${nama}` dan `${nama:terbilang}`.
- [ ] Tambahkan pertanyaan jenis **Nominal**.
    - **Harapan:** Dapat memilih Rupiah bulat atau desimal 2 digit. Tersedia tombol salin `${nama}` dan `${nama:terbilang}`.
- [ ] Tambahkan pertanyaan jenis **Rentang tanggal**.
    - **Harapan:** Dapat memilih basis Hari Kalender atau Hari Kerja, serta memilih format tanggal. Tersedia tombol salin kelima token (`mulai`, `akhir`, `jumlah_hari`, `terbilang`, `durasi`).
- [ ] Simpan konfigurasi templat.
    - **Harapan:** Konfigurasi tersimpan sukses. Validasi bukti DOCX mendeteksi token turunan baru secara tepat.

### 2. Form Generate Uji Templat

- [ ] Buka tab Generate Uji pada templat yang memiliki pertanyaan baru.
    - **Harapan:** Pertanyaan radio muncul sebagai pilihan radio; angka/nominal muncul sebagai input angka/uang; rentang tanggal muncul dengan pemilih tanggal mulai dan akhir.
    - **Harapan:** Terbilang dan durasi hari terisi otomatis saat input dimasukkan (status menghitung muncul singkat dan tidak menghilangkan input).
    - **Harapan:** Generate DOCX berhasil menghasilkan dokumen uji dengan token yang terganti sesuai nilai contoh.

### 3. Draft Surat dan Tampilan Baca

- [ ] Buat draft surat baru menggunakan templat dengan pertanyaan baru.
    - **Harapan:** Input form draft menampilkan field yang sama seperti generate uji. Input nominal dan angka memformat nilai dengan benar.
    - **Harapan:** Respons feedback resolver terbilang/durasi tampil tanpa mengganggu pengetikan.
- [ ] Simpan draft surat dan lihat mode baca (bukan edit).
    - **Harapan:** Nilai rentang tanggal diformat rapi (misal: `02-11-2026 s.d. 14-11-2026`), **bukan** bertuliskan `[object Object]`.
    - **Harapan:** Nilai radio menampilkan label opsi terpilih.

### 4. Kalender Kerja

- [ ] Buka menu **Data Master > Kalender Kerja** dari sidebar.
    - **Harapan:** Halaman kalender terbuka menampilkan tahun berjalan dan bulan saat ini.
- [ ] Uji navigasi empat tampilan: **Bulan**, **Minggu**, **Hari**, **Agenda**.
    - **Harapan:** Tampilan berganti sesuai tombol tab; URL query tersinkronisasi (`?view=...`) dan mendukung Back/Forward browser.
    - **Harapan:** Tampilan Minggu dan Hari menampilkan grid waktu 00:00–24:00 (default scroll area kerja 08:00).
- [ ] Kelola Pengaturan Tahun:
    - **Harapan:** Dapat membuat tahun baru (dengan opsi salin pola mingguan dari tahun sebelumnya).
    - **Harapan:** Dapat mengubah pola mingguan (Senin–Sabtu) dan mengonfirmasi kesiapan tahun.
- [ ] Tambah Kegiatan:
    - **Harapan:** Dialog tambah kegiatan mendukung jenis Kegiatan biasa (dengan jam atau sepanjang hari), Libur, dan Hari kerja pengganti.
    - **Harapan:** Libur dan Hari kerja pengganti otomatis sepanjang hari dan memperbarui status kalender.
- [ ] Uji Ambil Libur Nasional:
    - **Harapan:** Klik tombol "Ambil Hari Libur" memicu request preview dan membuka dialog tinjauan hasil.
    - **Harapan:** Daftar libur menampilkan item Baru, Duplikat, dan Konflik. Item duplikat/konflik tidak dapat dipilih.
    - **Harapan:** Klik tombol Terapkan berhasil memasukkan hari libur terpilih ke kalender tanpa menimpa kegiatan manual.

### 5. Ketergantungan Kalender pada Surat

- [ ] Buat draft surat dengan pertanyaan rentang tanggal berbasis hari kerja untuk tahun yang belum dikonfirmasi (draft).
    - **Harapan:** Draft menampilkan status kalender belum siap / missing, penerbitan ditahan dengan alasan yang jelas.
- [ ] Konfirmasi tahun kalender terkait di Kalender Kerja, lalu kembali ke draft surat.
    - **Harapan:** Muncul opsi atau tombol Refresh dependensi kalender.
    - **Harapan:** Melakukan refresh menampilkan tinjauan perubahan durasi; setelah konfirmasi refresh, draft siap diperiksa/diterbitkan kembali.

---

## Rekap Penerimaan Pengguna

- **Tanggal / Lingkungan Pengujian:** belum diisi.
- **Tahap / Fitur yang Diuji:** belum diisi.
- **Temuan Masalah:** belum diisi.
- **Keputusan:** belum diputuskan.
