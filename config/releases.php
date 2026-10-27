<?php

return [
    '0.1.0' => [
        'summary' => 'Rilis awal untuk pengelolaan data pendukung dan pembuatan surat berbasis templat DOCX.',
        'features' => [
            [
                'title' => 'Pegawai dan jabatan',
                'description' => 'Kelola data pegawai dan jabatan, cari dan filter pegawai, catat STR/SIP, serta impor dan ekspor data pegawai.',
            ],
            [
                'title' => 'Daftar referensi',
                'description' => 'Kelola grup dan item referensi beserta urutannya untuk pilihan pada formulir surat.',
            ],
            [
                'title' => 'Templat surat',
                'description' => 'Atur variabel dan penandatangan, unggah dan validasi DOCX, buat hasil uji, serta kelola publikasi dan riwayat versi templat.',
            ],
            [
                'title' => 'Font dokumen',
                'description' => 'Periksa font yang digunakan dokumen, lihat font yang tersedia, dan unggah font tambahan. Font yang kurang ditampilkan sebagai peringatan.',
            ],
            [
                'title' => 'Penomoran surat',
                'description' => 'Kelola klasifikasi dan urutan bulanan bersama, gunakan nomor otomatis atau manual, serta telusuri riwayat pemakaian nomor.',
            ],
            [
                'title' => 'Pengelolaan surat',
                'description' => 'Simpan draft bersama, periksa preview, terbitkan, revisi, batalkan, dan pulihkan surat dengan riwayat versi serta aktivitas. Nomor surat batal dapat dipilih kembali dengan jejak pemakaian tersimpan.',
            ],
            [
                'title' => 'Preview dan unduhan',
                'description' => 'Lihat DOCX dan PDF melalui viewer, cetak dokumen, serta unduh Word resmi atau PDF melalui menu Unduh. Dokumen resmi dan historis tetap tersimpan.',
            ],
            [
                'title' => 'Arsip dan akun',
                'description' => 'Tinjau dan pulihkan data yang mendukung arsip, serta kelola profil, foto, dan kata sandi akun.',
            ],
        ],
        'notes' => [
            'Aplikasi masih dalam pengembangan awal. Kesesuaian tampilan dan hasil cetak dokumen masih mengikuti evaluasi pengguna.',
        ],
    ],
];
