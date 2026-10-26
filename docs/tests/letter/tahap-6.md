# Tahap 6 — Worker, SSE, dan Preview Draft

Status implementasi: direncanakan. Status uji mandiri pengguna: belum dijalankan.

Acuan: [rencana teknis](../../planning/letter/teknis.md). Prasyarat: worker antrean Surat dan endpoint SSE telah dikonfigurasi pada lingkungan uji.

## Yang sudah dapat dilakukan setelah tahap selesai

Minta generate preview draft, lanjutkan ke halaman lain sambil menunggu, buka hasil dari notifikasi, dan konfirmasi pemeriksaan. Belum ada nomor resmi atau unduhan Word draft.

## Checklist mandiri

- [ ] Minta preview lalu klik kembali saat proses berjalan. **Harapan:** tidak membuat pekerjaan identik berkali-kali; status Menunggu/Diproses terlihat.
- [ ] Pindah ke halaman Pegawai sambil menunggu. **Harapan:** worker tetap berjalan dan notifikasi selesai/gagal muncul dengan aksi Buka hasil, tanpa memaksa navigasi.
- [ ] Refresh halaman atau putus/sambungkan jaringan ketika job berjalan. **Harapan:** status pulih dan hasil tidak hilang/tidak digandakan.
- [ ] Tutup tab, tunggu proses selesai, lalu buka aplikasi/detail lagi. **Harapan:** hasil tetap tersedia tanpa harus membuat job baru.
- [ ] Logout lalu login sebagai akun berbeda. **Harapan:** tidak menerima event atau tautan berkas privat dari sesi sebelumnya.
- [ ] Buka hasil DOCX. **Harapan:** nomor `445/XX.XX/PKM-KMJ/...` berwarna warning; tidak ada tombol unduh Word draft. Cetak draft memiliki penanda DRAFT.
- [ ] Konfirmasi pemeriksaan setelah render siap. **Harapan:** terikat ke hasil yang dibuka; ubah isian lalu coba memakai konfirmasi lama, harus diminta periksa ulang.
- [ ] Ubah font/versi templat yang relevan selama job berjalan. **Harapan:** hasil usang tidak menggantikan hasil draft terbaru atau menjadi bukti pemeriksaan yang sah.
- [ ] Pada lingkungan uji, jalankan kasus worker gagal atau berhenti. **Harapan:** draft/input tersimpan, status dapat dipahami dan retry tersedia setelah kegagalan teridentifikasi.
- [ ] Periksa notifikasi setelah beberapa kali navigasi/reconnect. **Harapan:** tidak muncul toast selesai yang sama berulang-ulang pada tab yang sama.

## Hasil pengguna

Tanggal, kondisi jaringan/worker, dan temuan: belum diisi. SSE bukan bukti job selesai bila artefaknya belum tersedia.
